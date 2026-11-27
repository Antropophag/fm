## Context

Существующие списки используют независимые LIKE/LOCATE/substring. Нужны адресная грамматика, нечёткий текст и строгие числовые компоненты до пагинации. Выбор компонентов исследован в docs/research/flexible-address-search.md; окончательное решение ниже уточняет предварительную рекомендацию.

## Goals / Non-Goals

Общий read-only модуль AddressSearch владеет разбором/матчингом; существующие MariaDb readers владеют SQL и авторизацией. Никаких новых domain writers, schema migrations, durable indexes или сторонних запросов. rapid-pilot UI не изменяется.

## Decisions

Готовый engine — совместимые закреплённые loilo/Fuse (PHP) и Fuse.js (офлайн), локальные зависимости с лицензиями. FMonitor задаёт адресную грамматику и точные house/corpus/building/entrance predicates; Fuse отвечает за нечёткое сопоставление названий. Одна таблица сценариев проверяет одинаковые PHP/JS результаты.

SQL readers получают только лёгкую поисковую проекцию (identity,address,entrance и уже существующие поисковые строки), выбирают matching IDs общим engine и применяют этот набор вместе с исходными predicates ПЕРЕД COUNT/ORDER/LIMIT. Это не post-page filtering и не rich hydration. На пустом запросе дополнительное сканирование не нужно. Не вводить cutoff, теряющий данные. Числовые ограничения применяются до fuzzy matching; повторяющиеся нормализованные адреса/слова переиспользуются в пределах одного запроса. Стабильный порядок сохраняется. Отсутствие второго persistent index исключает рассинхронизацию с edits/import.

Trade-off относительно исходной рекомендации research: SQL regex-only оставлял бы разные typo semantics online/offline, поэтому единый Fuse применяется и к лёгким SQL кандидатам. Текущий LIKE также сканирует набор. Benchmark10000 и реальные page integration checks обязательны; при неприемлемой стоимости пересмотреть retrieval, не замалчивать ограничения. Сложный libpostal/native model, TNT second index и fulltext short-token loss сейчас не оправданы.

Офлайн engine и bundle входят в штатный asset/service-worker cache manifest; никаких CDN. Saved calculations используют поля снимка, не текущие адресные данные; отсутствующий подъезд остаётся неизвестным.

## Risks / Trade-offs

- Linear candidate scan → лёгкие поля, request-local reuse, numeric prefilter, benchmark и отсутствие скрытой обрезки.
- Ошибки числового разбора → corpus с цифровыми названиями улиц, литерами, дробями, строениями, конфликтами и adversarial near numbers.
- PHP/JS drift → единый fixture corpus и cross-runtime tests.
- Новые зависимости/cache → lock, licence, immutable versioned assets и offline cache verification.
- Различные sql effective overlays → integration тесты исправленных адреса/подъезда и exact totals.

## Migration Plan

Нет DDL/новых persisted facts; delivery сохраняет backup/schema текущего home runtime и отдельные ещё не merged home изменения. После review/CI normal PR merge; затем совместимая home assembly и smoke. Rollback к предшествующему runtime без downgrade данных. Архитектурные ограничения не ослабляются. Root спецификация/тесты; отдельный sol/low executor реализация; независимый reviewer.
