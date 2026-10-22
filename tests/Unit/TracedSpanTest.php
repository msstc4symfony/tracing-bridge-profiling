<?php

declare(strict_types=1);

namespace Msstc4Symfony\TracingBridgeProfiling\Test\Unit;

use Msstc4Symfony\ProfilingBundle\Application\Span\Span;
use Msstc4Symfony\TracingBridgeProfiling\Infrastructure\Tracing\TracedSpan;
use Msstc4Symfony\TracingBridgeProfiling\Infrastructure\Tracing\TraceSpanKeys;
use Msstc4Symfony\TracingBridgeProfiling\Test\Fixtures\NoEndTimeSpan;
use Msstc4Symfony\TracingBundle\Domain\ValueObject\TraceContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TracedSpan::class)]
#[CoversClass(TraceSpanKeys::class)]
final class TracedSpanTest extends TestCase
{
    public function testDelegatesEverythingButTheContextToTheSpanItWraps(): void
    {
        $inner = new Span('sql select', ['table' => 'orders']);
        $parent = new Span('import');
        $traced = new TracedSpan($inner, TraceContext::start()->withChildSpan());
        $calls = [];
        $handler = static function () use (&$calls): void {
            $calls[] = 'handler';
        };

        self::assertSame($traced, $traced->setParentSpan($parent));
        self::assertSame($parent, $traced->getParentSpan());
        self::assertSame($parent, $inner->getParentSpan());
        self::assertSame($traced, $traced->addEndHandler($handler));
        self::assertSame([$handler], $traced->getEndHandlers());
        self::assertSame($inner->getStartTime(), $traced->getStartTime());
        self::assertSame('sql select', $traced->getMessage());
        self::assertTrue($traced->isRecorded());
        self::assertFalse($traced->isEnded());

        $traced->end(['rows' => 3]);

        self::assertTrue($inner->isEnded());
        self::assertTrue($traced->isEnded());
        self::assertSame(['handler'], $calls);
        self::assertSame($inner->getDuration(), $traced->getDuration());
        self::assertSame($traced, $traced->removeEndHandler(0));
        self::assertSame([], $inner->getEndHandlers());
    }

    public function testEndsAtTheGivenInstantThroughTheSpanItWraps(): void
    {
        $inner = new Span('sql select');
        $traced = new TracedSpan($inner, TraceContext::start()->withChildSpan());
        $at = hrtime(true) + 1_000_000;

        $traced->endAt($at);

        self::assertSame($at, $inner->getEndedAt());
        self::assertSame($at, $traced->getEndedAt());
    }

    public function testAWrappedSpanWithoutAnEndTimeIsEndedNow(): void
    {
        $inner = new NoEndTimeSpan();
        $traced = new TracedSpan($inner, TraceContext::start()->withChildSpan());

        $traced->endAt(hrtime(true), ['rows' => 1]);

        self::assertSame([['rows' => 1]], $inner->ends);
        self::assertNull($traced->getEndedAt());
    }

    public function testAddsTheSpanIdsToTheContext(): void
    {
        $outer = TraceContext::start();
        $child = $outer->withChildSpan();

        $context = (new TracedSpan(new Span('sql select', ['table' => 'orders']), $child))->getContext();

        self::assertSame(['table' => 'orders', TraceSpanKeys::SPAN_ID => $child->traceParent->spanId, TraceSpanKeys::PARENT_SPAN_ID => $outer->traceParent->spanId], $context);
    }
}
