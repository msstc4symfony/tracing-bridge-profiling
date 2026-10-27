<?php

declare(strict_types=1);

namespace Msstc4Symfony\TracingBridgeProfiling\Test\Fixtures;

use Msstc4Symfony\TracingBundle\Application\Port\OutgoingSpanReservationInterface;
use Msstc4Symfony\TracingBundle\Application\Port\TraceContextHolderInterface;
use Msstc4Symfony\TracingBundle\Domain\ValueObject\TraceContext;
use Override;

final class InMemoryReservation implements OutgoingSpanReservationInterface
{
    private ?TraceContext $reserved = null;

    public function __construct(
        private readonly TraceContextHolderInterface $holder,
    ) {
    }

    /**
     * The reservation not taken yet. Unlike tracing-bundle's, take() here does not check the trace.
     */
    public function reserved(): ?TraceContext
    {
        return $this->reserved;
    }

    #[Override]
    public function reserve(): TraceContext
    {
        return $this->reserved = $this->holder->current()->withChildSpan();
    }

    #[Override]
    public function take(): ?TraceContext
    {
        $reserved = $this->reserved;
        $this->reserved = null;

        return $reserved;
    }

    #[Override]
    public function reset(): void
    {
        $this->reserved = null;
    }
}
