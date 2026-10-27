# Changelog

All notable changes to this bundle are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versions follow
[Semantic Versioning](https://semver.org/); dates are UTC.

## [1.3.0] - 2026-10-27

### Added

- The HTTP request span of profiling-bundle 2.3 (`DetachedSpanFactoryInterface::OUTGOING_REQUEST`) reserves a child
  span of the current trace through tracing-bundle 2.4's `OutgoingSpanReservationInterface`: its record carries
  `trace_span_id` / `trace_parent_span_id`, and the request's `traceparent` names it. Other detached spans are left as
  they are.

### Changed

- Requires profiling-bundle ^2.3.1 (its HttpClient span opens before the request) and tracing-bundle ^2.4.

## [1.2.0] - 2026-10-24

### Changed

- Requires profiling-bundle ^2.2: a detached span is recognised by `DetachedSpanFactoryInterface::DETACHED` instead of
  the `profiling_detached` key spelled out in the bridge.

## [1.1.0] - 2026-10-22

### Added

- A detached span (profiling-bundle 2.2 `DetachedSpanFactoryInterface`, context `profiling_detached`) opens no trace
  span: it ends after the spans opened meanwhile, and its trace span would stay current over them.

## [1.0.0] - 2026-10-22

### Added

- Every recorded profiling-bundle span, except unit-of-work spans, runs in a child span of the tracing-bundle trace;
  its `profiling` record carries `trace_span_id` and `trace_parent_span_id` (`TraceSpanKeys`). Unit-of-work spans:
  `request `, `cli command `, `message `, plus `action ` when tracing-bridge-logic is registered, plus
  `extra_unit_span_prefixes`. Needs profiling-bundle ^2.1 (`EndableAtInterface`).

[1.3.0]: https://github.com/msstc4symfony/tracing-bridge-profiling/releases/tag/v1.3.0
[1.2.0]: https://github.com/msstc4symfony/tracing-bridge-profiling/releases/tag/v1.2.0
[1.1.0]: https://github.com/msstc4symfony/tracing-bridge-profiling/releases/tag/v1.1.0
[1.0.0]: https://github.com/msstc4symfony/tracing-bridge-profiling/releases/tag/v1.0.0
