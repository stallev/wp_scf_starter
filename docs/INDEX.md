---
status: canonical
version: 0.1
updated: 2026-09-24
---

# Документация: карта

Единственное место правила приоритета и статусов. Остальные документы на него ссылаются и не повторяют.

## Правило приоритета при конфликте

1. **Project** — `prototype/`, `docs/project/*`, `pages-map.json`, `project.config.json`
2. **Canonical** — `docs/contracts/*`, `docs/playbooks/*`, `docs/phases/*`
3. **Decisions** — `docs/decisions/*` (ADR: почему принято решение; история, не инструкция)

Расхождение фиксируется правкой документа с датой, а не молчаливым изменением кода. Сначала документ, потом код.

## Статусы (frontmatter `status`)

| Статус | Значение |
|---|---|
| `canonical` | Актуальный источник для агента и разработки |
| `planned` | Заготовка, наполняется в указанном milestone/фазе |
| `superseded` | Устарел; читать только как историю, в ссылке указать замену |

## Карта

| Путь | Что | Статус |
|---|---|---|
| [`STARTER-PLAN.md`](STARTER-PLAN.md) | Принципы, процесс проекта, milestones стартера, решения D1–D16 | canonical |
| [`decisions/`](decisions/) | ADR. [`0001`](decisions/0001-example-migration.md) — реестр миграции `/example` | canonical |
| `contracts/` | Интерфейсы: naming (+ `naming.json`), forms, seo, images, blog, testing, mu-plugin, theme, data-structures, template-parts | planned (M5, M7) |
| `playbooks/` | Переносимое знание: performance, psi, prototype-rules, architecture, analytics, launch | planned (M7) |
| `phases/` | ROADMAP + phase-0…7 проекта на стартере | planned (M7) |
| `project/` | Заполняется на конкретном проекте: sitemap, seo, pages, prds, perf-log, qa-report | planned (M7) |

## Файлы конфигурации в корне

| Файл | Назначение | Схема |
|---|---|---|
| `project.config.json` | Префикс, slug-и, URL окружений, locale, шрифты, изображения, аналитика, модули, пути | [`schemas/project.config.schema.json`](../schemas/project.config.schema.json) |
| `pages-map.json` | Манифест страниц: прототип → шаблон → проверки (e2e, perf-markup, PSI, сидер) | [`schemas/pages-map.schema.json`](../schemas/pages-map.schema.json) |
| `.wp-env.json` | Локальное окружение: PHP, тема, mu-plugins, lifecycle | — |
| `.wp-env.override.json` | Генерируется `tools/wp-env.mjs` при старте: версии WP и плагинов из конфига (gitignored) | — |
| `package.json` | npm-скрипты: окружение, проверки, gate | — |
| `composer.json` | PHP dev-зависимости: WPCS, PHPCompatibility, PHPStan, parallel-lint | — |
| `.env.example` | Шаблон `.env` (секреты, gitignored) | — |
| `phpcs.xml.dist`, `phpstan.neon.dist` | WPCS + PHPCompatibility (8.1+), PHPStan | — |

Проверка обоих JSON: `npm run check:config`.
