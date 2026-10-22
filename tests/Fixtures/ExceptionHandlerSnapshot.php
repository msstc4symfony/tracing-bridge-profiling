<?php

declare(strict_types=1);

namespace Msstc4Symfony\TracingBridgeProfiling\Test\Fixtures;

use Throwable;

/**
 * @see \Msstc4Symfony\TracingBridgeProfiling\Test\Unit\ExceptionHandlerSnapshotTest
 */
final readonly class ExceptionHandlerSnapshot
{
    /**
     * @param (callable(Throwable): void)|null $handler
     */
    private function __construct(
        private mixed $handler,
    ) {
    }

    public static function take(): self
    {
        return new self(self::current());
    }

    public function restore(): void
    {
        $removed = [];
        $current = self::current();
        while ($current !== null && $current !== $this->handler) {
            $removed[] = $current;
            restore_exception_handler();
            $current = self::current();
        }

        // The snapshot handler is no longer on the stack: put back everything removed
        // instead of stripping handlers this test never installed (e.g. PHPUnit's own).
        if ($current === null && $this->handler !== null) {
            foreach (array_reverse($removed) as $handler) {
                set_exception_handler($handler);
            }
        }
    }

    /**
     * @return (callable(Throwable): void)|null
     */
    public static function current(): ?callable
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }
}
