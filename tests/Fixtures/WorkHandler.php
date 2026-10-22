<?php

declare(strict_types=1);

namespace Msstc4Symfony\TracingBridgeProfiling\Test\Fixtures;

use Msstc4Symfony\ProfilingBundle\Application\ProfilingFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class WorkHandler
{
    public function __construct(
        private ProfilingFactoryInterface $profiling,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(WorkMessage $message): void
    {
        $span = $this->profiling->createSpan('sql work');
        $this->logger->info('Handled ' . $message->number);
        $this->profiling->endSpan($span);
    }
}
