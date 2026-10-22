<?php

declare(strict_types=1);

namespace Msstc4Symfony\TracingBridgeProfiling\Infrastructure\Tracing;

use Msstc4Symfony\ProfilingBundle\Application\Processor\CreateSpan\CreateSpanProcessorInterface;
use Msstc4Symfony\ProfilingBundle\Application\Span\SpanInterface;
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
