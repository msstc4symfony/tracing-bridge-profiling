# Changelog

All notable changes to this bundle are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versions follow
[Semantic Versioning](https://semver.org/); dates are UTC.

## [1.0.0] - 2026-10-22

### Added

- Every recorded profiling-bundle span, except unit-of-work spans, runs in a child span of the tracing-bundle trace;
  its `profiling` record carries `trace_span_id` and `trace_parent_span_id` (`TraceSpanKeys`). Unit-of-work spans:
  `request `, `cli command `, `message `, plus `action ` when tracing-bridge-logic is registered, plus
  `extra_unit_span_prefixes`. Needs profiling-bundle ^2.1 (`EndableAtInterface`).

[1.0.0]: https://github.com/msstc4symfony/tracing-bridge-profiling/releases/tag/v1.0.0
