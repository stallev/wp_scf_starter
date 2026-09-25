---
status: canonical
type: adr
updated: 2026-09-24
---

# ADR 0001: Реестр миграции `/example` → стартер

## Контекст

`/example` — копия реального проекта (тема `taranenkotheme` + mu-plugin `tb-core` + `.cursor/rules` + docs). Из него собирается стартер «WordPress-тема + mu-plugin + SCF» для AI-assisted разработки корпоративных сайтов по HTML-прототипу. После сборки стартера каталог `/example` **удаляется** (он в `.gitignore`, в истории git его нет — восстановить нельзя).

Реестр фиксирует решение по **каждому** файлу/группе. `/example` удаляется только после закрытия всех строк и выполнения §«Критерии удаления».

## Обозначения

### Решения

| Код | Смысл |
|---|---|
| **PORT** | Перенести код и обобщить: префикс, пути, бренд, магические ID → `project.config.json` / опции |
| **KNOW** | Превратить в переносимое знание: `docs/playbooks/*` (без данных клиента, цифры → короткие обезличенные «кейсы» с причиной правила) |
| **TPL** | Превратить в шаблон документа с плейсхолдерами: `docs/contracts/*`, `docs/phases/*`, `docs/project/*` |
| **OPT** | Отключаемый модуль или пример (`modules/<name>`), по умолчанию выключен |
| **FIX** | Взять только идею/структуру; реализацию написать заново |
| **DROP** | Не переносить |

### Плейсхолдеры

| Плейсхолдер | Пример в `/example` |
|---|---|
| `{prefix}` | `tb` (функции `tb_`, хуки, meta, CPT `tb_*`) |
| `{core}` | `tb-core` (mu-plugin) |
| `{theme}` | `taranenkotheme` (каталог и text domain темы) |
| `{PREFIX}` | `TB` (константы, классы `TB_*`) |

Значения задаются в `project.config.json`; `tools/init` делает замену.

### Статус строки

`todo` → `done` (перенесено и проверено) или `n/a` (DROP подтверждён).

---

## 0. Безопасность (до начала переноса)

| # | Действие | Статус |
|---|---|---|
| S1 | Убедиться, что исходный проект (с историей git) хранится вне этого репо | todo |
| S2 | **Сменить** Telegram bot token и пароль WP-админки из `creds/project_creds.json`: файл лежал в web root темы и был публично доступен (см. `ops/pagespeed-insights.md`) | todo |
| S3 | Ни один секрет из `creds/`, `.env` не переносится; в стартер — только `.env.example` | todo |
| S4 | GA4 ID `G-P98DDJKSWQ` (default в коде и доках) — не переносить как default | todo |

---

## 1. Сторонний код и артефакты

| Источник | Решение | Цель / действие | Статус |
|---|---|---|---|
| `plugins/secure-custom-fields/` (6.9.4) | DROP | Ставится wp-env из `project.config.json` → `wordpress.plugins` (`latest` = последняя стабильная) | done (M2) |
| `plugins/wordpress-seo/` (Yoast 28.2) | DROP | Как SCF: `wordpress.plugins` в конфиге (исключает RC из zip без версии) | done (M2) |
| `plugins/duplicator/` | DROP | Не нужен стартеру; перенос сайта — `playbooks/launch.md` (WP-CLI export/search-replace) | todo |
| `plugins/index.php` | DROP | — | n/a |
| `themes/{theme}/node_modules/`, `package-lock.json` | DROP | Lock-файл генерируется заново | n/a |
| `themes/{theme}/psi-reports/*` | DROP | Цифры-вехи уже в журнале `pagespeed-insights.md` → KNOW (см. §6) | n/a |
| `themes/{theme}/test-results/*` | DROP | — | n/a |
| `themes/{theme}/creds/project_creds.json` | DROP | Секреты. См. S2 | todo |
| `themes/{theme}/.env` | DROP | Секреты | todo |
| `themes/{theme}/.env.example` | PORT | → корень: `.env.example` (`PAGESPEED_API_KEY=`, `PLAYWRIGHT_BASE_URL=`). `TELEGRAM_*` не добавлены: credentials бота живут в защищённой WP-опции (M3), не в env | done (M2) |
| `themes/{theme}/.gitignore` | FIX | → корневой `.gitignore`: `.env`, `node_modules/`, `psi-reports/`, `test-results/`, `playwright-report/`, `creds/`, dev-каталоги изображений. Исправить пробел: в исходнике **не было** `.env` | done (M2) |
| `themes/{theme}/assets/images/source-photos/`, `webp-photos/` | DROP | Фото клиента; каталоги остаются как gitignored dev-пути | n/a |
| `themes/{theme}/templates/.gitkeep`, `template-parts/.gitkeep` | DROP | — | n/a |

---

## 2. Правила агентов (`.cursor/rules/`)

| Источник | Решение | Цель в стартере | Что сделать | Статус |
|---|---|---|---|---|
| `general.mdc` | FIX | `AGENTS.md` (+ `CLAUDE.md` = `@AGENTS.md` + роль Claude) | Универсальное (карта, приоритет источников, Context7, секреты, PSI-указатель) → `AGENTS.md`. Пути/URL → из `project.config.json`. Проектное (калькулятор, pricebook, `greifer`) → DROP. Убрать `globs: **/*` при `alwaysApply` | done (M5) |
| `product-doc-alignment.mdc` | FIX | `.claude/commands/doc-align.md` (+ ссылка для Cursor) | Из `alwaysApply` — в команду старта/закрытия фазы. Сохранить порядок источников и блок анти-паттернов `❌`; убрать калькулятор/pricebook | done (M5) |
| `wordpress.mdc` | PORT | `.cursor/rules/mu-plugin.mdc`, `seo.mdc`; security → `AGENTS.md` | escape/sanitize/nonce, без `query_posts`, CPT/tax/options только в mu-plugin, SCF по `name`, transients + инвалидация, Yoast-only title/JSON-LD | done (M5) |
| `php.mdc` | PORT | `.cursor/rules/php.mdc` | Минимум **PHP 8.1** (вместо 7.4), WPCS, strict, early return, PHPDoc на `{prefix}_get_*`, без `@` | done (M5) |
| `testing.mdc` | PORT | `.cursor/rules/tests.mdc` | Glob `**/*.spec.{ts,js}` (исходный не ловил `.ts`); base URL из конфига; данные тестов из `pages-map.json`; моки внешних сервисов | done (M5) |
| `documentation.mdc` | PORT | `.cursor/rules/docs.mdc` | Новые пути `docs/*`; frontmatter; «один факт — одно место»; `check-links` | done (M5) |
| — (нет в исходнике) | FIX | `theme-templates.mdc`, `assets.mdc`, `prototype.mdc` | Новые: данные только через провайдеры (без хардкода NAP/цен/домена); изображения через `{prefix}_image()`; `.reveal` ниже сгиба; БЭМ/токены/`is-*`; скрипт только со `strategy`; `prototype/**` read-only | done (M5) |
| Список запретов (дублирован в `general`, `product-doc-alignment`, `naming-dictionary`, phase-0) | FIX | `docs/contracts/naming.json` | Единый машиночитаемый список `{name, reason, replace}` → генерация таблицы в `naming-dictionary.md` + `tools/check-naming` | done (M5) |

---

## 3. Mu-plugin `tb-core` → `{core}`

| Источник | Решение | Цель | Что сделать | Статус |
|---|---|---|---|---|
| `mu-plugins/tb-core.php` | PORT | `mu-plugins/{core}.php` | Константы `{PREFIX}_VERSION`, `{PREFIX}_CORE_PATH`. `TB_SERVICE_CARD_FALLBACK_ID = 147` → опция в `{prefix}-company` (поле «изображение по умолчанию»), не константа | done (M3) |
| `tb-core/boot.php` | PORT | `{core}/boot.php` | Загрузка модулей по списку из конфига (вкл./выкл. OPT-модулей); flush rewrite по смене версии — сохранить | done (M3) |
| `helpers.php` | PORT (частично) | `{core}/helpers.php` | Generic: `set_by_path`, `money` (формат/валюта → конфиг), `get_company`. Price-path функции (`tb_price_*`, `tb_the_price`) → OPT `pricebook` | done (M3) |
| `defaults.php` | FIX | `{core}/defaults.php` | `tb_default_company` → пустой каркас; `tb_default_ga` → без default ID; `tb_default_pricebook` → OPT `pricebook` | done (M3) |
| `options.php` | PORT | `{core}/options.php` | Options page `{prefix}-company` (generic); `{prefix}-pricebook` → OPT | done (M3) |
| `fields.php` (1120 строк) | FIX | `{core}/fields/*.php` | Разбить по сущностям (один файл = одна field group). Generic: company (NAP, соцсети, GA4, default image), lead, review, project, faq, service card. Проектные (trust, product, pricebook) → OPT/пример. Ключи `group_{prefix}_*` | done (M3) |
| `post-types.php` | FIX | `{core}/post-types.php` | Generic CPT: `{prefix}_lead` (непубличный), `{prefix}_review`, `{prefix}_project`, `{prefix}_faq`. `tb_product` → OPT `catalog`. Список CPT — из конфига | done (M3) |
| `taxonomies.php` | OPT | `modules/catalog` | Family/diameter, thin-slug редиректы на якоря — специфика каталога клиента | todo |
| `queries.php` | PORT (частично) | `{core}/queries.php` | Generic: `get_faqs_for`, `get_reviews`, `get_projects`, `get_service_card_pages`. Product-запросы → OPT `catalog` | done (M3) |
| `forms.php` | PORT | `{core}/forms.php` | Весь контур лида: AJAX action, nonce, honeypot, rate limit, meta, статусы, колонки/фильтр в админке, хук `{prefix}_lead_created`, Telegram-уведомление (credentials из защищённой опции, `autoload=false`) | done (M3) |
| `admin.php` | OPT | `modules/pricebook` | Notice «товар ↔ Pricebook» — специфика | todo |
| `admin-faq.php` + `assets/admin-faq.{css,js}` | PORT | `{core}/admin-faq.php` | Группировка по локациям, drag-reorder, AJAX + nonce | done (M3) |
| `assets/admin-leads.{css,js}` | PORT | `{core}/assets/` | — | done (M3) |
| `assets/admin-acf.css` | PORT | `{core}/assets/` | Проверить, не завязан ли на проектные поля | done (M3) |
| `admin-seed.php` | PORT | `{core}/admin-seed.php` | Tools → Seed (`manage_options` + nonce) | done (M3) |
| `cli.php` | PORT | `{core}/cli.php` | `wp {prefix} seed [--only=]`, `--dry-run` добавить | done (M3) |
| `seed/paths.php`, `loader.php`, `runner.php` | PORT | `{core}/seed/` | Путь к seed — из конфига (корень `seed/`, не тема). `tb_seed_find_forbidden` → читать `naming.json` | partial (M3; чтение запретов из `naming.json` — M6) |
| `seed/mappers.php` | PORT (частично) | `{core}/seed/mappers.php` | Generic `update_field`, `map_company`; pricebook/product-group → OPT | done (M3) |
| `seed/entities.php` (1441 строк) | FIX | `{core}/seed/entities/*.php` | Разбить по сущностям. Generic: upsert по slug, featured image, terms, posts, faq, reviews, projects, service cards, **menus**. Products/pricebook → OPT | done (M3) |
| `pricebook.php`, `calculator.php` | OPT | `modules/pricebook` (пример) | Provider + transients + инвалидация — как **образец** паттерна «Options → provider → cache → JS»; эталонные проверки `tb_run_etalon_checks` — как пример тестируемой бизнес-логики | todo |
| `seo.php` | PORT (частично) | `{core}/seo.php` | Generic: регистрация Yoast graph pieces, Organization, `schema_canonical`, `area_served` (из конфига), noindex + исключение из sitemap для служебных страниц (список из `pages-map`, не `formulas_page`) | done (M3) |
| `seo/class-tb-schema-localbusiness.php` | PORT | `{core}/seo/` | Тип бизнеса, гео, часы — из company options | done (M3) |
| `seo/class-tb-schema-service.php` | PORT | `{core}/seo/` | Service по страницам услуг (признак в `pages-map`) | done (M3) |
| `seo/class-tb-schema-faqpage.php` | PORT | `{core}/seo/` | Из `{prefix}_faq` | done (M3) |
| `seo/class-tb-schema-product-offer.php` | OPT | `modules/catalog` | — | todo |
| `llms-txt.php` | PORT | `{core}/llms-txt.php` | Курируемый `/llms.txt` + AI Allow в `robots.txt`; список ботов → конфиг | done (M3) |
| `data/llms.txt.md` | TPL | `docs/project/llms.txt.md` (шаблон) | Структура H1/blockquote/разделы; контент клиента — DROP | done (M3; отклонение: шаблон в `starter-core/data/llms.txt.md` + фильтр, не в `docs/project/`) |

---

## 4. Тема `taranenkotheme` → `{theme}`

### 4.1 PHP

| Источник | Решение | Цель | Что сделать | Статус |
|---|---|---|---|---|
| `style.css` | PORT | `{theme}/style.css` | Заголовок темы из конфига | done (M4) |
| `functions.php` | FIX | `{theme}/functions.php` + `inc/{setup,assets,images,analytics,head}.php` | Разнести по модулям. Сохранить: supports, menus (`primary/mobile/footer`), отключение site icon, favicon-набор, WebP output + quality 80, размер карточки (из конфига), `filemtime`-версии, `strategy => defer`, font preload (список из конфига), отложенный GA4. `tb_theme_geo_meta` → данные из конфига | done (M4) |
| `header.php`, `footer.php`, `index.php`, `page.php`, `archive.php`, `single.php`, `home.php` | PORT | `{theme}/` | Каркас без контента клиента; `single.php`/`home.php` — блог с TOC, автором, карточками | done (M4) |
| `front-page.php` | FIX | `{theme}/front-page.php` (минимальный) | Контент клиента DROP; оставить скелет: hero (LCP-текст без `.reveal`) + динамические секции через parts | done (M4) |
| `page-*.php` (13 шт.) | DROP | — | Контент клиента. Паттерн «статичная вёрстка + вставки провайдеров» описан в `/port-page`. **Анти-пример** для `check-hardcode`: телефон захардкожен в 8 шаблонах | n/a |
| `archive-tb_product.php`, `single-tb_product.php`, `taxonomy-tb_product_family.php` | OPT | `modules/catalog/templates/` | Пример CPT-архива с фильтрами и якорями | todo |
| `inc/template-tags.php` | FIX | `{theme}/inc/template-tags.php` | Generic: `url`, `home_hash`, `company`, `company_value`, `phone_href`, `brand_mark`, иконки, `has_menu_items`, `normalize_instagram_url`, `user_contactmethods`, `post_reading_minutes`, `get_post_author_data`, `blog_url`, `has_blog`, `blog_filter_categories`. Проектные (`dostavka_*`, `grejfer_*`, `katalog_url`, `family_url`, `price*`, `nav_price_desc`, `product_field`) → DROP/OPT. Добавить `{prefix}_image()` (priority/lazy/sizes/width/height) | done (M4) |
| `inc/post-toc.php` + `assets/js/editor-heading-anchors.js` | PORT | `{theme}/inc/post-toc.php` | Якоря заголовков + TOC | done (M4) |
| `inc/class-tb-walker-nav-{primary,mobile,footer}.php` | PORT | `{theme}/inc/` | Классы `{PREFIX}_Walker_Nav_*`; разметка под БЭМ шапки стартера | done (M4) |
| `inc/setup-pages.php` | FIX | `{core}/seed` (pages) | Создание страниц — по `pages-map.json` в сидере, а не хардкод-списком в теме | done (M3) |
| `template-parts/site-header.php`, `site-footer.php`, `nav-*-fallback.php`, `footer-links-fallback.php` | PORT | `{theme}/template-parts/` | Меню WP + статичный fallback; данные из company | done (M4) |
| `lead-form.php`, `lead-call.php` | PORT | `{theme}/template-parts/` | Контракт формы (`.js-lead`, одна на страницу) | done (M4) |
| `faq.php`, `reviews.php`, `folio.php`, `service-card.php`, `post-card.php`, `post-author.php`, `post-toc.php` | PORT | `{theme}/template-parts/` | Изображения через `{prefix}_image()`; аргументы `priority`, `reveal` | done (M4) |
| `geo-map.php` | PORT | `{theme}/template-parts/embed-facade.php` | Обобщить: карта/видео/виджет по клику, `data-src`, `aspect-ratio`, аргумент `reveal` | done (M4) |
| `trust.php`, `product-card.php`, `content-katalog-*.php` | OPT/DROP | `modules/catalog` (product-card); остальное DROP | — | todo |
| `tools/bootstrap-pages.php`, `tools/run-seed.php`, `tools/verify-seed.php` | FIX | `wp {prefix} seed`, `wp {prefix} seed --verify` | Заменить WP-CLI-командами; вызов без WP-CLI не поддерживать (есть `wp-env`) | partial (M3: `wp starter seed`; `--verify` — M6) |

### 4.2 CSS / JS / статика

| Источник | Решение | Цель | Что сделать | Статус |
|---|---|---|---|---|
| `assets/css/main.css` | FIX | `{theme}/assets/css/main.css` (каркас) | Взять структуру секций и конвенции: FONTS → TOKENS → BASE → LAYOUT → компоненты → брейкпоинты → `PAGE: <NAME>` → УТИЛИТЫ (последней). Generic-компоненты: button, header, form card, faq, lightbox, geo/embed-facade, footer, reveal, is-*. Значения токенов и страничные секции — DROP (их даёт прототип проекта) | done (M4) |
| `assets/js/main.js` | FIX | `{theme}/assets/js/main.js` | Generic IIFE: меню/mobile, reveal, FAQ, lead-форма (AJAX, `tb:lead:success`), слайдер, lightbox, embed-facade по клику. Проектные (калькуляторы, фильтр каталога, реквизиты) → OPT/DROP. Урок: без `fetch('data/*.json')` из прототипа (404 в WP) | done (M4) |
| `assets/js/analytics.js` | PORT | `{theme}/assets/js/analytics.js` | Отложенный gtag (`delayMs` + первое взаимодействие), `dataLayer`, трекинг tel/tg/лида; ID из опций; админы не трекаются | done (M4) |
| `assets/fonts/*` | DROP | — | Шрифты — из прототипа проекта. Правило «только variable woff2 + подмножества» → KNOW | n/a |
| `assets/icons/*` | FIX | `{theme}/assets/icons/` (плейсхолдеры) | Структура набора (ico, svg, apple-touch, 192/512, webmanifest) — плейсхолдеры; графика клиента DROP | done (M4) |
| `assets/images/*.jpg` | DROP | — | Контент клиента | n/a |

### 4.3 Node-инструменты и тесты

| Источник | Решение | Цель | Что сделать | Статус |
|---|---|---|---|---|
| `package.json` | FIX | корневой `package.json` | Скрипты: `images:webp`, `psi`, `test:e2e*`, `gate:*`, `check:*`, `lint:prototype`, `fonts:fallback` | partial (M2–M5: env/check/gate; psi, images, e2e — M6) |
| `scripts/psi.mjs` | PORT | `tools/psi.mjs` | `baseUrl` из `project.config.json` (`production_url`/`staging_url`), пути — `pages-map.json` (`psi: true`), `.env` из корня, отчёты в корневой `psi-reports/`. Сохранить: redact ключа, заголовок `X-Goog-Api-Key`, прогрев с заголовками кэша, медиана, `flaky`, ретраи, коды 0/1/2, отказ на localhost | todo |
| `scripts/psi.config.json` | PORT | `tools/psi.config.json` | Только `thresholds`/`runs`/`mode`/`pauseMs`/`timeoutMs`; `floor` = `null` | todo |
| `scripts/convert-to-webp.mjs` | PORT | `tools/convert-to-webp.mjs` | Пути из конфига | todo |
| `scripts/a11y-smoke.mjs` | FIX | `tests/e2e/a11y.spec.ts` | Перевести в Playwright + `@axe-core/playwright`, URL из `pages-map` | todo |
| `tools/convert-prototype-templates.mjs` | FIX | `.claude/commands/port-page.md` + `tools/port-page` (извлечение `<main>`, замена ссылок по `pages-map`) | Одноразовый скрипт с жёсткой картой → обобщённая утилита на одну страницу | todo |
| `seed/scripts/validate-seeds.mjs` | PORT | `tools/validate-seeds.mjs` | + JSON Schema (`seed/schema/*.json`) + запреты из `naming.json` | todo |
| `playwright.config.ts` | PORT | корневой `playwright.config.ts` | `baseURL` из конфига/env; проекты по suite; smoke Firefox/WebKit | todo |
| `tests/e2e/static-http-200.spec.ts` | PORT | `tests/e2e/static.spec.ts` | Data-driven из `pages-map.json` (200, одна `.js-lead` / её отсутствие) | todo |
| `tests/e2e/navigation.spec.ts` | PORT | `tests/e2e/navigation.spec.ts` | Селекторы стартерной шапки, desktop/mobile | todo |
| `tests/e2e/forms.spec.ts` | PORT | `tests/e2e/forms.spec.ts` | Happy/negative/honeypot/nonce/rate limit; Telegram мок | todo |
| `tests/e2e/seo-sitemap.spec.ts` | PORT | `tests/e2e/seo.spec.ts` | Yoast title, один ld+json graph, sitemap без служебных, noindex, `/llms.txt`, robots AI, geo meta | todo |
| `tests/e2e/perf-markup.spec.ts` | PORT | `tests/e2e/perf-markup.spec.ts` | Данные из `pages-map` (`lcp`, `above_fold`) и конфига (preload шрифтов) | todo |
| `tests/e2e/dynamic.spec.ts` | FIX | `tests/e2e/dynamic.spec.ts` | Generic: FAQ, отзывы, портфолио, service cards; каталог/калькуляторы → OPT | todo |
| — (нового нет) | FIX | `tests/e2e/console.spec.ts`, `visual.spec.ts` | Консоль/Network без 404 и ошибок; визуальный diff «прототип vs WP» | todo |

### 4.4 Seed

| Источник | Решение | Цель | Что сделать | Статус |
|---|---|---|---|---|
| `seed/README.md` | TPL | `seed/README.md` | Правила: имена из словаря, стабильный `slug`, идемпотентность, без секретов | done (M3) |
| `seed/pages-map.json` | FIX | `pages-map.json` (корень) — **центральный манифест** | Схема: `url`, `prototype`, `template`, `type`, `lead_form`, `noindex`, `schema`, `lcp`, `above_fold`, `psi`, `specs` | done (M2–M4: схема, валидатор, демо-страницы) |
| `seed/company.json`, `faq.json`, `reviews.json`, `projects.json`, `posts.json`, `terms.json` | TPL | `seed/*.json` (пустые примеры) + `seed/schema/*.json` | Структура → JSON Schema; данные клиента DROP | partial (M3: нейтральные демо-данные; JSON Schema — M6) |
| `seed/products.json`, `pricebook.json`, `yoast-meta.json` | OPT/TPL | `modules/*/seed/`; `yoast-meta` → шаблон | — | todo |

### 4.5 Прототип

| Источник | Решение | Цель | Что сделать | Статус |
|---|---|---|---|---|
| `prototype/*` | FIX | `fixtures/demo-prototype/` (3–4 страницы) | **Не копировать** контент клиента. Собрать нейтральную мини-фикстуру по регламенту (главная, услуга, контакты, пост) для самотестов стартера. Реальный прототип проекта кладётся в корневой `prototype/` (вне темы — не уезжает на прод) | todo |
| `prototype/README.md`, `vercel.json` | KNOW | `docs/playbooks/prototype-rules.md` | Деплой прототипа на Vercel (Root Directory, абсолютные пути) | todo |
| `prototype/data/prices.json` + `assets/js/prices.js` | OPT | `modules/pricebook` | Паттерн «единый JSON цен → `data-price*` → JSON-LD → калькулятор» | todo |
| `README.md` (корень темы, о прототипе) | KNOW | `docs/playbooks/prototype-rules.md`, `playbooks/launch.md` | «Перед публикацией»: снять noindex, домен в canonical/OG/JSON-LD, og-cover 1200×630, favicon, свои фото | todo |

---

## 5. Документация `docs/`

### 5.1 `docs/general/` и контент клиента

| Источник | Решение | Цель | Что сделать | Статус |
|---|---|---|---|---|
| `general/reglament-koda-prototipa.md` | KNOW | `docs/playbooks/prototype-rules.md` | Файлы, токены, БЭМ без вложенности, `is-*`, DRY-лестница, префиксы, формы; + требования perf (первый экран без `.reveal`, размеры `<img>`, без Google Fonts, фасады). Машинно проверяемое → `lint:prototype` | todo |
| `general/checklist-proverki-struktury-kontenta.md` | TPL | `docs/project/_templates/page-spec-checklist.md` | — | todo |
| `general/01-sitemap-i-seo-pozicionirovanie.md` | TPL | `docs/project/sitemap.md` (шаблон) | Структура: карта, приоритеты, журнал решений | todo |
| `general/03-seo-strategiya.md` | TPL | `docs/project/seo-strategy.md` (шаблон) | — | todo |
| `general/cenoobrazovanie-i-formuly-rascheta.md` | DROP | — | Бизнес-логика клиента; как пример — только в `modules/pricebook/README` | n/a |
| `pages_descriptions_specs/*` (22 файла) | TPL | `docs/project/pages/_template.md` | Шаблон спецификации страницы (по 1–2 лучшим образцам) | todo |
| `seo-specs/*` (+ `posts-content/*`) | TPL/DROP | `docs/project/seo/_templates/` (meta-паттерны, outline поста) | Контент DROP | todo |

### 5.2 `docs/wp-docs/`

| Источник | Решение | Цель | Что сделать | Статус |
|---|---|---|---|---|
| `README.md` | FIX | `docs/INDEX.md` | Навигация; «как работать агенту» → `AGENTS.md` (без дубля) | todo |
| `DOCUMENTATION-STRUCTURE.md` | FIX | `docs/INDEX.md` | Правило приоритета (Project → Canonical → Decisions), статусы `canonical/planned/superseded`. Единственное место этого правила | todo |
| `contracts/naming-dictionary.md` | TPL | `docs/contracts/naming-dictionary.md` + `naming.json` | Структура: канон / запреты / устаревшее→канон. Базовые generic-имена стартера заполнены | done (M5: генерируется из `naming.json`) |
| `contracts/prototype-to-templates.md` | FIX | `pages-map.json` + `docs/contracts/template-parts.md` | Таблица маппинга → манифест; список parts → контракт | todo |
| `contracts/data-structures.md` | TPL | `docs/contracts/data-structures.md` | Сущности и источники истины (company vs pages vs CPT) | todo |
| `contracts/form-contracts.md` | PORT | `docs/contracts/forms.md` | Generic как есть (с `{prefix}`) | todo |
| `contracts/seo-contract.md` | PORT | `docs/contracts/seo.md` | Yoast vs custom pieces | todo |
| `contracts/image-assets-contract.md` | PORT | `docs/contracts/images.md` | Размер карточки, WebP, alt/fallback, процедура регенерации и отката | todo |
| `contracts/catalog-url-model.md`, `pricebook-schema.md` | OPT | `modules/catalog/`, `modules/pricebook/` README | — | todo |
| `specs/architecture.md` | KNOW | `docs/playbooks/architecture.md` | Принципы: mu-plugin = данные, тема = представление, provider + cache, enqueue только в теме | todo |
| `specs/mu-plugin-spec.md`, `theme-spec.md` | TPL | `docs/contracts/mu-plugin.md`, `theme.md` | Спеки стартера (модули, API, хуки) | todo |
| `specs/analytics-spec.md` | KNOW | `docs/playbooks/analytics.md` | Отложенный GA4, события, компромиссы, диагностика «gtag не грузится» | todo |
| `specs/blog-spec.md` | PORT | `docs/contracts/blog.md` | `post` + `category`, шаблоны, TOC | todo |
| `specs/calculator-spec.md` | OPT | `modules/pricebook/` | — | todo |
| `prds/forms-prd.md`, `blog-prd.md`, `content-management-prd.md` | TPL | `docs/project/prds/_template.md` + базовые PRD стартера | — | todo |
| `prds/product-catalog-prd.md`, `calculator-prd.md` | OPT/DROP | — | — | todo |
| `phases/ROADMAP.md` | FIX | `docs/phases/ROADMAP.md` | 8 фаз нового пайплайна; gate = команда `npm run gate:N` + `/doc-align` + `/phase-review` | todo |
| `phases/phase-0…11-*.md` | TPL | `docs/phases/phase-0…7-*.md` | Формат «Связанные / Задачи / AC Happy-Negative-Security / Gate». Содержание: 0 init, 1 приёмка прототипа (+ `pages-map`), 2 модель данных (+ seed ← бывш. 1, 6), 3 ядро (← 2), 4 оболочка темы (← 3, 4, шрифты), 5 перенос страниц (← 5, 10), 6 динамика/формы/SEO/AI (← 7, 8, 11), 7 QA и запуск (← 9, PSI baseline) | todo |
| `ops/performance-optimization.md` | KNOW | `docs/playbooks/performance.md` | Принципы, 9 разделов (шрифты, render-blocking, `.reveal`, LCP/lazy, GA4, фасады, остатки прототипа, WebP), чеклист блока, «не делать». Цифры → обезличенные кейсы | todo |
| `ops/pagespeed-insights.md` | KNOW | `docs/playbooks/psi.md` + `docs/project/perf-log.md` (шаблон журнала) | Процедура, ключ, пороги и ввод `floor`, чтение ответа Lighthouse 13, «не делать». Журнал клиента DROP (кроме 1–2 обезличенных кейсов) | todo |
| `ops/manager-guide.md` | TPL | `docs/project/manager-guide.md` (шаблон) | Раздел про company/FAQ/лиды/меню generic; pricebook → OPT | todo |
| `testing/test-strategy.md` | PORT | `docs/contracts/testing.md` | Стек, классы кейсов, матрица браузеров, правила агента; base URL из конфига | todo |
| `testing/test-cases/*` | TPL | `docs/project/test-cases/_template.md` | Формат кейса; generic-кейсы nav/static/forms — как готовые | todo |
| `testing/phase-9-qa-report.md` | TPL | `docs/project/qa-report.md` (шаблон) | — | todo |
| `drafts/archive/*` | DROP | — | Заменено ADR-подходом (`docs/decisions/`) | n/a |

---

## 6. Знания, которые нельзя потерять (сквозной чек-лист)

Каждый пункт должен быть найден в стартере (код, проверка или playbook) до удаления `/example`.

| # | Знание | Источник | Где в стартере | Статус |
|---|---|---|---|---|
| K1 | `.reveal` на LCP-узле → render delay 4–5.7 с | performance §4 | `theme-templates.mdc` + `perf-markup.spec.ts` + `playbooks/performance.md` | partial (M4: `.reveal` только ниже сгиба + no-JS fallback; проверка — M6) |
| K2 | Зависимый скрипт без `strategy` делает родителя блокирующим | performance §3 | `assets.mdc` + `perf-markup` (итоговый HTML) | partial (M4: все скрипты `defer`, включая admin-bar; проверка — M6) |
| K3 | WP сам вешает `fetchpriority=high` на первую «большую» картинку без `loading` | performance §5 | `{prefix}_image()` всегда ставит явный `loading` | done (M4: `starter_image()` всегда ставит `loading`) |
| K4 | Fallback-метрики шрифта мерить по реальному тексту, не по файлу | performance §2 | `tools/font-fallback-metrics.mjs` | todo |
| K5 | Preload только 4 критичных woff2, `crossorigin` обязателен | performance §2 | Конфиг + `perf-markup` | partial (M4: механизм preload из конфига; проверка — M6) |
| K6 | PSI не видит localhost; lab ≠ CrUX; TTFB PSI ≠ TTFB из своей сети; UI ≠ API | psi, performance §1 | `playbooks/psi.md`, отказ `psi.mjs` на localhost | todo |
| K7 | Фиксировать GA4 on/off в каждом прогоне | psi | `psi.mjs` (детект) + `psi.md` | todo |
| K8 | Не чинить аудит внутри зелёной категории | psi | `AGENTS.md` (1 строка) + `psi.md` | done (M5: `AGENTS.md` + `/psi-analyze`) |
| K9 | `fetch('data/*.json')` из прототипа → 404 в WP | performance §8 | `console.spec.ts` | partial (M4: нет fetch данных прототипа; console-spec — M6) |
| K10 | Ключ PSI: только заголовок, redact, `.env` UTF-8 без BOM (PowerShell 5.1), Git Bash искажает `/`-аргументы (`MSYS_NO_PATHCONV=1`) | psi | `psi.md`, `.env.example` | todo |
| K11 | WebP: оригиналы не трогать (og:image), регенерация старых вложений — отдельная процедура с бэкапом | performance §9, images | `contracts/images.md`, `playbooks/launch.md` | done (M4: оригиналы остаются PNG/JPEG, подразмеры WebP, srcset без оригиналов) |
| K12 | MU-plugin без activation hook → flush rewrite по смене версии | boot.php | `{core}/boot.php` | done (M3) |
| K13 | SCF: `get_field( $name, 'option' )` по **name**, не key | wordpress.mdc | `mu-plugin.mdc` | done (M5: `mu-plugin.mdc`) |
| K14 | Одна `.js-lead` форма на страницу; служебные страницы без формы и в noindex | form-contracts | `pages-map` + `static.spec.ts` | partial (M4: `starter_page_has_lead_form()` по pages-map; static-spec — M6) |
| K15 | Хостинг: page cache, `Cache-Control` HTML, `immutable` для assets/uploads | performance «Что осталось» | `playbooks/launch.md` | todo |
| K16 | Дубли запретов расходятся → один машиночитаемый источник | анализ rules | `naming.json` + `check-naming` | done (M5: `naming.json` + `check:naming`) |
| K17 | Данные в шаблонах хардкодятся, если нет проверки | анализ шаблонов | `check-hardcode` | todo |
| K18 | Phase review — отдельным агентом/моделью, не тем, что реализовал | ROADMAP | `/phase-review` (Claude) | partial (M5: `/phase-review`; ROADMAP-гейты — M7) |

---

## 7. Критерии удаления `/example`

- [ ] Все строки §0–§6 в статусе `done` или `n/a`.
- [ ] `grep -riE "example/|taranenko|kanalizacia|tb_|tb-core|http://taranenko|G-P98DDJKSWQ"` по стартеру (кроме этого ADR) — пусто.
- [ ] `tools/check-links` — нет битых ссылок в `docs/`, `AGENTS.md`, `.cursor/rules/`, `.claude/`.
- [ ] `check-rules-coverage` — у каждого исходного файла есть применимое правило.
- [ ] Самотест на `fixtures/demo-prototype/`: `init` → `wp-env start` → фазы 0–7, все `gate:*` зелёные, `perf-markup` зелёный.
- [ ] `psi.mjs --help` работает; на localhost — код 2.
- [ ] Ревью стартера против этого реестра — отдельной моделью (Claude), блокеры закрыты.
- [ ] Выполнены S1–S2.
- [ ] Стартер закоммичен и помечен тегом `v0.1.0`.

## Последствия

- Проектные бизнес-модули (каталог, pricebook/калькулятор) живут как отключаемые примеры; по умолчанию стартер — корпоративный сайт: страницы, услуги, блог, FAQ, отзывы, портфолио, лиды.
- Знание о производительности хранится в трёх формах одновременно: код по умолчанию, автоматическая проверка, playbook с причинами.
- Этот ADR после удаления `/example` остаётся как история решений (`status: canonical`, дальше не меняется; новые решения — новые ADR).
