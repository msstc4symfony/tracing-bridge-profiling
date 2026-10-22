<?php

declare(strict_types=1);

namespace Msstc4Symfony\TracingBridgeProfiling\Test\Integration\Kernel;

use Msstc4Symfony\ProfilingBundle\ProfilingBundle;
use Msstc4Symfony\TracingBridgeProfiling\Test\Fixtures\CollectingHandler;
use Msstc4Symfony\TracingBridgeProfiling\Test\Fixtures\WorkHandler;
use Msstc4Symfony\TracingBridgeProfiling\Test\Fixtures\WorkMessage;
use Msstc4Symfony\TracingBridgeProfiling\TracingBridgeProfilingBundle;
use Msstc4Symfony\TracingBundle\TracingBundle;
use Override;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, list<string>> $bridge the msstc4symfony_tracing_bridge_profiling configuration
     */
    public function __construct(
        string $environment,
        bool $debug,
        private readonly array $bridge,
    ) {
        parent::__construct($environment, $debug);
    }

    // Per process: parallel PHPUnit runs must not wipe each other's container.
    public static function cacheRoot(): string
    {
        return sys_get_temp_dir() . '/msstc4symfony-tracing-bridge-profiling-test-' . getmypid();
    }

    #[Override]
    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new MonologBundle(), new TracingBundle(), new ProfilingBundle(), new TracingBridgeProfilingBundle()];
    }

    #[Override]
    public function getCacheDir(): string
    {
        return self::cacheRoot() . '/cache/' . $this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return self::cacheRoot() . '/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'http_method_override' => false,
            'test' => true,
            // The php_errors logger installs a global handler that outlives the kernel and trips failOnRisky.
            'php_errors' => ['log' => false],
            'messenger' => [
                'transports' => ['async' => 'in-memory://'],
                'routing' => [WorkMessage::class => 'async'],
            ],
        ]);
        $container->extension('msstc4symfony_profiling', ['messages' => [WorkMessage::class]]);
        $container->services()->set(WorkHandler::class)->autowire()->autoconfigure();
        $container->services()->set(CollectingHandler::class)->public();
        $container->extension('monolog', ['handlers' => [
            'main' => ['type' => 'test'],
            'collecting' => ['type' => 'service', 'id' => CollectingHandler::class],
        ]]);
        $container->extension('msstc4symfony_tracing_bridge_profiling', $this->bridge);
    }
}
