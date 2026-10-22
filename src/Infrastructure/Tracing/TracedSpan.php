<?php

declare(strict_types=1);

namespace Msstc4Symfony\TracingBridgeProfiling\Infrastructure\Tracing;

use Msstc4Symfony\ProfilingBundle\Application\Span\EndableAtInterface;
use Msstc4Symfony\ProfilingBundle\Application\Span\SpanInterface;
use Msstc4Symfony\TracingBundle\Domain\ValueObject\TraceContext;
use Override;

/**
 * Carries the trace span ids in the span context: end processors such as the profiling log run after the trace is
 * restored, so the log record's own trace fields name the outer span. End handlers still receive the wrapped span.
 *
 * @internal
 */
final readonly class TracedSpan implements SpanInterface, EndableAtInterface
{
    public function __construct(
        private SpanInterface $inner,
        private TraceContext $trace,
    ) {
    }

    #[Override]
    public function end(array $context = []): void
    {
        $this->inner->end($context);
    }

    #[Override]
    public function endAt(int $endedAt, array $context = []): void
    {
        $this->inner instanceof EndableAtInterface ? $this->inner->endAt($endedAt, $context) : $this->inner->end($context);
    }

    #[Override]
    public function getEndedAt(): ?int
    {
        return $this->inner instanceof EndableAtInterface ? $this->inner->getEndedAt() : null;
    }

    #[Override]
    public function isEnded(): bool
    {
        return $this->inner->isEnded();
    }

    #[Override]
    public function getDuration(): float
    {
        return $this->inner->getDuration();
    }

    #[Override]
    public function getMessage(): string
    {
        return $this->inner->getMessage();
    }

    #[Override]
    public function getContext(): array
    {
        return [
            ...$this->inner->getContext(),
            TraceSpanKeys::SPAN_ID => $this->trace->traceParent->spanId,
            TraceSpanKeys::PARENT_SPAN_ID => $this->trace->parentSpanId,
        ];
    }

    #[Override]
    public function getStartTime(): float
    {
        return $this->inner->getStartTime();
    }

    #[Override]
    public function getParentSpan(): ?SpanInterface
    {
        return $this->inner->getParentSpan();
    }

    #[Override]
    public function setParentSpan(?SpanInterface $parentSpan): static
    {
        $this->inner->setParentSpan($parentSpan);

        return $this;
    }

    #[Override]
    public function getEndHandlers(): array
    {
        return $this->inner->getEndHandlers();
    }

    #[Override]
    public function addEndHandler(callable $endHandler): static
    {
        $this->inner->addEndHandler($endHandler);

        return $this;
    }

    #[Override]
    public function removeEndHandler(callable|int $endHandler): static
    {
        $this->inner->removeEndHandler($endHandler);

        return $this;
    }

    #[Override]
    public function isRecorded(): bool
    {
        return $this->inner->isRecorded();
    }
}
