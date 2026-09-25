# AGENTS.md — правила для AI-агентов (Cursor, Claude)

Единый источник правил (L0). Cursor читает этот файл сам; Claude — через [`CLAUDE.md`](CLAUDE.md). Правила зон (L1) — [`.cursor/rules/*.mdc`](.cursor/rules/) по glob; контракты и playbooks (L2) — по ссылке из задачи; phase-файл / задача (L3) — явно при старте. Здесь — только инварианты, карта и цикл; HOW живёт в контрактах, playbooks и командах.

**Приоритет источников и статусы документов** — только в [`docs/INDEX.md`](docs/INDEX.md). Не пересказывать, ссылаться.

## Плейсхолдеры

`starter` (тема, text domain), `starter-core` (mu-plugin), `starter_` / `STARTER_` / `Starter_` (префикс функций, хуков, meta, опций, CPT, констант, классов) — имена стартера. На проекте их заменяет `npm run init -- --prefix=… --name=…` (`tools/init.mjs`, один раз, на фазе 0). Новые имена пишутся с текущим префиксом.

## Карта

| Путь | Что |
|---|---|
| `project.config.json` | Префикс, slug-и, URL окружений, шрифты (preload), изображения, аналитика, модули |
| `pages-map.json` | Манифест страниц: URL → прототип → шаблон → `lead_form` / `noindex` / `schema` / `lcp` / `above_fold` / `psi` |
| `prototype/` | HTML-прототип проекта — **read-only вход** |
| `seed/` | JSON демо/клиентских данных для `wp starter seed`, формат — [`seed/README.md`](seed/README.md), JSON Schema — `seed/schema/` |
| `fixtures/` | `demo-prototype/` — нейтральный прототип для самотестов стартера; `bad-prototype/` — заведомо плохой (негативный тест `lint:prototype`) |
| `wp-content/mu-plugins/starter-core.php`, `starter-core/` | Данные и логика: CPT, SCF-поля и опции, формы/лиды, seed, Yoast-схемы, `llms.txt` |
| `wp-content/mu-plugins/starter-core/config.generated.php` | Снимок конфигов для PHP (генерируется, не редактировать) |
| `wp-content/themes/starter/` | Представление: `inc/` (setup, assets, images, analytics, head), шаблоны, `template-parts/`, `assets/` |
| `docs/` | Документация, карта — [`docs/INDEX.md`](docs/INDEX.md); словарь имён — [`docs/contracts/naming-dictionary.md`](docs/contracts/naming-dictionary.md) |
| `tools/` | Node-скрипты проверок и окружения (`lib.mjs` — общие хелперы) |
| `.cursor/rules/` | L1-правила по glob · `.claude/commands/` — процедуры (`/doc-align`, `/phase-review`, `/port-page`, `/psi-analyze`) |

## Инварианты

1. **Mu-plugin = данные, тема = представление.** CPT, taxonomies, options, SCF-поля, формы, SEO-схемы — только в `starter-core`. Enqueue front-end, шаблоны, parts — только в теме.
2. **Данные в шаблонах — только через API** `starter_get_*()` / `starter_company_value()` / `starter_page_config()`. Телефоны, адреса, цены, домен, ID аналитики не хардкодятся.
3. **Имена — только из словаря.** Канон и запреты — [`docs/contracts/naming.json`](docs/contracts/naming.json) (таблица генерируется, проверка — `npm run check:naming`). Новое имя: сначала `naming.json`, потом код.
4. **SCF** — группы полей в PHP (один файл = одна группа), чтение и запись по `name`, не по key.
5. **SEO** — title, meta и JSON-LD только через Yoast (свои сущности — graph pieces в `starter-core/seo/`). `noindex` и исключение из sitemap — флагом в `pages-map.json`.
6. **Лид-форма** — ровно одна `form.js-lead` на странице с `lead_form: true`, ни одной на служебных; контракт — шапка `starter-core/forms.php`.
7. **Производительность (P1):** `.reveal` только ниже первого экрана; LCP-узел из `pages-map` (`lcp`, `above_fold`) без `.reveal`; каждый скрипт со `strategy` (`starter_script_args()`); изображения только через `starter_image()` (одна LCP-картинка с `priority`); тяжёлые встраивания — `template-parts/embed-facade.php`; без внешних CDN шрифтов.
8. **`prototype/` — read-only.** Прототип читают и переносят, но не правят.
9. **Секреты** — только в `.env` (шаблон `.env.example`) или защищённых опциях (`autoload=false`). Не выводить в чат/логи, не коммитить.
10. **Конфиг:** после правки `project.config.json` или `pages-map.json` — `npm run build:config` (PHP читает только снимок); `check:config` ловит несвежий снимок.
11. **Сначала документ, потом код.** Расхождение кода с контрактом исправляется правкой документа с датой (см. [`docs/INDEX.md`](docs/INDEX.md)), не молча.

## Цикл задачи

1. **Контекст:** запись `pages-map.json` / контракт / phase-файл задачи; L1-правила зоны подтянутся по glob.
2. **Реализация** в своей зоне (mu-plugin или тема), имена — из словаря.
3. **Проверка:** релевантная gate-команда (ниже); красный gate = задача не готова.
4. **Ревью:** независимое (`/phase-review` другой моделью) для фазы; `/doc-align` при старте и закрытии фазы.
5. **Коммит** — только при зелёном gate; одна страница / одна задача — один коммит.

Нарушение, которое агент совершил дважды, становится автоматической проверкой, а не новым абзацем.

## Команды

| Команда | Что |
|---|---|
| `npm run env:start` / `env:stop` | Поднять / остановить wp-env (http://localhost:8888, `admin` / `password`) |
| `npm run env:update` | Перечитать версии WP и плагинов из конфига, обновить, перезапустить |
| `npm run env:clean` / `env:destroy` / `env:logs` | Сбросить базы / удалить контейнеры и данные / логи |
| `npm run wp -- <args>` | WP-CLI через `docker exec` (секунды вместо ~90 с у `wp-env run`) |
| `npm run wp -- starter seed` | Импорт `seed/*.json` (`--only=`, `--dry-run`), формат — [`seed/README.md`](seed/README.md) |
| `npm run composer -- <args>` | Composer: локальный или Docker-образ `composer:2` |
| `npm run build:config` | Снимок `project.config.json` + `pages-map.json` → `starter-core/config.generated.php` (корень репо не деплоится) — после каждой правки конфигов |
| `npm run check:config` | Схемы и перекрёстные правила конфигов, свежесть снимка |
| `npm run build:naming` | `naming.json` → `naming-dictionary.md` |
| `npm run check:naming` | Схема `naming.json`, свежесть словаря, наличие канонических функций/констант/классов/хуков в коде, запрещённые имена |
| `npm run check:links` | Относительные ссылки (точный регистр) в docs, `AGENTS.md`, `CLAUDE.md`, `README.md`, `.cursor/`, `.claude/` |
| `npm run check:rules` | Каждый исходный и конфиг-файл покрыт `.mdc`; формат и длина правил |
| `npm run gate:rules` | `check:naming` + `check:links` + `check:rules` |
| `npm run lint:php` | parallel-lint + PHPCS (WPCS, PHPCompatibility 8.1+) + PHPStan |
| `npm run lint:php:fix` | Автоисправление PHPCS |
| `npm run init -- --prefix=acme --name="Acme"` | Фаза 0 (чистое дерево, wp-env остановлен): заменить плейсхолдеры `starter*` (файлы, каталоги темы и ядра), очистить демо-данные (`--keep-demo` — оставить), `--dry-run` — только план |
| `npm run check:hardcode` | Данные компании из `seed/company.json`, цены карточек, свой домен, `tel:`/`mailto:` литералами в теме; исключение — `hardcode:allow` в строке |
| `npm run check:seeds` | `seed/*.json`: JSON Schema, уникальные slug, картинки, ссылки меню/карточек на `pages-map`, секреты, запреты `naming.json` |
| `npm run check:seed-idempotent` | Два прогона `wp starter seed` в запущенном wp-env, второй без изменений |
| `npm run lint:prototype [-- --dir=<каталог>]` | Машинно проверяемые правила прототипа (по умолчанию `prototype/`): inline-стили, `on*=`, внешние CSS/шрифты, `<img>` без размеров/`alt`, один `h1`, `form.js-lead`, LCP / первый экран вне `.reveal` |
| `npm run fonts:fallback -- --font=<woff2> --family="Name"` | `@font-face` fallback с `size-adjust` и `*-override`, измеренными в Chromium на реальном тексте; статичные файлы — парами `--font=r.woff2@400,b.woff2@700` |
| `npm run images:webp` | `assets/images/source-photos/` темы → `webp-photos/` (Sharp, качество из конфига) |
| `npm run psi -- [--paths=…]` | PSI задеплоенного сайта (см. «PSI» ниже): localhost → код 2, отчёт — `psi-reports/` |
| `npm run test:tools` | Самотесты `tools/__tests__/` (node --test), включая негативные случаи |
| `npm run gate:0` | `check:config` + `gate:rules` + `check:hardcode` + `check:seeds` + `lint:php` |
| `npm run gate:1` … `gate:7` | Gate фазы проекта (1 прототип, 2 модель данных, 3 ядро + идемпотентный seed, 4–7 — статические проверки; их e2e-наборы добавит M6b) |
| `npm run gate:page -- <url>` | Gate страницы: URL из `pages-map`, `check:config` + `check:hardcode` + `lint:php`; e2e страницы — M6b |

E2E (Playwright, `tests/e2e/`) появятся в M6b; `gate:4`–`gate:7` и `gate:page` пока только называют свои наборы и не запускают их.

## PSI

Только по явному запросу пользователя и только на задеплоенном публичном URL: `npm run psi` (база — `urls.production` или `--base`, пути — `psi: true` в `pages-map`, ключ `PAGESPEED_API_KEY` — в `.env`); процедура — `docs/playbooks/psi.md` (M7), анализ отчёта — `/psi-analyze`. Код чинится, только если категория вышла из зелёной зоны **и** пользователь попросил исправить.

## Инструменты

- **Context7** — для API WordPress, SCF, Yoast, WP-CLI, Playwright вместо ответов «по памяти».
- **Роли:** Cursor — реализация; Claude — план фазы, независимое ревью, сверка с документацией, анализ PSI (см. [`CLAUDE.md`](CLAUDE.md)).
