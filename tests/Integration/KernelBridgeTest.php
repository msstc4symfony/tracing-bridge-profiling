<?php

declare(strict_types=1);

namespace Msstc4Symfony\TracingBridgeProfiling\Test\Integration;

use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Msstc4Symfony\ProfilingBundle\Application\ProfilingFactoryInterface;
use Msstc4Symfony\TracingBridgeProfiling\Infrastructure\Tracing\TraceSpanKeys;
use Msstc4Symfony\TracingBridgeProfiling\Test\Fixtures\CollectingHandler;
use Msstc4Symfony\TracingBridgeProfiling\Test\Fixtures\ExceptionHandlerSnapshot;
use Msstc4Symfony\TracingBridgeProfiling\Test\Fixtures\WorkMessage;
use Msstc4Symfony\TracingBridgeProfiling\Test\Integration\Kernel\TestKernel;
use Msstc4Symfony\TracingBundle\Application\Port\TraceContextHolderInterface;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversNothing]
final class KernelBridgeTest extends TestCase
{
    private TestKernel $kernel;

    /** @var list<string> */
    private array $bundleDeprecations = [];

    private ExceptionHandlerSnapshot $exceptionHandlers;

    #[Override]
    protected function setUp(): void
    {
        // trigger_deprecation() silences its notice with @, which PHPUnit ignores; record it directly.
        set_error_handler(function (int $level, string $message): bool {
            if (str_contains($message, 'msstc4symfony/')) {
                $this->bundleDeprecations[] = $message;
            }

            return false;
        }, E_USER_DEPRECATED);
        (new Filesystem())->remove(TestKernel::cacheRoot());
        $this->exceptionHandlers = ExceptionHandlerSnapshot::take();
        $this->kernel = new TestKernel('test', false, []);
        $this->kernel->boot();
    }

    #[Override]
    protected function tearDown(): void
    {
        restore_error_handler();
        // Guarded so that a failing setUp() is reported instead of an uninitialised property.
        if (isset($this->kernel)) {
            $this->kernel->shutdown();
        }

        // FrameworkBundle::boot() on symfony/error-handler before 7.4.17 leaves its exception handler
        // installed when another error handler is active, which PHPUnit reports as risky.
        if (isset($this->exceptionHandlers)) {
            $this->exceptionHandlers->restore();
        }

        (new Filesystem())->remove(TestKernel::cacheRoot());
    }

    public function testAManualSpanRunsInAChildSpanAndItsProfilingRecordNamesIt(): void
    {
        $unit = $this->trace()->current();
        $profiling = $this->container()->get(ProfilingFactoryInterface::class);
        self::assertInstanceOf(ProfilingFactoryInterface::class, $profiling);
        $logger = $this->container()->get(LoggerInterface::class);
        self::assertInstanceOf(LoggerInterface::class, $logger);

        $span = $profiling->createSpan('sql select');
        $inside = $this->trace()->current();
        $logger->info('Inside the span');
        $profiling->endSpan($span);

        self::assertSame($unit, $this->trace()->current());
        self::assertSame($unit->traceParent->spanId, $inside->parentSpanId);
        self::assertSame($unit->traceParent->traceId, $inside->traceParent->traceId);

        $records = $this->records();
        $log = $records['app']['Inside the span'] ?? null;
        $profiled = $records['profiling']['sql select'] ?? null;
        self::assertIsArray($log);
        self::assertIsArray($profiled);
        self::assertSame($inside->traceParent->spanId, $log['extra']['span_id'] ?? null);
        self::assertSame($inside->traceParent->spanId, $profiled['context']['trace_span_id'] ?? null);
        self::assertSame($unit->traceParent->spanId, $profiled['context']['trace_parent_span_id'] ?? null);
        self::assertSame([], $this->bundleDeprecations);
    }

    public function testAUnitOfWorkSpanKeepsTheUnitsTrace(): void
    {
        $unit = $this->trace()->current();
        $profiling = $this->container()->get(ProfilingFactoryInterface::class);
        self::assertInstanceOf(ProfilingFactoryInterface::class, $profiling);

        $span = $profiling->createSpan('message App\\Message\\Import');

        self::assertSame($unit, $this->trace()->current());
        $profiling->endSpan($span);
        self::assertArrayNotHasKey('trace_span_id', $this->records()['profiling']['message App\\Message\\Import']['context'] ?? []);
    }

    public function testEachMessageOfAWorkerTracesItsSpansInItsOwnTrace(): void
    {
        if (!interface_exists(MessageBusInterface::class)) {
            self::markTestSkipped('symfony/messenger is not installed');
        }

        $bus = $this->container()->get(MessageBusInterface::class);
        self::assertInstanceOf(MessageBusInterface::class, $bus);
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        // The in-memory transport forgets queued messages on kernel.reset, which the worker calls after each one.
        foreach ([1, 2] as $number) {
            $bus->dispatch(new WorkMessage($number));
            $exitCode = $application->run(new ArrayInput(['command' => 'messenger:consume', 'receivers' => ['async'], '--limit' => 1]), new NullOutput());
            self::assertSame(0, $exitCode);
        }

        $handled = [];
        $messageSpans = [];
        $sqlSpans = [];
        foreach ($this->recordList() as $record) {
            if ($record->channel === 'app' && str_starts_with($record->message, 'Handled ')) {
                $handled[] = $record->extra;
            } elseif ($record->channel === 'profiling' && str_ends_with($record->message, ' > sql work')) {
                $sqlSpans[] = $record->context;
            } elseif ($record->channel === 'profiling' && str_starts_with($record->message, 'message ')) {
                $messageSpans[] = $record->extra;
            }
        }

        self::assertCount(2, $handled);
        self::assertCount(2, $messageSpans);
        self::assertCount(2, $sqlSpans);
        self::assertNotSame($handled[0]['trace_id'] ?? null, $handled[1]['trace_id'] ?? null);
        foreach ([0, 1] as $i) {
            self::assertSame($messageSpans[$i]['trace_id'] ?? 'message', $handled[$i]['trace_id'] ?? 'handled');
            self::assertSame($messageSpans[$i]['span_id'] ?? 'message', $handled[$i]['parent_span_id'] ?? 'handled');
            self::assertSame($handled[$i]['span_id'] ?? 'handled', $sqlSpans[$i][TraceSpanKeys::SPAN_ID] ?? 'sql');
        }
    }

    public function testAnActionSpanGetsItsOwnTraceSpanWithoutTracingBridgeLogic(): void
    {
        $unit = $this->trace()->current();
        $profiling = $this->container()->get(ProfilingFactoryInterface::class);
        self::assertInstanceOf(ProfilingFactoryInterface::class, $profiling);

        $span = $profiling->createSpan('action order.create');

        self::assertSame($unit->traceParent->spanId, $this->trace()->current()->parentSpanId);
        $profiling->endSpan($span);
    }

    public function testTheExtraPrefixesReachTheProcessor(): void
    {
        $this->kernel->shutdown();
        $this->kernel = new TestKernel('extra_prefixes', false, ['extra_unit_span_prefixes' => ['sql ']]);
        $this->kernel->boot();

        $unit = $this->trace()->current();
        $profiling = $this->container()->get(ProfilingFactoryInterface::class);
        self::assertInstanceOf(ProfilingFactoryInterface::class, $profiling);

        $profiling->createSpan('sql select');

        self::assertSame($unit, $this->trace()->current());
    }

    /**
     * @return list<LogRecord>
     */
    private function recordList(): array
    {
        $handler = $this->container()->get(CollectingHandler::class);
        self::assertInstanceOf(CollectingHandler::class, $handler);

        return $handler->records;
    }

    /**
     * @return array<string, array<string, array{context: array<array-key, mixed>, extra: array<array-key, mixed>}>> records by channel and message
     */
    private function records(): array
    {
        $handler = $this->container()->get('monolog.handler.main');
        self::assertInstanceOf(TestHandler::class, $handler);
        $records = [];
        foreach ($handler->getRecords() as $record) {
            $records[$record->channel][$record->message] = ['context' => $record->context, 'extra' => $record->extra];
        }

        return $records;
    }

    private function trace(): TraceContextHolderInterface
    {
        $trace = $this->container()->get(TraceContextHolderInterface::class);
        self::assertInstanceOf(TraceContextHolderInterface::class, $trace);

        return $trace;
    }

    private function container(): ContainerInterface
    {
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }
}
