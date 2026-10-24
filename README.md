# msstc4symfony/tracing-bridge-profiling

![Build Status](https://github.com/msstc4symfony/tracing-bridge-profiling/actions/workflows/checks.yml/badge.svg?branch=main)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Runs every [profiling-bundle](https://github.com/msstc4symfony/profiling-bundle) span in its own
[tracing-bundle](https://github.com/msstc4symfony/tracing-bundle) span, so the logs and outgoing calls made inside a
span share its `span_id`, its `parent_span_id` points at the span or unit of work around it, and the span's own
`profiling` log record names both.

## Compatibility

| Bundle | PHP  | Symfony  | profiling-bundle | tracing-bundle |
|--------|------|----------|------------------|----------------|
| 1.0    | 8.2+ | 7.4, 8.x | ^2.1             | ^2.0           |
| 1.1    | 8.2+ | 7.4, 8.x | ^2.1             | ^2.0           |
| 1.2    | 8.2+ | 7.4, 8.x | ^2.2             | ^2.0           |

Symfony 8.x needs PHP 8.4; on PHP 8.2 and 8.3 Composer installs Symfony 7.4.

## Installation

The packages are not on Packagist yet, so register their GitHub repositories first:

```sh
composer config repositories.msstc4symfony-profiling vcs https://github.com/msstc4symfony/profiling-bundle
composer config repositories.msstc4symfony-tracing vcs https://github.com/msstc4symfony/tracing-bundle
composer config repositories.msstc4symfony-tracing-bridge-profiling vcs https://github.com/msstc4symfony/tracing-bridge-profiling
composer require msstc4symfony/tracing-bridge-profiling
```

Register the bridge in `config/bundles.php` alongside the two bundles it connects (order does not matter):

```php
Msstc4Symfony\TracingBundle\TracingBundle::class => ['all' => true],
Msstc4Symfony\ProfilingBundle\ProfilingBundle::class => ['all' => true],
Msstc4Symfony\TracingBridgeProfiling\TracingBridgeProfilingBundle::class => ['all' => true],
```

Without TracingBundle the container fails to compile: the bridge's span processor cannot be autowired, as no
`TraceContextHolderInterface` service exists.

## The span

While a recorded profiling span is open — one you open with `ProfilingFactoryInterface::createSpan()`, or one another
bridge opens:

- `span_id` is new and `parent_span_id` is the span id around it — the enclosing profiling span or the unit of work;
  the trace, runtime id, trace state and baggage are the unit's;
- logs, HTTP requests, Messenger messages and Sentry events of the span carry it;
- the span's `profiling` log record has `trace_span_id` and `trace_parent_span_id` in its context. Its own `span_id` /
  `parent_span_id` fields name the span around it: profiling writes the record after the span — and its trace span —
  ended.

When the span ends the trace around it is restored, also when profiling ends it implicitly with its parent. When a
nested unit of work (a sync message, an Action) ended the span and then put its trace back, the next span of the bridge
— or the end of an enclosing one — moves on to the trace around it; logs written in between still name the ended span. When tracing-bundle has moved to
another trace (a reset, the next message of a worker) the bridge leaves that trace alone. A span opened before the
unit's trace exists (a `kernel.request` listener above priority 2048, a `console.command` one above 100) is a child
of the previous trace: its `trace_span_id` names a span of that trace.

Left as they are:

- spans a decision maker filtered out (profiling's `NullSpan`): they open no trace span;
- detached spans (context `DetachedSpanFactoryInterface::DETACHED` of profiling-bundle 2.2, set on its HTTP request spans): they end after
  the spans opened meanwhile, so the request runs in the trace span around it;
- unit-of-work spans, by message prefix: `request `, `cli command ` and `message ` of profiling-bundle, which
  tracing-bundle already gives their own span — and the command and message spans open before tracing-bundle
  switches to the unit's trace; `action ` of [profiling-bridge-logic](https://github.com/msstc4symfony/profiling-bridge-logic)
  only when [tracing-bridge-logic](https://github.com/msstc4symfony/tracing-bridge-logic) is registered, which runs
  every Action in its own span. Do not start your own span messages with these prefixes: such a span keeps the trace
  around it.

End handlers of a traced span receive the span profiling built, not the wrapper `createSpan()` returned: its context
lacks the two keys. Removing the bridge's end handler — the span's first one, index 0 — keeps the span's trace current, and later spans
become its children, until tracing-bundle replaces the trace (the next unit of work) or the next reset.

## Configuration

```yaml
# config/packages/msstc4symfony_tracing_bridge_profiling.yaml
msstc4symfony_tracing_bridge_profiling:
    # added to the built-in unit prefixes; an empty prefix is rejected
    extra_unit_span_prefixes: ['batch ']
```

tracing-bundle exports no spans: the call tree is rebuilt from the `span_id` / `parent_span_id` log keys. One trace
holder serves the whole container, so spans opened in concurrent Fibers would interleave.

## Public API

The bundle class `Msstc4Symfony\TracingBridgeProfiling\TracingBridgeProfilingBundle`, its configuration and the context
keys `trace_span_id` / `trace_parent_span_id` (constants of `Msstc4Symfony\TracingBridgeProfiling\Infrastructure\Tracing\TraceSpanKeys`);
the span processor and its span wrapper are `@internal`.

## Local development

`composer-ci.json` installs profiling-bundle and tracing-bundle from their GitHub repositories.

```sh
make install-ci                        # composer-ci.json profile with every CI tool
COMPOSER=composer-ci.json make check   # lint, PHPStan, CS, composer validate/audit, Rector, deptrac
make test                              # unit + integration
```

## License

MIT — see [LICENSE](LICENSE).
