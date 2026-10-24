<?php

declare(strict_types=1);

namespace Msstc4Symfony\TracingBridgeProfiling\Test\Unit;

use Msstc4Symfony\ProfilingBundle\Application\Assembler\SpanAssembler;
use Msstc4Symfony\ProfilingBundle\Application\DetachedSpanFactoryInterface;
use Msstc4Symfony\ProfilingBundle\Application\Processor\CreateSpan\CreateSpanProcessorInterface;
use Msstc4Symfony\ProfilingBundle\Application\ProfilingFactory;
use Msstc4Symfony\ProfilingBundle\Application\Span\NullSpan;
use Msstc4Symfony\ProfilingBundle\Application\Span\Span;
use Msstc4Symfony\ProfilingBundle\Application\Span\SpanInterface;
use Msstc4Symfony\TracingBridgeProfiling\Infrastructure\Tracing\TracedSpan;
use Msstc4Symfony\TracingBridgeProfiling\Infrastructure\Tracing\TraceSpanKeys;
use Msstc4Symfony\TracingBridgeProfiling\Infrastructure\Tracing\TraceSpanProcessor;
use Msstc4Symfony\TracingBridgeProfiling\Test\Fixtures\InMemoryTraceHolder;
use Msstc4Symfony\TracingBundle\Domain\ValueObject\TraceContext;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionProperty;
use RuntimeException;
use WeakMap;

#[CoversClass(TraceSpanProcessor::class)]
#[UsesClass(TracedSpan::class)]
#[UsesClass(TraceSpanKeys::class)]
final class TraceSpanProcessorTest extends TestCase
{
    public function testARecordedSpanRunsInAChildSpanOfTheTrace(): void
    {
        $holder = new InMemoryTraceHolder();
        $outer = $holder->current();

        $span = $this->processor($holder)->process(new Span('sql select'));

        $inside = $holder->current();
        self::assertNotSame($outer, $inside);
        self::assertSame($outer->traceParent->traceId, $inside->traceParent->traceId);
        self::assertSame($outer->traceParent->spanId, $inside->parentSpanId);
        self::assertSame($outer->runtimeId, $inside->runtimeId);
        self::assertSame(
            [TraceSpanKeys::SPAN_ID => $inside->traceParent->spanId, TraceSpanKeys::PARENT_SPAN_ID => $outer->traceParent->spanId],
            array_intersect_key($span->getContext(), [TraceSpanKeys::SPAN_ID => true, TraceSpanKeys::PARENT_SPAN_ID => true]),
        );
    }

    public function testTheTraceIsRestoredWhenTheSpanEnds(): void
    {
        $holder = new InMemoryTraceHolder();
        $outer = $holder->current();

        $this->processor($holder)->process(new Span('sql select'))->end();

        self::assertSame($outer, $holder->current());
    }

    public function testTheSpanKeepsItsOwnContextAndMessage(): void
    {
        $span = $this->processor(new InMemoryTraceHolder())->process(new Span('sql select', ['table' => 'orders']));

        self::assertSame('sql select', $span->getMessage());
        self::assertSame('orders', $span->getContext()['table']);
        self::assertTrue($span->isRecorded());
    }

    public function testNestedSpansFormAChainOfChildSpans(): void
    {
        $holder = new InMemoryTraceHolder();
        $processor = $this->processor($holder);
        $outer = $holder->current();

        $parent = $processor->process(new Span('import'));
        $parentContext = $holder->current();
        $child = $processor->process(new Span('sql insert'));

        self::assertSame($parentContext->traceParent->spanId, $holder->current()->parentSpanId);
        $child->end();
        self::assertSame($parentContext, $holder->current());
        $parent->end();
        self::assertSame($outer, $holder->current());
    }

    public function testEndingAParentFirstRestoresItsOuterTraceOnceTheFactoryEndsTheChild(): void
    {
        $holder = new InMemoryTraceHolder();
        $factory = $this->factory($holder);
        $outer = $holder->current();

        $parent = $factory->createSpan('import');
        $child = $factory->createSpan('sql insert');
        $factory->endSpan($parent);

        self::assertTrue($child->isEnded());
        self::assertSame($outer, $holder->current());
    }

    public function testAChildEndsAtItsParentsEndTimeThroughTheWrapper(): void
    {
        $factory = $this->factory(new InMemoryTraceHolder());
        $parent = $factory->createSpan('import');
        $child = $factory->createSpan('sql insert');
        $factory->createSpan('sql select')->addEndHandler(static function (): void {
            usleep(20_000);
        });

        $factory->endSpan($parent);

        self::assertLessThanOrEqual($parent->getDuration(), $child->getDuration());
    }

    public function testASpanClosedInsideANestedUnitOfWorkDoesNotStrandTheTrace(): void
    {
        $holder = new InMemoryTraceHolder();
        $processor = $this->processor($holder);
        $unit = $holder->current();
        $outerSpan = $processor->process(new Span('import'));
        $innerSpan = $processor->process(new Span('batch'));
        $beforeNested = $holder->current();

        // A nested sync message or Action: it opens its own span and puts the trace back in finally.
        $holder->replace($beforeNested->withChildSpan());
        $innerSpan->end();
        $holder->replace($beforeNested);
        $outerSpan->end();

        self::assertSame($unit, $holder->current());
    }

    public function testTheNextSpanDoesNotBecomeAChildOfASpanEndedInsideANestedUnitOfWork(): void
    {
        $holder = new InMemoryTraceHolder();
        $processor = $this->processor($holder);
        $unit = $holder->current();
        $span = $processor->process(new Span('import'));
        $inSpan = $holder->current();

        $holder->replace($inSpan->withChildSpan());
        $span->end();
        $holder->replace($inSpan);
        $processor->process(new Span('sql select'));

        self::assertSame($unit->traceParent->spanId, $holder->current()->parentSpanId);
    }

    public function testACreateProcessorFailingAfterThisOneLeavesTheSpanTraced(): void
    {
        $holder = new InMemoryTraceHolder();
        $failing = new class implements CreateSpanProcessorInterface {
            #[Override]
            public function process(SpanInterface $span): SpanInterface
            {
                throw new RuntimeException('processor bug');
            }
        };
        $factory = new ProfilingFactory([new SpanAssembler([])], [$this->processor($holder), $failing], [], new NullLogger());
        $outer = $holder->current();

        $span = $factory->createSpan('sql select');

        self::assertArrayHasKey(TraceSpanKeys::SPAN_ID, $span->getContext());
        $factory->endSpan($span);
        self::assertSame($outer, $holder->current());
    }

    /**
     * @param bool $holderFirst whether kernel.reset reaches the trace holder before the profiling factory
     */
    #[DataProvider('resetOrders')]
    public function testAResetInEitherOrderLeavesAFreshTrace(bool $holderFirst): void
    {
        $holder = new InMemoryTraceHolder();
        $processor = $this->processor($holder);
        $factory = new ProfilingFactory([new SpanAssembler([])], [$processor], [], new NullLogger());
        $unit = $holder->current();
        $factory->createSpan('sql select');

        if ($holderFirst) {
            $holder->reset();
            $fresh = $holder->current();
            $factory->reset();
        } else {
            $factory->reset();
            self::assertSame($unit->traceParent->spanId, $holder->current()->traceParent->spanId);
            $holder->reset();
            $fresh = $holder->current();
        }
        $processor->reset();

        self::assertSame($fresh, $holder->current());
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function resetOrders(): iterable
    {
        yield 'trace holder first' => [true];
        yield 'profiling factory first' => [false];
    }

    public function testASpanInsideAKeptCommandSpanEndsOnResetAndRestoresTheCommandsTrace(): void
    {
        $holder = new InMemoryTraceHolder();
        $factory = $this->factory($holder);
        $command = $factory->createSpan('cli command app:import');
        $factory->keepOpenOnReset($command);
        $commandTrace = $holder->current();
        $factory->createSpan('sql insert');

        $factory->reset();

        self::assertFalse($command->isEnded());
        self::assertSame($commandTrace, $holder->current());
    }

    public function testATraceTheTracingBundleReplacedMeanwhileIsLeftAlone(): void
    {
        $holder = new InMemoryTraceHolder();
        $span = $this->processor($holder)->process(new Span('sql select'));
        $next = TraceContext::start();
        $holder->replace($next);

        $span->end();

        self::assertSame($next, $holder->current());
    }

    public function testATraceOfAnOuterSpanIsNotReplacedByAnInnerSpanEnding(): void
    {
        $holder = new InMemoryTraceHolder();
        $processor = $this->processor($holder);
        $processor->process(new Span('import'));

        $outerSpan = $holder->current();
        $processor->process(new Span('batch'));
        $innermost = $processor->process(new Span('sql insert'));
        $holder->replace($outerSpan);

        $innermost->end();

        self::assertSame($outerSpan->traceParent->spanId, $holder->current()->traceParent->spanId);
    }

    public function testEndedSpansLeaveNothingBehind(): void
    {
        $holder = new InMemoryTraceHolder();
        $processor = $this->processor($holder);

        for ($i = 0; $i < 3; $i++) {
            $parent = $processor->process(new Span('import'));
            $processor->process(new Span('sql insert'))->end();
            $parent->end();
        }
        unset($parent);
        gc_collect_cycles();

        // A worker runs for days: what the processor remembers must not grow with every message.
        foreach (['open', 'ended'] as $property) {
            $remembered = (new ReflectionProperty($processor, $property))->getValue($processor);
            self::assertInstanceOf(WeakMap::class, $remembered);
            self::assertCount(0, $remembered, $property);
        }
    }

    public function testADetachedSpanOpensNoTraceSpan(): void
    {
        $holder = new InMemoryTraceHolder();
        $outer = $holder->current();
        $detached = new Span('http GET api.example.com', DetachedSpanFactoryInterface::DETACHED);

        self::assertSame($detached, $this->processor($holder)->process($detached));
        self::assertSame($outer, $holder->current());
    }

    public function testASpanOfTheDetachedFactoryOpensNoTraceSpan(): void
    {
        $holder = new InMemoryTraceHolder();
        $outer = $holder->current();

        $span = $this->factory($holder)->createDetachedSpan('http GET api.example.com');

        self::assertArrayNotHasKey(TraceSpanKeys::SPAN_ID, $span->getContext());
        self::assertSame($outer, $holder->current());
    }

    #[DataProvider('notDetachedMarkers')]
    public function testOnlyATrueMarkerDetachesASpan(mixed $marker): void
    {
        $holder = new InMemoryTraceHolder();
        $outer = $holder->current();

        $context = array_map(static fn (): mixed => $marker, DetachedSpanFactoryInterface::DETACHED);
        $span = $this->processor($holder)->process(new Span('sql select', $context));

        self::assertInstanceOf(TracedSpan::class, $span);
        self::assertNotSame($outer, $holder->current());
    }

    /**
     * @return iterable<string, array{bool|int|string}>
     */
    public static function notDetachedMarkers(): iterable
    {
        yield 'false' => [false];
        yield 'one' => [1];
        yield 'string true' => ['true'];
    }

    public function testAFilteredSpanOpensNoTraceSpan(): void
    {
        $holder = new InMemoryTraceHolder();
        $outer = $holder->current();
        $null = new NullSpan('sql select');

        self::assertSame($null, $this->processor($holder)->process($null));
        self::assertSame($outer, $holder->current());
    }

    #[DataProvider('unitSpans')]
    public function testAUnitOfWorkSpanKeepsTheTraceTheTracingBundleGivesIt(string $message): void
    {
        $holder = new InMemoryTraceHolder();
        $outer = $holder->current();
        $span = new Span($message);

        self::assertSame($span, $this->processor($holder)->process($span));
        self::assertSame($outer, $holder->current());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unitSpans(): iterable
    {
        yield 'request' => ['request app_order_show'];
        yield 'command' => ['cli command app:import'];
        yield 'message' => ['message App\Message\ImportOrder'];
        yield 'action' => ['action order.create'];
    }

    public function testTheUnitPrefixesAreConfigurable(): void
    {
        $holder = new InMemoryTraceHolder();
        $processor = new TraceSpanProcessor($holder, ['batch ']);

        self::assertNotInstanceOf(TracedSpan::class, $processor->process(new Span('batch nightly')));
        self::assertInstanceOf(TracedSpan::class, $processor->process(new Span('request app_home')));
    }

    public function testResetForgetsTheOpenSpans(): void
    {
        $holder = new InMemoryTraceHolder();
        $processor = $this->processor($holder);
        $span = $processor->process(new Span('sql select'));
        $inside = $holder->current();

        $processor->reset();
        $span->end();

        self::assertSame($inside, $holder->current());
    }

    private function processor(InMemoryTraceHolder $holder): TraceSpanProcessor
    {
        return new TraceSpanProcessor($holder, ['request ', 'cli command ', 'message ', 'action ']);
    }

    private function factory(InMemoryTraceHolder $holder): ProfilingFactory
    {
        return new ProfilingFactory([new SpanAssembler([])], [$this->processor($holder)], [], new NullLogger());
    }
}
