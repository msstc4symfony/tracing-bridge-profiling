<?php

declare(strict_types=1);

namespace Msstc4Symfony\TracingBridgeProfiling\Test\Integration;

use Msstc4Symfony\TracingBridgeProfiling\TracingBridgeProfilingBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

/**
 * The extension reads kernel.bundles to decide on the Action prefix; loading it runs services.php, hence not a unit test.
 */
#[CoversNothing]
final class BundleExtensionTest extends TestCase
{
    /**
     * @param array<string, string>|null $bundles null leaves kernel.bundles unset
     * @param list<string> $expected
     */
    #[DataProvider('kernels')]
    public function testTheUnitSpanPrefixesFollowTheRegisteredBundles(?array $bundles, array $expected): void
    {
        $container = new ContainerBuilder();
        if ($bundles !== null) {
            $container->setParameter('kernel.bundles', $bundles);
        }
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());

        $extension = (new TracingBridgeProfilingBundle())->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        $extension->load([['extra_unit_span_prefixes' => ['batch ']]], $container);

        self::assertSame($expected, $container->getParameter('msstc4symfony_tracing_bridge_profiling.unit_span_prefixes'));
    }

    /**
     * @return iterable<string, array{array<string, string>|null, list<string>}>
     */
    public static function kernels(): iterable
    {
        yield 'with tracing-bridge-logic' => [
            ['TracingBridgeLogicBundle' => 'Msstc4Symfony\\TracingBridgeLogic\\TracingBridgeLogicBundle'],
            ['request ', 'cli command ', 'message ', 'action ', 'batch '],
        ];
        yield 'without it' => [['FrameworkBundle' => FrameworkBundle::class], ['request ', 'cli command ', 'message ', 'batch ']];
        yield 'no kernel.bundles' => [null, ['request ', 'cli command ', 'message ', 'batch ']];
    }
}
