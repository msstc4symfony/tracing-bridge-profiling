# CLAUDE.md

Guidance for Claude Code in this repository. Deep references live under `.claude/docs/`.

## What this is

Symfony bundle (`msstc4symfony/tracing-bridge-profiling`, namespace `Msstc4Symfony\TracingBridgeProfiling`, extension
alias `msstc4symfony_tracing_bridge_profiling`, one option `extra_unit_span_prefixes`) that connects
`msstc4symfony/profiling-bundle` to `msstc4symfony/tracing-bundle`: every recorded profiling span except the
unit-of-work ones runs in a child span of the current trace. PHP >= 8.2, Symfony 7.4 / 8.x.

## Common commands

Develop against the CI profile: `COMPOSER=composer-ci.json composer install`.

- `make check` — `php -l`, PHPStan level 10, PHP-CS-Fixer, `composer validate --strict`,
  `composer audit`, Rector dry-run, deptrac. Run as `COMPOSER=composer-ci.json make check`.
- `make test` — unit + integration suites; `make infection`, `make fix`.
- `php ../bundle-standard/bin/verify-standard.php .` — standard compliance (the same check CI runs).

## Architecture in 60 seconds

- `Infrastructure\Tracing\TraceSpanProcessor` (`@internal`, `ResetInterface`) — profiling-bundle
  `CreateSpanProcessorInterface`: for a recorded span whose message has no unit prefix it adds an end handler,
  remembers `child → outer` in a `WeakMap` (`open`, moved to `ended` on end) and `replace()`s the holder with `current()->withChildSpan()`. On end it
  marks the child ended and walks `current()` back through ended children to the first open one (or the trace around
  them) — only from a context it installed.
- `Infrastructure\Tracing\TracedSpan` (`@internal`) — the wrapper returned for such spans; delegates everything incl.
  profiling 2.1 `EndableAtInterface` (children end at the parent's end time), `getContext()` adds `TraceSpanKeys`.
- `TracingBridgeProfilingBundle` — unit prefixes `request `/`cli command `/`message ` always, `action ` when
  `kernel.bundles` has TracingBridgeLogicBundle, then `extra_unit_span_prefixes`; passed as a parameter to `services.php`.

Details: `.claude/docs/architecture.md`. **Read `.claude/docs/known-issues.md` before chasing a
"weird" failure.**

## Pointers

- `.claude/docs/architecture.md` — layers, wiring, service ids.
- `.claude/docs/conventions.md` — layout, tests, commits.
- `.claude/docs/known-issues.md` — bridge and family-wide Symfony/CI traps.
