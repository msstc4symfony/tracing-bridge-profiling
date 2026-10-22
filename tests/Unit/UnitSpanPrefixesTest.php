<?php

declare(strict_types=1);

namespace Msstc4Symfony\TracingBridgeProfiling\Test\Unit;

use Msstc4Symfony\TracingBridgeProfiling\DependencyInjection\UnitSpanPrefixes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

#[CoversClass(UnitSpanPrefixes::class)]
final class UnitSpanPrefixesTest extends TestCase
{
    public function testProfilingsUnitSpansAreAlwaysLeftAlone(): void
    {
        self::assertSame(['request ', 'cli command ', 'message '], UnitSpanPrefixes::of(['FrameworkBundle' => FrameworkBundle::class], []));
    }

    public function testActionSpansAreLeftAloneWhenTracingBridgeLogicGivesThemASpan(): void
    {
        self::assertSame(
            ['request ', 'cli command ', 'message ', 'action '],
            UnitSpanPrefixes::of(['TracingBridgeLogicBundle' => 'Msstc4Symfony\\TracingBridgeLogic\\TracingBridgeLogicBundle'], []),
        );
    }

    public function testExtraPrefixesComeAfterTheBuiltInOnes(): void
    {
        self::assertSame(['request ', 'cli command ', 'message ', 'batch '], UnitSpanPrefixes::of([], ['batch ']));
    }
}
