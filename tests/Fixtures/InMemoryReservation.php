<?php

declare(strict_types=1);

namespace Msstc4Symfony\TracingBridgeProfiling\Test\Fixtures;

use Msstc4Symfony\TracingBundle\Application\Port\OutgoingSpanReservationInterface;
use Msstc4Symfony\TracingBundle\Application\Port\TraceContextHolderInterface;
use Msstc4Symfony\TracingBundle\Domain\ValueObject\TraceContext;
use Override;

final class InMemoryReservation implements OutgoingSpanReservationInterface
{
    public int $resets = 0;

    private ?TraceContext $reserved = null;

    private ?TraceContext $reservedFrom = null;

    public function __construct(
        private readonly TraceContextHolderInterface $holder,
    ) {
    }

    public function reserved(): ?TraceContext
    {
        return $this->reserved;
    }

    #[Override]
    public function reserve(): TraceContext
    {
        $this->reservedFrom = $this->holder->current();

        return $this->reserved = $this->reservedFrom->withChildSpan();
    }

    #[Override]
    public function take(): ?TraceContext
    {
        $reserved = $this->reservedFrom === $this->holder->current() ? $this->reserved : null;
        $this->reserved = null;

        $this->reservedFrom = null;

        return $reserved;
    }

    #[Override]
    public function reset(): void
    {
        $this->resets++;
        $this->reserved = null;
        $this->reservedFrom = null;
    }
}
