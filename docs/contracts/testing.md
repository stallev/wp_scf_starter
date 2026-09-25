---
status: canonical
version: 1.0
updated: 2026-09-25
---

# Контракт тестирования

Основной контроль качества — **статические проверки** (быстрые, без окружения). e2e Playwright — дополнительный, низкоприоритетный слой (D26): наборы поддерживаются в рабочем состоянии, но их падение из-за окружения не повод откладывать статический gate. PSI — не тест, а ручной замер ([`playbooks/psi.md`](../playbooks/psi.md)).

## Слои

| Слой | Команды | Нужен wp-env | Когда |
|---|---|---|---|
| Статические проверки | `check:config`, `check:naming`, `check:links`, `check:rules`, `check:hardcode`, `check:seeds`, `lint:php`, `lint:prototype` | нет | Каждое изменение; `gate:0` |
| Самотесты tools | `test:tools` (`tools/__tests__/`, `node --test`) | нет | Изменение `tools/`; `gate:7` |
| Seed | `check:seed-idempotent` | да | `gate:3`, изменение seed / сидера |
| e2e | `test:e2e[:<suite>]`, `gate:4…7`, `gate:page` | да | Фазы 4–7, перенос страницы |
| PSI | `npm run psi` | нет (публичный URL) | Только по явному запросу после деплоя |

Состав `gate:*` — `package.json`; e2e-наборы фаз — `PHASE_SUITES` / `PAGE_SUITES` в [`tools/gate.mjs`](../../tools/gate.mjs). Таблица команд — `AGENTS.md` → «Команды».

## e2e-наборы

| Набор | Что проверяет | Данные |
|---|---|---|
| `static` | 200 на каждом URL, число `form.js-lead` = `lead_form`, один `h1`, `noindex` meta, 404 на неизвестном URL | `pages-map` |
| `navigation` (desktop + mobile) | Меню, дропдауны с клавиатуры и ARIA, бургер, skip link | шапка темы |
| `forms` | Happy / negative / security формы ([`forms.md`](forms.md)) | WP-CLI, `starter_e2e_mode` |
| `dynamic` | FAQ, отзывы, портфолио, карточки, блог из seed; страница читаема без JS | `pages-map` + `seed/` |
| `seo` | Title Yoast, один граф JSON-LD с ожидаемыми pieces, robots vs `blog_public`, sitemap без `noindex` и лидов, `/llms.txt`, `robots.txt` | `pages-map` |
| `perf-markup` | P2: `defer`/`async` у скриптов, нет `gtag.js` в HTML, ≤ 1 `fetchpriority=high`, размеры и `loading` у `<img>`, LCP / `above_fold` вне `.reveal`, preload шрифтов = конфиг, нет чужих origin в `<head>`, srcset без оригиналов | `pages-map`, конфиг |
| `console` | Нет ошибок консоли, 4xx/5xx и упавших запросов (K9) | `pages-map` |
| `a11y` | axe WCAG A/AA: serious / critical — падение | `pages-map` |
| `visual` | Скриншот прототипа vs WordPress, порог `VISUAL_MAX_DIFF` (0.05) | `pages-map` → `prototype` |
| `smoke` | Firefox + WebKit: `static`, `dynamic` | — |

## Правила

- **Данные — только из `pages-map.json`** и конфига; URL, префикс и имена WordPress (action, nonce, CPT) в тестах не хардкодятся — берутся из `tests/e2e/helpers/config.ts`, чтобы тесты пережили `npm run init`.
- Base URL — `PLAYWRIGHT_BASE_URL` или `urls.local`.
- Классы кейсов на каждый контракт: **happy**, **negative**, **security**.
- Внешние сервисы (Telegram, GA4, карты) — мок / перехват / флаг; нет credentials — `skip` с причиной.
- Тест независим от порядка; что создал (лиды, transients, опции) — удаляет сам.
- Селекторы — БЭМ-классы, роли, лейблы; не позиции и не тексты контента.
- Скриншот и trace — только при падении (`npx playwright show-report`).
- Нарушение, найденное ревью вручную, → новая проверка или тест, а не абзац в правилах.
- Новый набор: проект в `playwright.config.ts`, тег `@<suite>`, скрипт `test:e2e:<suite>`, место в `PHASE_SUITES` / `PAGE_SUITES`.

## Отчёт

Итог фазы 7 — [`project/qa-report.md`](../project/qa-report.md); ручные кейсы проекта сверх автоматических — [`project/test-cases/_template.md`](../project/test-cases/_template.md).
