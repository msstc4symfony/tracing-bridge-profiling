<?php

declare(strict_types=1);

namespace Msstc4Symfony\TracingBridgeProfiling\Test\Unit;

use Msstc4Symfony\TracingBridgeProfiling\TracingBridgeProfilingBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;

#[CoversClass(TracingBridgeProfilingBundle::class)]
final class TracingBridgeProfilingBundleTest extends TestCase
{
    public function testExtensionAliasFollowsTheFamilyNaming(): void
    {
        self::assertSame('msstc4symfony_tracing_bridge_profiling', (new TracingBridgeProfilingBundle())->getContainerExtension()?->getAlias());
    }

    public function testNoExtraPrefixesByDefault(): void
    {
        self::assertSame(['extra_unit_span_prefixes' => []], self::process([]));
    }

    public function testANonStringPrefixIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        self::process(['extra_unit_span_prefixes' => [123]]);
    }

    public function testAnEmptyPrefixIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        self::process(['extra_unit_span_prefixes' => ['']]);
    }

    /**
     * @param array<string, list<int|string>> $config
     *
     * @return array<array-key, mixed>
     */
    private function process(array $config): array
    {
        $extension = (new TracingBridgeProfilingBundle())->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        return (new Processor())->processConfiguration($configuration, [$config]);
    }
}
