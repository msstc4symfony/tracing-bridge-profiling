<?php

declare(strict_types=1);

namespace Msstc4Symfony\TracingBridgeProfiling\Test\Fixtures;

use Msstc4Symfony\ProfilingBundle\Application\Span\SpanInterface;
use Override;

/**
 * A span of another library: SpanInterface only, no EndableAtInterface.
 */
final class NoEndTimeSpan implements SpanInterface
{
    /** @var list<array<string, mixed>> the context of every end() call */
    public array $ends = [];

    #[Override]
    public function end(array $context = []): void
    {
        $this->ends[] = $context;
    }

    #[Override]
    public function isEnded(): bool
    {
        return $this->ends !== [];
    }

    #[Override]
    public function getDuration(): float
    {
        return 0.0;
    }

    #[Override]
    public function getMessage(): string
    {
        return 'external';
    }

    #[Override]
    public function getContext(): array
    {
        return [];
    }

    #[Override]
    public function getStartTime(): float
    {
        return 0.0;
    }

    #[Override]
    public function getParentSpan(): ?SpanInterface
    {
        return null;
    }

    #[Override]
    public function setParentSpan(?SpanInterface $parentSpan): static
    {
        return $this;
    }

    #[Override]
    public function getEndHandlers(): array
    {
        return [];
    }

    #[Override]
    public function addEndHandler(callable $endHandler): static
    {
        return $this;
    }

    #[Override]
    public function removeEndHandler(callable|int $endHandler): static
    {
        return $this;
    }

    #[Override]
    public function isRecorded(): bool
    {
        return true;
    }
}
