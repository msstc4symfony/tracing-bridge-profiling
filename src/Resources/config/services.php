<?php

declare(strict_types=1);

use Msstc4Symfony\TracingBridgeProfiling\Infrastructure\Tracing\TraceSpanProcessor;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->defaults()->autowire()->autoconfigure();

    $services->set(TraceSpanProcessor::class)
        ->arg('$unitSpanPrefixes', param('msstc4symfony_tracing_bridge_profiling.unit_span_prefixes'))
    ;
};
