# WP SCF Starter

Стартер для многостраничных корпоративных сайтов на WordPress: классическая PHP-тема + mu-plugin + Secure Custom Fields + Yoast. Сайт собирается из готового HTML-прототипа с помощью AI (Cursor, Claude).

Статус: **M2 — каркас**. План и milestones — [`docs/STARTER-PLAN.md`](docs/STARTER-PLAN.md), карта документации — [`docs/INDEX.md`](docs/INDEX.md).

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

| Команда | Что делает |
|---|---|
| `npm run env:start` / `env:stop` | Поднять / остановить WordPress (wp-env) |
| `npm run env:update` | Перечитать версии из конфига, скачать обновления, перезапустить |
| `npm run env:clean` | Сбросить базу обоих окружений |
| `npm run env:destroy` | Удалить контейнеры и данные окружения |
| `npm run env:logs` | Логи контейнеров |
| `npm run wp -- <args>` | WP-CLI внутри окружения, например `npm run wp -- plugin list`. Идёт напрямую через `docker exec` (секунды вместо ~90 с у `wp-env run`) |
| `npm run composer -- <args>` | Composer (локальный или Docker-образ `composer:2`) |
| `npm run check:config` | Валидация `project.config.json` и `pages-map.json` по схемам + перекрёстные правила |
| `npm run lint:php` | php-parallel-lint + PHPCS (WPCS, PHPCompatibility 8.1+) + PHPStan |
| `npm run lint:php:fix` | Автоисправление PHPCS |
| `npm run gate:0` | Gate фазы 0: конфиг + PHP-линт |

## Структура

```
project.config.json   параметры проекта (prefix, slug-и, URL, шрифты, модули)
pages-map.json        манифест страниц
schemas/              JSON Schema для конфигов
prototype/            HTML-прототип проекта (read-only для агента)
seed/                 JSON для импорта контента
wp-content/
  mu-plugins/         starter-core.php (+ starter-core/ в M3)
  themes/starter/     тема
tools/                node-скрипты (composer, env-setup, validate-config, …)
docs/                 документация (см. docs/INDEX.md)
```

Имена `starter` / `starter-core` / префикс `starter_` — плейсхолдеры; на проекте их заменит `tools/init` (M6) по `project.config.json`.

## Известные особенности окружения

- **DNS за VPN.** wp-env проверяет сеть через `dns.resolve`, который за некоторыми VPN-резолверами зависает, и тогда wp-env считает себя офлайн. `tools/dns-fallback.cjs` подменяет проверку на системный резолвер; подключается обёрткой автоматически.
- **Первый старт** скачивает образы и WordPress (5–10 минут). Если первый запуск упал с «Error establishing a database connection» — MySQL не успел подняться, повторите `npm run env:start`.
- **npm 11** блокирует install-скрипт `fs-ext-extra-prebuilt` (зависимость php-wasm): для Docker-окружения он не нужен.
- **Composer в Docker** использует образ `composer:2` с актуальным PHP; совместимость с PHP 8.1 проверяет PHPCompatibility, а PHPStan анализирует с `phpVersion: 80100`.

## Секреты

Только в `.env` (см. `.env.example`), он в `.gitignore`. В репозитории — никаких токенов, паролей и ключей.
