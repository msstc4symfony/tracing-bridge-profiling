<?php

declare(strict_types=1);

namespace Msstc4Symfony\TracingBridgeProfiling\Infrastructure\Tracing;

/**
 * The context keys a traced span adds to its profiling record.
 */
final class TraceSpanKeys
{
    public const SPAN_ID = 'trace_span_id';

    public const PARENT_SPAN_ID = 'trace_parent_span_id';

    private function __construct()
    {
    }
}
