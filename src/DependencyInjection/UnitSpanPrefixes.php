<?php

declare(strict_types=1);

namespace Msstc4Symfony\TracingBridgeProfiling\DependencyInjection;

/**
 * @internal
 */
final class UnitSpanPrefixes
{
    /**
     * The spans profiling-bundle opens for a request, command and message: tracing-bundle already gives each unit of
     * work its span, and the command and message spans open before tracing-bundle switches to the unit's trace.
     */
    private const PROFILING = ['request ', 'cli command ', 'message '];

    /** The Action span of profiling-bridge-logic, which tracing-bridge-logic already runs in its own trace span. */
    private const ACTION = 'action ';

    private const TRACING_BRIDGE_LOGIC_BUNDLE = 'Msstc4Symfony\TracingBridgeLogic\TracingBridgeLogicBundle';

    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $bundles the kernel.bundles parameter
     * @param list<non-empty-string> $extra
     *
     * @return list<non-empty-string>
     */
    public static function of(array $bundles, array $extra): array
    {
        $prefixes = self::PROFILING;
        if (in_array(self::TRACING_BRIDGE_LOGIC_BUNDLE, $bundles, true)) {
            $prefixes[] = self::ACTION;
        }

        return [...$prefixes, ...$extra];
    }
}
