<?php

declare(strict_types=1);

namespace Msstc4Symfony\TracingBridgeProfiling;

use Msstc4Symfony\TracingBridgeProfiling\DependencyInjection\UnitSpanPrefixes;
use Override;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class TracingBridgeProfilingBundle extends AbstractBundle
{
    protected string $extensionAlias = 'msstc4symfony_tracing_bridge_profiling';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('extra_unit_span_prefixes')
                    ->info('Further span message prefixes that keep the trace span of their unit of work instead of opening a child.')
                    ->stringPrototype()->cannotBeEmpty()->end()
                    ->defaultValue([])
                ->end()
            ->end()
        ;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $bundles = $builder->hasParameter('kernel.bundles') ? $builder->getParameter('kernel.bundles') : [];
        $configured = $config['extra_unit_span_prefixes'];
        $extra = array_values(array_filter(is_array($configured) ? $configured : [], static fn (mixed $prefix): bool => is_string($prefix) && $prefix !== ''));
        $container->parameters()->set('msstc4symfony_tracing_bridge_profiling.unit_span_prefixes', UnitSpanPrefixes::of(is_array($bundles) ? $bundles : [], $extra));
        $container->import(__DIR__ . '/Resources/config/services.php');
    }
}
