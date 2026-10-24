# Известные ловушки

## Бридж

- **`continuing()` не подходит** для дочернего span: он переписывает `remoteTraceParent` и `traceState`.
- **Порядок end handler'ов profiling**: handler процессора на span выполняется раньше handler'а фабрики, а фабрика
  закрывает открытых детей уже после. Отсюда цепочка по закрытым child'ам, см. architecture.md.
- **Обёртка и время конца (ревью 2026-10-22)**: фабрика profiling 2.0 закрывала детей временем родителя только для
  `AbstractSpan`; `TracedSpan` — не он (`endAt()` final) — и ребёнок переживал родителя. profiling 2.1 проверяет
  `EndableAtInterface`, `TracedSpan` его делегирует; мост требует profiling ^2.1.
- **FQCN `TracingBridgeLogicBundle` — строкой** в `DependencyInjection/UnitSpanPrefixes` (класс не в зависимостях):
  при переименовании в tracing-bridge-logic префикс `action ` молча перестанет добавляться — сверять при его мажорах.
- **`composer-ci.lock` в git**, как у остальных пакетов семейства: job `setup` CI ставит зависимости по нему. Локально
  `roave/security-advisories` конфликтует с PHP 8.4.17 — lock обновлять с `--ignore-platform-req=php`, работать через
  `composer-local.json`.
- **Worker в тестах**: `kernel.reset` после каждого сообщения очищает Monolog `TestHandler` и in-memory транспорт
  Messenger — тест worker'а пишет в `tests/Fixtures/CollectingHandler` и запускает `messenger:consume --limit=1` на
  каждое сообщение.
- **`kernel.reset` без порядка** (`ResettableServicePass` не сортирует): reset фабрики profiling может закрыть span'ы
  после reset держателя tracing — проверка `current()` по идентичности не даёт вернуть trace прошлой единицы работы.
- **Свои id в `extra` записи `profiling` не попадают** — запись пишется после restore; id в контексте span
  (`trace_span_id`/`trace_parent_span_id`).
- **Изменения контекста внутри span отбрасываются** при его закрытии, если это контекст моста. Общий держатель не
  годится для Fiber/async, как и сам tracing-bundle.
- **Span'ы нигде не экспортируются**: дерево восстанавливается по `span_id`/`parent_span_id` в логах.
- **Маркер detached — константа `DetachedSpanFactoryInterface::DETACHED`** (с v1.2.0, 2026-10-24 UTC; раньше ключ
  `profiling_detached` читался строкой ради profiling ^2.1). Мост требует profiling ^2.2; все пары ключ/значение
  константы должны совпасть строго (`true`, не `1`/`'true'`).

## Семейство (Symfony, CI)

- **`bin/init` шаблона даёт namespace с суффиксом `Bundle`** и имя пакета `…-bundle` (историческое: при создании
  репозитория); у бриджей семейства без суффикса — поправлено вручную (composer, deptrac, README, SECURITY, CI slug).
- **`kernel.build_dir` в тестовом контейнере.** `PhpFileLoader` Symfony 7.4 читает этот параметр,
  8.x — нет; без него интеграционные тесты падают только в ячейке 7.4.
- **Теги для своих интерфейсов** (интерфейсов этого пакета, которые реализуют сервисы приложения) регистрировать в `loadExtension()` через
  `registerForAutoconfiguration()`, а не `#[AutoconfigureTag]` на интерфейсе: атрибут на интерфейсе
  из vendor-пакета приложение не подхватывает.
- **`gh run watch` может вернуть 0 на упавшем прогоне.** Итог проверять через
  `gh run view <id> --json conclusion,jobs`.
- **`actions/checkout` по sha делает shallow-клон**; push из такого клона отклоняется как
  "fetch first" — нужен `fetch-depth: 0`.
- **Пороги Infection** (`infection-min-msi`, `infection-min-covered-msi` в `checks.yml`) = замер минус запас,
  с датой замера (UTC) в комментарии; пересчитывать при заметном изменении тестов.
- **`exec()` портит вывод с путями**: режет по строкам и срезает пробелы в конце строки, поэтому
  `git ls-files -z` через него теряет символы в именах. Читать через `proc_open` целиком. В тестах не
  читать stdout и stderr из двух pipe по очереди (deadlock при заполненном буфере) — stderr писать в файл.
- **Код должен разбираться на PHP 8.2** (с 2026-10-14 UTC, профиль `php82` из bundle-standard v2.0.0+): без
  типизированных констант (`const string X`), без анонимных `new readonly class {}` (оба — 8.3;
  именованный `readonly class` — 8.2, можно) и без `new X()->m()` / `new X()(...)` (8.4) — только
  `(new X())->m()`. PHPStan (`phpVersion.min: 80200`) ловит типизированные
  константы; остальное ловят только `php -l` в job «minimal» (PHP 8.2) и ячейки PHPUnit на 8.2. Шаблоны Rector
  (`withPhpVersion(PHP_82)`) и CS-Fixer (`new_expression_parentheses`) синтаксис обратно не поднимают.
  `#[Override]` (8.3) допустим: на 8.2 атрибут не инстанцируется. `array_any/all/find` (8.4) — только с
  `symfony/polyfill-php84` в `require`. Symfony 8 ставится только на PHP 8.4+.
  `composer-ci.lock` собран на PHP 8.4 (Symfony 8): на 8.2/8.3 локально — `COMPOSER=composer-ci.json composer update`
  (или `--prefer-lowest`, как ячейка CI), не `install`.
- **PhpStorm (`.idea/php.xml` на уровне монорепо) стоит на языке 8.5**: его инспекции предлагают вернуть типы
  констант и убрать скобки у `new` — для пакетов на 8.2 это шум, не применять.
