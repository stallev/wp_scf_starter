---
status: canonical
version: 1.0
updated: 2026-09-24
---

# План разработки стартера: принципы и milestones

Стартер = WordPress-тема + mu-plugin + SCF для многостраничных корпоративных сайтов, которые собираются из готового HTML-прототипа с помощью AI (основной инструмент — Cursor, дополнительный — Claude Desktop / Claude Code).

Документ фиксирует решения, принятые при проектировании. Пофайловый перенос из `/example` — [`decisions/0001-example-migration.md`](decisions/0001-example-migration.md).

---

## 1. Исходные данные и цель

- **Вход проекта:** HTML-прототип (заранее создан, read-only для агента) + спецификации страниц + SEO-семантика.
- **Выход проекта:** классическая PHP-тема + mu-plugin с данными на SCF, Yoast для SEO, e2e-тесты, замеры PSI после деплоя.
- **Основа стартера:** реальный проект в `/example` (тема `taranenkotheme`, mu-plugin `tb-core`, `.cursor/rules`, ~4 250 строк документации). После сборки стартера `/example` **удаляется**.
- **Главный риск:** стартер превратится в копию одного проекта. Поэтому всё проектное параметризуется или выносится.

---

## 2. Принципы

### 2.1 Архитектура

1. **Mu-plugin = данные и логика, тема = представление.** CPT, taxonomies, options, SCF-поля, формы, SEO-схемы — только в mu-plugin. Enqueue, шаблоны, parts — только в теме.
2. **Данные в шаблонах — только через провайдеры** `{prefix}_get_*()`. Телефоны, адреса, цены, домен в шаблонах не хардкодятся (в примере телефон оказался захардкожен в 8 шаблонах при наличии `tb_get_company()`).
3. **Поля SCF описываются в PHP** (`acf_add_local_field_group` на `acf/init`), один файл = одна группа полей. Чтение по `name`, не по key.
4. **Параметризация через `project.config.json`:** префикс, text domain, slug темы и mu-plugin, домены (local / staging / production), locale, шрифты для preload, размеры изображений, аналитика. `tools/init` делает подстановку.
5. **Центральный манифест `pages-map.json`:** URL → файл прототипа → шаблон → тип → форма заявки → noindex → схема → LCP-кандидат → блоки первого экрана → участвует ли в PSI → ссылки на спецификации. Из него строятся тесты, PSI, perf-проверки и сидер страниц.
6. **Бизнес-специфика — отключаемые модули** (`modules/*`), по умолчанию выключены. Из примера: `catalog` (товары, фильтры, якоря) и `pricebook` (прайс → кэш → JS-калькулятор) — как образцы паттернов.
7. **Seed идемпотентен:** стабильный `slug`, повторный прогон = то же состояние. Запуск — `wp {prefix} seed`.
8. **Прототип лежит вне темы** (корневой `prototype/`), чтобы не уезжать на прод. Для самотестов стартера — нейтральная фикстура `fixtures/demo-prototype/`.
9. **Минимум PHP 8.1**, WPCS, PHPStan (`phpstan-wordpress`). Окружение — `wp-env` (или DDEV); плагины (SCF, Yoast) ставятся зависимостями, а не копиями в репо.

### 2.2 Правила для AI-агентов

1. **Один источник правил для обоих инструментов.** `AGENTS.md` (≈100–150 строк) читают Cursor и Claude; `CLAUDE.md` = `@AGENTS.md` + роль Claude.
2. **Правила — указатели, а не пересказ.** `.mdc` по glob ссылаются на контракты и playbooks, HOW не дублируют.
3. **Один факт — одно место.** Правило приоритета источников живёт только в `docs/INDEX.md`; запрещённые имена — только в `docs/contracts/naming.json` (из него генерируется таблица словаря и работает `check-naming`).
4. **Процедуры — это команды, а не always-on правила.** `/doc-align` (бывший `product-doc-alignment.mdc`), `/phase-review`, `/port-page <slug>`, `/psi-analyze <dir>`.
5. **Многоуровневая загрузка контекста:**

   | Уровень | Что | Когда |
   |---|---|---|
   | L0 | `AGENTS.md` — инварианты, карта, команды, запреты, цикл задачи | Всегда |
   | L1 | `.cursor/rules/*.mdc` по glob | При работе с файлами зоны |
   | L2 | `docs/contracts/*`, `docs/playbooks/*` | По ссылке из задачи |
   | L3 | Текущий phase-файл / задача | Явно при старте |

6. **Набор `.mdc`:** `php`, `mu-plugin`, `theme-templates`, `assets`, `seo`, `tests`, `docs`, `prototype` (read-only). Проектные правила — отдельными `project-*.mdc`, не в ядре.
7. **Роли инструментов:** Cursor — реализация; Claude — план фазы, ревью другой моделью, сверка с документацией, анализ отчётов PSI.
8. **Context7** — для API WordPress / SCF / Yoast вместо ответов «по памяти».

### 2.3 Проверки вместо текста

1. **Всё, что можно проверить машиной, становится проверкой, а не абзацем документации.** Документация — для решений, контракты — для интерфейсов, соблюдение — gate-скрипты.
2. **Phase gate = команда с кодом выхода**, а не чекбоксы, заполненные агентом.
3. **Готово = зелёный gate.** Цикл задачи: прочитать контракт → сделать → `npm run gate:<scope>` → ревью → коммит.
4. **Набор проверок:**

   | Проверка | Что ловит |
   |---|---|
   | `php -l`, PHPCS (WPCS), PHPStan | Синтаксис, стандарты, типы |
   | `check-naming` | Запрещённые / устаревшие имена |
   | `check-hardcode` | NAP, цены, домен в шаблонах |
   | `check-links` | Битые ссылки в docs, `AGENTS.md`, `.mdc`, командах |
   | `check-rules-coverage` | Файлы, на которые не действует ни одно правило |
   | `lint:prototype` | Нарушения регламента прототипа |
   | `validate-seeds` | Seed против JSON Schema и запретов |
   | e2e `static`, `navigation`, `forms`, `dynamic`, `seo`, `a11y` | Поведение сайта (данные из `pages-map`) |
   | e2e `perf-markup` | Разметка производительности |
   | e2e `console` | 404 и ошибки в консоли/Network |
   | e2e `visual` | Скриншот-сравнение «прототип vs WordPress» |

5. **Визуальный diff с прототипом** — ключевой элемент AI-тестирования: прототип уже является эталоном.
6. **Каждое нарушение агента на пилоте → новая автоматическая проверка**, а не новое правило в тексте.

### 2.4 Производительность: три уровня

| Уровень | Что | Когда |
|---|---|---|
| **P1. Встроено по умолчанию** | Решения из примера в коде темы | С фазы 0 |
| **P2. Статический gate** | `perf-markup.spec.ts` по данным `pages-map` | Каждая страница, локально |
| **P3. PSI** | `tools/psi.mjs` на публичном URL | Вручную, по явному запросу, после деплоя |

**P1 — механизмы стартера:**
- скрипты только со `strategy => 'defer'`, версии по `filemtime`;
- отложенный GA4 (заглушка `gtag` + загрузка через `delayMs` после `load` или при первом взаимодействии), ID из опций, пусто = выключено;
- фасад для тяжёлых встраиваний (`embed-facade`: карта / видео / виджет по клику, `aspect-ratio`);
- локальные variable woff2 по подмножествам, preload только критичных файлов из конфига, fallback-шрифт с метриками, **измеренными по реальному тексту** (`tools/font-fallback-metrics.mjs`), без Google Fonts;
- хелпер `{prefix}_image()`: одна LCP-картинка `eager + fetchpriority=high`, остальные `lazy + async`, всегда `width/height/sizes`, явный `loading` (защита от авто-`fetchpriority` ядра WP);
- `.reveal` только ниже первого экрана;
- WebP-подразмеры (quality 80) без изменения оригиналов.

**P2 — `perf-markup` проверяет:** LCP-узел не в `.reveal`; ровно одна картинка с `fetchpriority=high`; у `<img>` есть размеры; у скриптов `defer`/`async`; нет посторонних origin в `<head>`; preload шрифтов соответствует конфигу.

**P3 — правила PSI:**
- только задеплоенный публичный URL (PSI не видит localhost и не проходит basic-auth; staging закрывать от индексации `X-Robots-Tag: noindex`, не паролем);
- только по явному запросу, не в CI и не на каждый коммит, без параллельных прогонов на один ключ;
- ключ `PAGESPEED_API_KEY` только в `.env` (корень репо), передаётся заголовком, в логах редактируется;
- медиана нескольких прогонов, пометка `flaky`, фиксация GA4 on/off;
- пороги вводятся постепенно: `report` → baseline → `floor` = худший результат минус ~5 → `enforce` → поднимать `floor`;
- код чинится только если категория вышла из зелёной зоны и об этом попросили;
- вехи — в `docs/project/perf-log.md`; анализ отчёта — `/psi-analyze`.

**Вне кода темы** (чеклист запуска): page cache, `Cache-Control` для HTML, `immutable` для assets/uploads, регенерация WebP для старых вложений.

### 2.5 Безопасность

- Секреты — только в `.env` / защищённых опциях (`autoload=false`); в репо — только `.env.example`.
- `.gitignore` с первого коммита: `.env`, `creds/`, `node_modules/`, `psi-reports/`, `test-results/`, `playwright-report/`.
- Формы: nonce, honeypot, rate limit, sanitize/escape; CPT заявок непубличный.
- Default-идентификаторы клиента (GA4 и т. п.) в код стартера не попадают.

---

## 3. Структура стартера

```
wp-scf-starter/
├─ AGENTS.md · CLAUDE.md
├─ .cursor/rules/*.mdc          ← тонкие, по glob
├─ .claude/commands/            ← doc-align, phase-review, port-page, psi-analyze
├─ project.config.json          ← prefix, slugs, домены, шрифты, аналитика
├─ pages-map.json               ← центральный манифест страниц
├─ .env.example · .gitignore · .wp-env.json · composer.json · package.json
├─ prototype/                   ← вход проекта (read-only)
├─ fixtures/demo-prototype/     ← самотест стартера
├─ docs/
│  ├─ INDEX.md                  ← карта + правило приоритета
│  ├─ STARTER-PLAN.md           ← этот документ
│  ├─ contracts/                ← naming (+ naming.json), forms, seo, images, blog, testing, mu-plugin, theme, data-structures, template-parts
│  ├─ playbooks/                ← performance, psi, prototype-rules, architecture, analytics, launch
│  ├─ phases/                   ← ROADMAP + phase-0…7
│  ├─ decisions/                ← ADR
│  └─ project/                  ← заполняется на проекте (sitemap, seo, pages, prds, perf-log, qa-report)
├─ seed/ · seed/schema/
├─ wp-content/mu-plugins/{core}.php · {core}/
├─ wp-content/themes/{theme}/
├─ modules/                     ← catalog, pricebook (отключаемые)
├─ tests/e2e/
└─ tools/                       ← init, port-page, check-*, validate-seeds, psi, font-fallback-metrics, convert-to-webp
```

---

## 4. Процесс проекта на стартере (8 фаз)

| # | Фаза | Результат | Gate |
|---|---|---|---|
| 0 | **Init** — `npm run init`, `wp-env start` | Чистый WP, тема и mu-plugin активны, `.env` из примера | `gate:0`: 200, без PHP notices |
| 1 | **Приёмка прототипа** — аудит по регламенту, инвентаризация | `pages-map.json` (включая `lcp`, `above_fold`, `psi`), список компонентов. **Утверждает человек** | `lint:prototype` |
| 2 | **Модель данных** — контракты и seed | naming, CPT/tax/options/fields, seed JSON | `validate-seeds`, `check-naming` |
| 3 | **Ядро mu-plugin** | Данные доступны через `{prefix}_get_*()` | lint + PHPStan + идемпотентный seed |
| 4 | **Оболочка темы** — assets, шрифты, header/footer/menus, parts | Каркас | e2e navigation + `perf-markup` (шрифты, скрипты) |
| 5 | **Перенос страниц** — цикл `/port-page` по `pages-map` | Все шаблоны | 200 + `visual` + `check-hardcode` + `perf-markup` + `console` |
| 6 | **Динамика, формы, SEO, AI-visibility** | Выводы CPT, формы + Telegram, Yoast-схемы, sitemap, `/llms.txt` | e2e forms / dynamic / seo |
| 7 | **QA и запуск** | Релиз, PSI baseline, чеклист хостинга | Полный регресс + чеклист запуска + запись в `perf-log.md` |

После запуска — эксплуатация: PSI по запросу после крупных изменений, вехи в журнал, `enforce` вручную.

**Цикл одной страницы (`/port-page <slug>`):**
1. Контекст: запись `pages-map`, спецификация страницы, HTML прототипа, L1-правила.
2. Cursor: HTML → шаблон; повторяющиеся блоки → parts; данные → провайдеры; изображения → `{prefix}_image()`.
3. `npm run gate:page -- <slug>`.
4. Claude: ревью diff против AC и контрактов; если меняется контракт — сначала документ, потом код.
5. Коммит: одна страница — один коммит; красный gate — нет коммита.

Каждая фаза проекта завершается `/doc-align` и `/phase-review` (другой моделью, не тем агентом, который реализовывал).

---

## 5. Milestones разработки стартера

| # | Milestone | Содержание | Критерий готовности |
|---|---|---|---|
| **M0** | Безопасность и подготовка | Исходный проект с историей git сохранён вне репо; сменены Telegram-токен и пароль WP-админки (утёкший `creds/project_creds.json`) | S1–S2 в ADR 0001 закрыты |
| **M1** | Реестр миграции | [`ADR 0001`](decisions/0001-example-migration.md): решение по каждому файлу `/example` + список знаний K1–K18 | ✅ Составлен (2026-09-24) |
| **M2** | Каркас | `project.config.json`, `pages-map.json` (схема), `.wp-env.json`, `composer.json` (WPCS, PHPStan), `package.json`, `.gitignore`, `.env.example`, `docs/INDEX.md` | ✅ Выполнен (2026-09-24): WP 7.1.2 + SCF 6.9.5 + Yoast 28.5 через `wp-env`; `npm run gate:0` (check:config + parallel-lint + PHPCS + PHPStan) зелёный |
| **M3** | Ядро mu-plugin | Обобщённый `{core}`: boot, helpers, company options, поля по сущностям, generic CPT, forms + Telegram, admin FAQ/leads, seed runner + CLI, Yoast-схемы, `llms.txt` | ✅ Выполнен (2026-09-24): `starter-core` без fatal, `wp starter seed` идемпотентен на демо-seed, лиды/Telegram/FAQ-админка/Yoast-схемы/`llms.txt`; review пройден |
| **M4** | Тема с механизмами P1 | Модули setup/assets/images/analytics/head, parts, walkers, `{prefix}_image()`, `embed-facade`, каркас `main.css` / `main.js` / `analytics.js` | ✅ Выполнен (2026-09-25): тема `starter` на 9 демо-страницах из `pages-map`; P1-механизмы на месте, разовая P2-проверка зелёная; комментарии отключены ядром; review пройден |
| **M5** | Правила агентов | `AGENTS.md`, `CLAUDE.md`, 8 `.mdc`, команды `doc-align`, `phase-review`, `port-page`, `psi-analyze`; `naming.json` | `check-rules-coverage` и `check-links` зелёные |
| **M6** | Инструменты и тесты | `init`, `check-naming`, `check-hardcode`, `check-links`, `lint:prototype`, `validate-seeds` + JSON Schema, `font-fallback-metrics`, `convert-to-webp`, `psi.mjs`, `gate:*`; e2e: static, navigation, forms, dynamic, seo, a11y, perf-markup, console, visual | Все проверки работают на фикстуре; `psi.mjs` отказывает на localhost (код 2) |
| **M7** | Документация стартера | `contracts/*`, `playbooks/*` (performance, psi, prototype-rules, architecture, analytics, launch), `phases/ROADMAP` + phase-0…7, шаблоны `docs/project/*` | Все пункты K1–K18 ADR 0001 найдены в коде, проверке или playbook |
| **M8** | Модули-примеры | `modules/catalog`, `modules/pricebook` (выключены по умолчанию) | Включение модуля не ломает gate |
| **M9** | Самотест | Полный проход фаз 0–7 на `fixtures/demo-prototype/` | Все `gate:*` зелёные |
| **M10** | Удаление `/example` | Критерии §7 ADR 0001: реестр закрыт, grep по проектным именам пуст, ревью стартера против реестра другой моделью | `/example` удалён; стартер закоммичен, тег **`v0.1.0`** |
| **M11** | Пилот | Реальный проект на стартере, включая PSI baseline после деплоя | Ретро: нарушения агента → новые проверки; тег **`v0.2.0`** + `CHANGELOG` |

Порядок: M0 → M1 → M2 → M3 → M4 → M5 → M6 → M7 → M8 → M9 → M10 → M11 (M5 перенесён после M4, см. D21).

---

## 6. Решения, принятые при проектировании

| # | Решение | Причина |
|---|---|---|
| D1 | Классическая PHP-тема + SCF, не block theme | Перенос HTML-прототипа кусками разметки; поля в коде видны агенту |
| D2 | `AGENTS.md` — единый источник правил для Cursor и Claude | Консистентность между инструментами; в примере для Claude правил не было |
| D3 | `product-doc-alignment` из always-on правила → команда `/doc-align` | 35 строк процедуры фазы не должны висеть в каждом запросе |
| D4 | Запреты имён — только в `naming.json` | В примере список был продублирован в 4 местах |
| D5 | Gate = команда, а не чекбоксы | Чекбоксы, отмеченные агентом, — самоотчёт |
| D6 | Новые правила для шаблонов, CSS/JS и производительности | В примере эти зоны не покрывались, отсюда хардкод и риск perf-регрессий |
| D7 | `pages-map.json` как центральный манифест | Списки URL в тестах, PSI и сидере были захардкожены по отдельности |
| D8 | Производительность на трёх уровнях (код / статический gate / PSI) | Выигрыши примера (mobile 72–88 → 99–100) дали повторяемые решения — их нужно встроить, а не пересказывать |
| D9 | PSI — вручную, только прод/публичный staging | PSI мерит деплой, не ветку; квота ключа; lab-разброс |
| D10 | Прототип вне темы; фикстура для самотестов | Прототип примера уезжал на прод; после удаления `/example` стартеру нужен собственный вход |
| D11 | Каталог и pricebook — отключаемые модули | Бизнес-логика конкретного клиента, но полезные образцы паттернов |
| D12 | PHP 8.1 минимум | Хостинг примера — 8.2; современные WPCS/PHPStan |
| D13 | Окружение `wp-env`/DDEV, плагины зависимостями | OSPanel и копии плагинов в репо невоспроизводимы для агента |
| D14 | Ревью фазы — другой моделью (Claude) | Разделение реализации и проверки |
| D15 | `drafts/archive` → ADR в `docs/decisions/` | История решений без устаревших «полуканонических» черновиков |
| D16 | `/example` удаляется только после самотеста и закрытия реестра | Каталог в `.gitignore` — восстановить нельзя |
| D17 | WordPress и плагины — `latest` из `project.config.json`, резолвятся в последнюю **стабильную** версию через api.wordpress.org; можно зафиксировать точную. `wordpress.min` = текущая мажорная ветка (7.1) | Актуальная версия WP по умолчанию; zip без версии отдавал Yoast RC |
| D18 | Node.js ≥ 24 | Требование зависимостей `@wordpress/env` |
| D19 | Локальное окружение — PHP 8.2 (как на хостинге), совместимость с 8.1 проверяет PHPCompatibility/PHPStan | Среда повторяет прод, минимум контролируется статически |
| D20 | `npm run wp` идёт через `docker exec` в запущенный контейнер, `wp-env` — только запасной путь | `wp-env run` на Windows ~90 с на вызов |
| D21 | M5 (правила агентов) — после M3/M4, а не параллельно с M3 | Правила — указатели на реальный код и контракты; параллельная работа агентов над общими файлами рискованна |
| D22 | Конфиг попадает в PHP через сгенерированный `starter-core/config.generated.php` (`npm run build:config`), свежесть проверяет `check:config` | Корень репо (`project.config.json`, `pages-map.json`) на хостинг не деплоится |
| D23 | Комментарии отключены ядром по умолчанию (фильтр `starter_disable_comments`) | Корпоративным сайтам они не нужны; открытые, но не выводимые комментарии — канал спама |
| D24 | Оригиналы изображений остаются PNG/JPEG, WebP — только подразмеры; srcset без оригиналов | `og:image` и лайтбокс работают с оригиналом, hi-DPI не тянет тяжёлый файл |
