<?php

declare(strict_types=1);

namespace Msstc4Symfony\TracingBridgeProfiling\Infrastructure\Tracing;

use Msstc4Symfony\ProfilingBundle\Application\DetachedSpanFactoryInterface;
use Msstc4Symfony\ProfilingBundle\Application\Processor\CreateSpan\CreateSpanProcessorInterface;
use Msstc4Symfony\ProfilingBundle\Application\Span\SpanInterface;
use Msstc4Symfony\TracingBundle\Application\Port\OutgoingSpanReservationInterface;
use Msstc4Symfony\TracingBundle\Application\Port\TraceContextHolderInterface;
use Msstc4Symfony\TracingBundle\Domain\ValueObject\TraceContext;
use Override;
use Symfony\Contracts\Service\ResetInterface;
use WeakMap;

/**
 * Runs every recorded profiling span, except the unit-of-work ones, in a child span of the current trace.
 *
 * When a span ends, the trace is walked back through the spans already ended — to the first one still open, or to
 * the trace around them — but only from a trace this processor installed: tracing-bundle replaces the trace on its
 * own unit boundaries, and ProfilingFactory ends open children only after their parent's end handlers ran.
 *
 * A detached span stays outside the trace, except one of an outgoing HTTP request: it reserves a child span for the
 * request's traceparent (tracing-bundle's HTTP integrations run inside profiling's) and carries its ids.
 *
 * @internal
 */
final class TraceSpanProcessor implements CreateSpanProcessorInterface, ResetInterface
{
    /** @var WeakMap<TraceContext, TraceContext> the trace around each open span this processor opened */
    private WeakMap $open;

    /** @var WeakMap<TraceContext, TraceContext> the same for the spans that ended */
    private WeakMap $ended;

    /**
     * @param list<non-empty-string> $unitSpanPrefixes messages of spans that already run in the trace of their unit of work
     */
    public function __construct(
        private readonly TraceContextHolderInterface $holder,
        private readonly array $unitSpanPrefixes,
        private readonly ?OutgoingSpanReservationInterface $reservation = null,
    ) {
        $this->open = new WeakMap();
        $this->ended = new WeakMap();
    }

    #[Override]
    public function process(SpanInterface $span): SpanInterface
    {
        if (!$span->isRecorded() || $this->isUnitSpan($span->getMessage())) {
            return $span;
        }

        // A detached span ends after spans opened meanwhile: as the current trace span it would stay over them.
        if ($this->carries($span, DetachedSpanFactoryInterface::DETACHED)) {
            $reservation = $this->reservation;
            if ($reservation instanceof OutgoingSpanReservationInterface && $this->isOutgoingRequest($span)) {
                $reserved = $this->reserve($reservation);
                // A request that never passed tracing-bundle's integrations leaves the reservation behind; a request
                // takes its own synchronously, so a slot still full when one ends holds no request's reservation.
                $span->addEndHandler(static function () use ($reservation): void {
                    $reservation->reset();
                });

                return new TracedSpan($span, $reserved);
            }

            return $span;
        }

        $outer = $this->firstOpen($this->holder->current());
        $child = $outer->withChildSpan();
        $span->addEndHandler(function () use ($child): void {
            $this->close($child);
        });
        $this->open[$child] = $outer;
        $this->holder->replace($child);

        return new TracedSpan($span, $child);
    }

    #[Override]
    public function reset(): void
    {
        $this->open = new WeakMap();
        $this->ended = new WeakMap();
    }

    private function close(TraceContext $child): void
    {
        if (!isset($this->open[$child])) {
            return;
        }

        $this->ended[$child] = $this->open[$child];
        unset($this->open[$child]);
        $current = $this->holder->current();
        $restored = $this->firstOpen($current);
        if ($restored !== $current) {
            $this->holder->replace($restored);
        }
    }

    /**
     * The trace itself unless it is one of an ended span of this processor — then the trace around the first span
     * still open, or around all of them. A nested unit of work may have put such a trace back in its finally.
     */
    private function firstOpen(TraceContext $trace): TraceContext
    {
        while (isset($this->ended[$trace])) {
            $trace = $this->ended[$trace];
        }

        return $trace;
    }

    /**
     * @param array<string, true> $marker
     */
    private function carries(SpanInterface $span, array $marker): bool
    {
        $context = $span->getContext();
        foreach ($marker as $key => $value) {
            if (($context[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    private function isOutgoingRequest(SpanInterface $span): bool
    {
        return $this->carries($span, DetachedSpanFactoryInterface::OUTGOING_REQUEST);
    }

    /**
     * Reserved from the trace a stacked span would open under: a nested unit of work may have put back the trace of a
     * span that already ended.
     */
    private function reserve(OutgoingSpanReservationInterface $reservation): TraceContext
    {
        $current = $this->holder->current();
        $open = $this->firstOpen($current);
        if ($open !== $current) {
            $this->holder->replace($open);
        }

        return $reservation->reserve();
    }

    private function isUnitSpan(string $message): bool
    {
        foreach ($this->unitSpanPrefixes as $prefix) {
            if (str_starts_with($message, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
