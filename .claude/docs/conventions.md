# Соглашения

- Код бриджа — в `src/Infrastructure/Tracing/`; Application-слоя нет.
- Тесты: `tests/Unit/<Класс>Test.php` с `#[CoversClass]`; unit-тест процессора работает с
  `tests/Fixtures/InMemoryTraceHolder` (держатель tracing 2.0 — `@internal`) и настоящими `Span`/`NullSpan` profiling.
- `tests/Integration/KernelBridgeTest` поднимает настоящий `TestKernel` (Framework, Monolog, Tracing, Profiling,
  бридж; конфиг бриджа — аргумент конструктора) и читает записи каналов `app`/`profiling` из `monolog.handler.main`.
- Длинные строки в тестах допустимы: конфиг CS семейства их не ограничивает.
- Пакет создан копией tracing-bridge-logic (2026-10-22 UTC); `ExceptionHandlerSnapshot` и `InMemoryTraceHolder` оттуда.
- Infection гоняет только unit-сьют: всё, что должно убивать мутантов, — в `tests/Unit`.
- Файлы инструментов байт-в-байт из `bundle-standard/templates`; менять их можно только в стандарте.
- Коммиты — одна строка на английском, ≤ 20 слов; теги `vX.Y.Z`.
