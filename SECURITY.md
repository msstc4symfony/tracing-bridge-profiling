# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 2.x     | :white_check_mark: |
| 1.x     | :x:                |

Runtime requirements: PHP >= 8.2 (1.2+; 1.0–1.1 need PHP 8.4), Symfony 7.4 / 8.x.

## Reporting a Vulnerability

Do **not** open public issues for security problems. Use:

1. **GitHub Security Advisory** (preferred):
   <https://github.com/msstc4symfony/tracing-bridge-profiling/security/advisories/new>
2. **Email**: `maxim.shamaev@gmail.com`

Include the bundle, PHP and Symfony versions, a reproducer and the impact. You will get an
acknowledgement within **7 days**; please allow 30–90 days before public disclosure.

## Threat Model

The bundle stores no data, makes no network calls and handles no user input: it only swaps span ids in memory.
The ids reach logs, Sentry and outgoing headers through msstc4symfony/tracing-bundle, whose threat model applies.
