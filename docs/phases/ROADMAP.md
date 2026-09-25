---
status: canonical
version: 1.0
updated: 2026-09-25
---

# Roadmap проекта на стартере

Сайт собирается из HTML-прототипа за 8 фаз. Источник этапов — [`STARTER-PLAN.md`](../STARTER-PLAN.md) §4; здесь — порядок, gate и ссылки на phase-файлы (L3-контекст: агент читает текущий phase-файл явно при старте).

| # | Фаза | Результат | Gate |
|---|---|---|---|
| 0 | [Init](phase-0-init.md) — `npm run init`, `env:start` | Чистый WP, тема и ядро активны под именами проекта, `.env` | `npm run gate:0` + ручной AC T0.2 (200, без PHP notices) |
| 1 | [Приёмка прототипа](phase-1-prototype.md) | Прототип по регламенту, `pages-map.json` (`lcp`, `above_fold`, `psi`), инвентаризация компонентов. **Утверждает человек** | `npm run gate:1` |
| 2 | [Модель данных](phase-2-data-model.md) | Сущности, поля, имена в `naming.json`, seed JSON клиента | `npm run gate:2` |
| 3 | [Ядро mu-plugin](phase-3-core.md) | Данные доступны через `starter_get_*()`, seed идемпотентен | `npm run gate:3` + `npm run gate:0` |
| 4 | [Оболочка темы](phase-4-theme-shell.md) | Токены, шрифты, шапка / подвал / меню, общие parts | `npm run gate:4` |
| 5 | [Перенос страниц](phase-5-pages.md) — цикл `/port-page` | Все шаблоны из `pages-map` | `npm run gate:page -- <url>` на каждую + `npm run gate:5` |
| 6 | [Динамика, формы, SEO, AI](phase-6-dynamic.md) | CPT-блоки, форма + Telegram, Yoast-схемы, sitemap, `/llms.txt` | `npm run gate:6` |
| 7 | [QA и запуск](phase-7-launch.md) | Релиз, чеклист запуска, PSI baseline | `npm run gate:7` + [`launch.md`](../playbooks/launch.md) + запись в `perf-log` |

После запуска — эксплуатация: PSI по запросу после крупных изменений, вехи в [`project/perf-log.md`](../project/perf-log.md), `enforce` вручную.

## Закрытие фазы

Фаза закрыта, когда выполнены **все три** шага:

1. **Gate:** `npm run gate:N` — код выхода 0. Красный gate = фаза не готова; чекбоксы, отмеченные агентом, gate не заменяют (D5).
2. **`/doc-align`** — сверка артефактов фазы с «Связанными документами» phase-файла и контрактами ([`.claude/commands/doc-align.md`](../../.claude/commands/doc-align.md)); запускается и при **старте** фазы, до кода.
3. **`/phase-review`** — независимое ревью **другим агентом / моделью**, не тем, что реализовывал ([`.claude/commands/phase-review.md`](../../.claude/commands/phase-review.md), K18). Блокеры закрываются, затем повторный review.

**Не начинать следующую фазу до закрытия gate предыдущей.**

## Правила

- Сначала документ, потом код: меняется контракт — правка документа с датой, затем код.
- Задача фазы = AC **Happy / Negative / Security**; AC проверяем командой или конкретным файлом.
- Одна задача / страница — один коммит, только при зелёном gate.
- e2e — вторичный контроль (D26): при недоступном окружении (нет wp-env / Chromium) e2e-часть gate фиксируется в отчёте фазы как не выполненная, статическая часть обязана быть зелёной.
- Нарушение, которое агент совершил дважды, — новая проверка в `tools/`, а не абзац.
- Имена — только из [`naming.json`](../contracts/naming.json); API WordPress / SCF / Yoast / Playwright — через Context7.
