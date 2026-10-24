# Архитектура

- `TracingBridgeProfilingBundle` + `DependencyInjection/UnitSpanPrefixes` (чистая функция, unit-тест без `services.php`,
  который под coverage делает тест risky) — `AbstractBundle`, опция `extra_unit_span_prefixes` (список непустых строк, по
  умолчанию пуст). `loadExtension()` собирает префиксы: `request `, `cli command `, `message ` всегда, `action ` — если
  в `kernel.bundles` есть `TracingBridgeLogicBundle` (иначе Action-span'у profiling-bridge-logic некому дать trace
  span), затем extra; кладёт в параметр `msstc4symfony_tracing_bridge_profiling.unit_span_prefixes`, `services.php`
  передаёт его в `$unitSpanPrefixes` (deptrac: BundleRoot не ссылается на Infrastructure).
- `Infrastructure/Tracing/TraceSpanProcessor` — profiling-bundle `CreateSpanProcessorInterface` (тег через
  autoconfigure: `#[AutoconfigureTag]` на интерфейсе profiling срабатывает, потому что сервис моста регистрирует сам
  мост с `autoconfigure()`; ловушка из known-issues — про интерфейсы самого моста, которые реализуют сервисы
  приложения; явный `->tag()` рядом с autoconfigure повесил бы тег дважды) + `ResetInterface` (`kernel.reset` очищает карты).
  - Для записанного span (`isRecorded()`) без префикса единицы работы: `$outer = current()`,
    `$child = $outer->withChildSpan()`, end handler на span, `open[$child] = $outer` (`WeakMap`), `replace($child)`.
  - End handler: переносит запись из `open` в `ended`; от `current()` идёт назад по `ended` (закрытые child'ы этого
    процессора → их outer) и ставит найденный. Если `current()` не наш (tracing сменил trace) — ничего.
  - Почему цепочка, а не стек (ревью 2026-10-22): `ProfilingFactory::onEnd()` закрывает открытых детей ПОСЛЕ end
    handler'ов родителя — родитель не восстанавливает, пока current — открытый ребёнок, а при неявном закрытии
    ребёнка цепочка доходит до outer родителя. Вложенная единица работы может закрыть span и вернуть его child в
    `finally`: `firstOpen()` в `close()` (закрытие внешнего span) и в `process()` (следующий span) уходит с такого
    child на trace вокруг (`testASpanClosedInsideANestedUnitOfWorkDoesNotStrandTheTrace`,
    `testTheNextSpanDoesNotBecomeAChildOfASpanEndedInsideANestedUnitOfWork`). Логи между `finally` и следующим событием
    span'а несут id закрытого span — окно описано в README.
  - `WeakMap`: записи исчезают вместе с контекстами, долгоживущий worker не копит память
    (`testEndedSpansLeaveNothingBehind`).
- `Infrastructure/Tracing/TracedSpan` — обёртка, которую процессор возвращает (фабрика кладёт её в стек и передаёт
  end-процессорам). Делегирует всё; `getContext()` добавляет `trace_span_id`/`trace_parent_span_id`: `LoggerProcessor`
  profiling пишет запись после end handler'ов, то есть после restore, и её `extra` (`TraceLogProcessor`) называет
  внешний span.
- Span'ы единиц работы не трогаются: их span уже даёт tracing-bundle (HTTP/консоль/Messenger) или tracing-bridge-logic
  (Action, priority 600 снаружи profiling-bridge-logic 500), и `extra` их записей уже верный. Второй span для
  `action …` был бы дублем. Плюс по таймингу: span консоли (4096) и сообщения (Received -1024) открываются ДО того, как
  tracing-bundle ставит trace единицы (100 / в middleware шины), — child от них ушёл бы в чужой trace.
- `NullSpan` (отфильтрован decision maker'ом) и detached span (`profiling_detached` в контексте, profiling 2.2: HTTP;
  маркер — `DetachedSpanFactoryInterface::DETACHED`, мост требует profiling ^2.2 с v1.2.0) — span трейса не
  открывают: detached закрывается позже открытых после него span'ов, его trace span висел бы над ними.
- Только публичный API tracing-bundle 2.0 (`TraceContextHolderInterface`, `TraceContext`) и profiling-bundle 2.2
  (`CreateSpanProcessorInterface`, `SpanInterface`, `EndableAtInterface`, `DetachedSpanFactoryInterface::DETACHED`).
  Сервис приватный, id = FQCN, `@internal`.
- deptrac: код в Infrastructure и DependencyInjection; BundleRoot → DependencyInjection, от Infrastructure не зависит.
