---
status: canonical
version: 0.2
updated: 2026-09-25
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
| [`STARTER-PLAN.md`](STARTER-PLAN.md) | Принципы, процесс проекта, milestones стартера, решения D1–D26 | canonical |
| [`decisions/`](decisions/) | ADR. [`0001`](decisions/0001-example-migration.md) — реестр миграции `/example` | canonical |

### `contracts/` — интерфейсы стартера (что и где в коде)

| Документ | Что | Статус |
|---|---|---|
| [`naming.json`](contracts/naming.json) → [`naming-dictionary.md`](contracts/naming-dictionary.md) | Канон и запреты имён (словарь генерируется) | canonical |
| [`mu-plugin.md`](contracts/mu-plugin.md) | Ядро: модули, API, хуки, seed, снимок конфига, версия и rewrite | canonical |
| [`theme.md`](contracts/theme.md) | Тема: `inc/`, шаблоны, enqueue, `.reveal`, `<head>` | canonical |
| [`template-parts.md`](contracts/template-parts.md) | Parts: аргументы, данные, разметка | canonical |
| [`data-structures.md`](contracts/data-structures.md) | Сущности и источники истины | canonical |
| [`forms.md`](contracts/forms.md) | Лид-форма: поля, nonce, honeypot, лимит, ответы, событие, Telegram | canonical |
| [`seo.md`](contracts/seo.md) | Yoast, graph pieces, `pages-map` → schema / noindex, `llms.txt`, robots | canonical |
| [`images.md`](contracts/images.md) | Размеры, `starter_image()`, WebP, регенерация | canonical |
| [`blog.md`](contracts/blog.md) | `post` + `category`, шаблоны, TOC, автор | canonical |
| [`testing.md`](contracts/testing.md) | Статические проверки и e2e-наборы, правила тестов | canonical |

### `playbooks/` — переносимое знание (почему так)

| Документ | Что | Статус |
|---|---|---|
| [`architecture.md`](playbooks/architecture.md) | Слои и принципы | canonical |
| [`performance.md`](playbooks/performance.md) | Шрифты, render-blocking, `.reveal`, LCP, GA4, фасады, WebP, чеклист, «не делать» | canonical |
| [`psi.md`](playbooks/psi.md) | Процедура PSI: ключ, запуск, пороги, чтение Lighthouse 13, выбросы | canonical |
| [`prototype-rules.md`](playbooks/prototype-rules.md) | Регламент HTML-прототипа и что проверяет `lint:prototype` | canonical |
| [`analytics.md`](playbooks/analytics.md) | Отложенный GA4, события, диагностика | canonical |
| [`launch.md`](playbooks/launch.md) | Чеклист запуска: индексация, домен, хостинг, WebP, секреты, бэкапы, PSI baseline | canonical |

### `phases/` и `project/`

| Путь | Что | Статус |
|---|---|---|
| [`phases/ROADMAP.md`](phases/ROADMAP.md) | 8 фаз проекта, закрытие фазы (gate + `/doc-align` + `/phase-review`); phase-0…7 | canonical |
| [`project/`](project/README.md) | Шаблоны, заполняемые на проекте: sitemap, seo-strategy, pages, seo, prds, test-cases, manager-guide, qa-report, perf-log, llms.txt | planned (заполняются на фазах 0–7 проекта) |

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
