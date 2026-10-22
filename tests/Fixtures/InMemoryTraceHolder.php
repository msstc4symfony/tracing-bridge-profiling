<?php

declare(strict_types=1);

namespace Msstc4Symfony\TracingBridgeProfiling\Test\Fixtures;

use Msstc4Symfony\TracingBundle\Application\Port\TraceContextHolderInterface;
use Msstc4Symfony\TracingBundle\Domain\ValueObject\TraceContext;
use Override;

final class InMemoryTraceHolder implements TraceContextHolderInterface
{
    private TraceContext $context;

    public function __construct(?TraceContext $context = null)
    {
        $this->context = $context ?? TraceContext::start();
    }

    #[Override]
    public function current(): TraceContext
    {
        return $this->context;
    }

    #[Override]
    public function replace(TraceContext $context): void
    {
        $this->context = $context;
    }

    #[Override]
    public function reset(): void
    {
        $this->context = TraceContext::start();
    }
}
