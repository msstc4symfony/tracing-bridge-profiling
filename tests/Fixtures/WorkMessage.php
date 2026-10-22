<?php

declare(strict_types=1);

namespace Msstc4Symfony\TracingBridgeProfiling\Test\Fixtures;

final readonly class WorkMessage
{
    public function __construct(
        public int $number,
    ) {
    }
}
