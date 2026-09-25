# WP SCF Starter

Стартер для многостраничных корпоративных сайтов на WordPress: классическая PHP-тема + mu-plugin + Secure Custom Fields + Yoast. Сайт собирается из готового HTML-прототипа с помощью AI (Cursor, Claude).

Статус: **M7 — документация стартера** (контракты, playbooks, фазы проекта, шаблоны `docs/project/`). План и milestones — [`docs/STARTER-PLAN.md`](docs/STARTER-PLAN.md), карта документации — [`docs/INDEX.md`](docs/INDEX.md).

## Требования

- Node.js ≥ 24 (см. `.nvmrc`; `@wordpress/env` тянет пакеты, требующие Node 24)
- Docker Desktop (для `wp-env` и, если нет локального PHP, для Composer)
- Опционально: локальные PHP 8.1+ и Composer — тогда `npm run composer` использует их вместо Docker

## Быстрый старт

```bash
npm install
npm run env:start
```

Сайт: http://localhost:8888, админка: http://localhost:8888/wp-admin (`admin` / `password`).
Версии WordPress и плагинов (SCF, Yoast) берутся из `project.config.json` → `wordpress`: `latest` = последняя **стабильная** версия с api.wordpress.org (не RC/trunk), либо точная версия для фиксации. `tools/wp-env.mjs` записывает их в `.wp-env.override.json` (в `.gitignore`). Чтобы подтянуть новые версии — `npm run env:update`. Список `plugins` в `.wp-env.json` — только запасной вариант: при старте его целиком заменяет `.wp-env.override.json`, поэтому новые плагины добавляйте в `project.config.json`.

После старта `tools/env-setup.mjs` активирует тему, ставит постоянные ссылки `/%postname%/`, locale из конфига, запрещает индексацию и удаляет демо-плагины.

```bash
npm run composer -- install
npm run gate:0
```

## Команды

Полная таблица npm-скриптов — [`AGENTS.md` → «Команды»](AGENTS.md#команды) (единственное место).

## Структура

```
project.config.json   параметры проекта (prefix, slug-и, URL, шрифты, модули)
pages-map.json        манифест страниц
schemas/              JSON Schema для конфигов
prototype/            HTML-прототип проекта (read-only для агента)
seed/                 JSON для импорта контента (в контейнере: wp-content/starter-seed), seed/schema/ — JSON Schema
fixtures/             demo-prototype/ (самотест стартера), bad-prototype/ (негативный тест lint:prototype)
wp-content/
  mu-plugins/         starter-core.php + starter-core/ (данные: CPT, SCF, формы, seed, SEO)
  themes/starter/     тема
tools/                node-скрипты: окружение, init, check-*, validate-seeds, lint-prototype, psi, font-fallback-metrics, convert-to-webp, gate; самотесты — tools/__tests__/
tests/e2e/            Playwright-наборы (static, navigation, forms, dynamic, seo, perf-markup, console, a11y, visual), конфиг — playwright.config.ts; команды — AGENTS.md
AGENTS.md, CLAUDE.md  правила для AI-агентов; .cursor/rules/ и .claude/commands/ — правила зон и процедуры
docs/                 документация (см. docs/INDEX.md)
```

**Демо-контент.** 9 страниц в `pages-map.json` (`/`, `/services/…`, `/about/`, `/contacts/`, `/blog/`, демо-пост, `/privacy-policy/`, у всех `prototype: null`) и всё содержимое `seed/` (включая `seed/images/`) — нейтральные демо-данные для самотеста темы. На реальном проекте `npm run init -- --prefix=… --name=…` очищает их (пустой `pages-map`, скелеты `seed/*.json`) — дальше страницы из прототипа и данные клиента.

`node-html-parser` (devDependency) — парсер HTML для `lint:prototype` и проверки разметки производительности `tests/e2e/perf-markup.spec.ts`. `@playwright/test` — Chromium для `fonts:fallback` и e2e (`npx playwright install chromium`; для `test:e2e:smoke` — ещё `firefox webkit`), `@axe-core/playwright` — `a11y`, `pixelmatch` + `pngjs` — `visual`, `sharp` — `images:webp`, `fontkit` — метрики шрифта.

Комментарии отключены ядром (`starter-core/comments.php`, фильтр `starter_disable_comments`, по умолчанию `true`); `env-setup` удаляет стандартные «Hello world!» и «Sample Page».

Имена `starter` / `starter-core` / префикс `starter_` — плейсхолдеры; на проекте их один раз заменяет `npm run init -- --prefix=acme --name="Acme"` (`--dry-run` — показать план). После init — `npm run env:start` (путь темы и маппинг seed изменились) и `npm run gate:0`.

## Правила для AI

Единый источник правил для Cursor и Claude — [`AGENTS.md`](AGENTS.md) (карта, инварианты, цикл задачи, команды). [`CLAUDE.md`](CLAUDE.md) импортирует его и задаёт роль Claude; правила зон — [`.cursor/rules/`](.cursor/rules/) по glob; процедуры — команды в [`.claude/commands/`](.claude/commands/) (`/doc-align`, `/phase-review`, `/port-page`, `/psi-analyze`). Имена — [`docs/contracts/naming.json`](docs/contracts/naming.json).

## Известные особенности окружения

- **DNS за VPN.** wp-env проверяет сеть через `dns.resolve`, который за некоторыми VPN-резолверами зависает, и тогда wp-env считает себя офлайн. `tools/dns-fallback.cjs` подменяет проверку на системный резолвер; подключается обёрткой автоматически.
- **Первый старт** скачивает образы и WordPress (5–10 минут). Если первый запуск упал с «Error establishing a database connection» — MySQL не успел подняться, повторите `npm run env:start`.
- **npm 11** блокирует install-скрипт `fs-ext-extra-prebuilt` (зависимость php-wasm): для Docker-окружения он не нужен.
- **Composer в Docker** использует образ `composer:2` с актуальным PHP; совместимость с PHP 8.1 проверяет PHPCompatibility, а PHPStan анализирует с `phpVersion: 80100`.

## Безопасность форм

Лимит заявок (`starter_submit_lead`) считается по `REMOTE_ADDR` (хеш, фильтр `starter_lead_client_ip`). За прокси/балансировщиком реальный IP должен подставлять веб-сервер (`mod_remoteip`, nginx `real_ip`), доверяя `X-Forwarded-For` **только** от адресов самого балансировщика — иначе клиент подменит IP и обойдёт лимит. Образ wp-env доверяет приватным диапазонам — на прод это не переносить.

## Данные и очистка

Что ядро `starter-core` создаёт в базе помимо CPT и SCF-полей (удалить при сносе ядра):

| Где | Что |
|---|---|
| опция `starter_rewrite_version` | Версия, при которой сброшены rewrite-правила (autoload) |
| опция `starter_telegram_bot` | `{ token, chat_id }` бота, autoload выключен; редактируется в «Заявки → Telegram» |
| опции `options_starter_company_*` | Поля страницы «Компания» (SCF) |
| transients `starter_lead_rl_*` | Счётчики лимита заявок (хеш IP, живут 10 мин) |
| transients `starter_seed_report_*` | Отчёт последнего запуска seed из админки (5 мин) |
| meta `_starter_seed_source` (вложения) | Источник картинки из seed — для повторного использования |
| term meta `_starter_seed_hash` (меню) | Хеш пунктов меню из seed — меню пересобирается только при изменении |
| theme mod `nav_menu_locations` | Области меню, назначенные seed |

## Секреты

Только в `.env` (см. `.env.example`), он в `.gitignore`. В репозитории — никаких токенов, паролей и ключей.
