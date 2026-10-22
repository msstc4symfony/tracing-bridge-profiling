<?php

declare(strict_types=1);

namespace Msstc4Symfony\TracingBridgeProfiling\Test\Unit;

use Msstc4Symfony\TracingBridgeProfiling\Test\Fixtures\ExceptionHandlerSnapshot;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversNothing]
final class ExceptionHandlerSnapshotTest extends TestCase
{
    public function testRemovesHandlersPushedAfterTheSnapshot(): void
    {
        $outer = ExceptionHandlerSnapshot::current();
        $snapshot = ExceptionHandlerSnapshot::take();
        set_exception_handler(static function (Throwable $e): void {});
        set_exception_handler(static function (Throwable $e): void {});

        $snapshot->restore();

        self::assertSame($outer, ExceptionHandlerSnapshot::current());
    }

    public function testLeavesTheStackIntactWhenTheSnapshotHandlerIsGone(): void
    {
        $outer = ExceptionHandlerSnapshot::current();
        set_exception_handler(static function (Throwable $e): void {});
        $snapshot = ExceptionHandlerSnapshot::take();
        restore_exception_handler();
        $foreign = static function (Throwable $e): void {};
        set_exception_handler($foreign);

        $snapshot->restore();

        $current = ExceptionHandlerSnapshot::current();
        restore_exception_handler();
        self::assertSame($foreign, $current);
        self::assertSame($outer, ExceptionHandlerSnapshot::current());
    }
}
