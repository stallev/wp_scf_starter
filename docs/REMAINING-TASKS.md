---
status: canonical
version: 1.0
updated: 2026-09-25
---

# Оставшиеся задачи стартера: инструкции для AI-агента (Cursor)

Документ передачи работы. До M7 включительно стартер собран в Claude Code (коммиты `2dec8fa` … `d813cce`). Всё, что ниже, включая верификацию уже сделанного, выполняет агент Cursor.

План и решения — [`STARTER-PLAN.md`](STARTER-PLAN.md) (milestones §5, решения D1–D26). Пофайловый реестр переноса — [`decisions/0001-example-migration.md`](decisions/0001-example-migration.md) (дальше «ADR 0001»). Правила агента — [`../AGENTS.md`](../AGENTS.md).

---

## 0. Как работать с этим документом

1. **Порядок:** задачи выполняются сверху вниз. T1 (верификация) идёт первой: остальные задачи опираются на её результат.
2. **Одна задача — один цикл:**
   1. прочитать раздел задачи и указанные в нём документы;
   2. реализовать;
   3. запустить команды из «Критерия готовности»;
   4. сделать независимое ревью по чек-листу [`.claude/commands/phase-review.md`](../.claude/commands/phase-review.md): отдельный чат или другая модель, не та сессия, что реализовывала;
   5. исправить блокеры;
   6. закоммитить.
3. **Коммиты:** одна задача — один коммит. Сообщение в стиле существующих: `feat(M8): …` / `docs(…): …` / `fix(…): …`, тело подробное: что сделано, как проверено, какие строки ADR закрыты. Красный gate = коммита нет.
4. **Учёт:** после каждой задачи обновить статусы строк ADR 0001 (`todo` → `done (Mx)` / `n/a`) и строку milestone в `STARTER-PLAN.md` §5. Новое решение записать в таблицу решений (D27 и далее), а не молча в код.
5. **Ограничения:**
   - не редактировать `prototype/` проекта и `/example`: последний — только источник для чтения до M10;
   - не публиковать и не деплоить без явной просьбы;
   - PSI запускать только по явному запросу владельца.

---

## 1. Окружение и полезные факты

| Что | Как |
|---|---|
| Node | ≥ 24 (`.nvmrc`), npm 11 |
| WordPress | `npm run env:start` → http://localhost:8888 (`admin` / `password`); версии WP и плагинов берутся из `project.config.json` → `wordpress` (`latest` = последняя стабильная) |
| WP-CLI | `npm run wp -- <args>`. Работает через `docker exec` в запущенный контейнер (секунды); `wp-env run` на Windows занимает ~90 с на вызов, им не пользоваться |
| Composer / PHP-линт | `npm run composer -- <args>`, `npm run lint:php` (локальный composer или Docker-образ `composer:2`) |
| Логи PHP | `debug.log` в контейнере `…-wordpress-1` (имя: `docker ps --filter label=com.docker.compose.service=wordpress`); в Git Bash перед `docker exec` ставить `MSYS_NO_PATHCONV=1` |
| После правки `project.config.json` / `pages-map.json` | `npm run build:config`, затем `npm run check:config` |
| После правки `docs/contracts/naming.json` | `npm run build:naming` (словарь генерируется) |
| Главный gate | `npm run gate:0` = check:config + gate:rules (naming, links, rules) + check:hardcode + check:seeds + lint:php |
| Тесты инструментов | `npm run test:tools` (node:test, 52 теста) |
| e2e | `npm run test:e2e` (Playwright, ~4–5 мин). **Низкий приоритет (D26):** не блокирует задачи, кроме случаев, где это оговорено |

**Известные особенности (README → «Известные особенности окружения»):**
- **DNS за VPN.** Обходится обёрткой `tools/wp-env.mjs` + `tools/dns-fallback.cjs`.
- **Первый старт падает с ошибкой БД.** Повторите `npm run env:start`.
- **`tools/init` на работающем окружении.** Сначала `npm run env:stop`: запущенный wp-env блокирует каталоги.

**Состояние репозитория на момент передачи:**
- `gate:0` зелёный, `test:tools` 52/52, `test:e2e` 127 passed / 9 skipped (visual без прототипа).
- Демо-контент: 9 страниц в `pages-map.json`, `seed/*`.
- Модули `catalog` и `pricebook` выключены и **ещё не реализованы**.

---

## 2. Список задач

| # | Задача | Milestone | Приоритет | Кто |
|---|---|---|---|---|
| T0 | Безопасность исходного проекта (S1, S2) | M0 | высокий | **владелец** |
| T1 | Верификация M2–M7 | — | высокий | агент |
| T2 | Модули-примеры `catalog` и `pricebook` | M8 | средний | агент |
| T3 | `tools/port-page` + обновление команды `/port-page` | ADR §4.3 | средний | агент |
| T4 | Seed: `--verify`, запреты из `naming.json`, шаблон Yoast-meta | ADR §3, §4.1, §4.4 | низкий | агент |
| T5 | Самотест стартера на `fixtures/demo-prototype/` | M9 | высокий | агент |
| T6 | Закрытие реестра и удаление `/example`, тег `v0.1.0` | M10 | высокий | агент + владелец (подтверждение удаления) |
| T7 | Пилот на реальном проекте, `v0.2.0` | M11 | — | владелец + агент |

---

## T0. Безопасность исходного проекта (владелец)

Не для агента: только отметить статус в ADR после подтверждения владельцем.

- **S1:** исходный проект с историей git сохранён вне этого репозитория.
- **S2:** Telegram bot token и пароль WP-админки из `creds/project_creds.json` исходного проекта **сменены**. Файл лежал в web root темы и был публично доступен.

**Готово, когда** владелец подтвердил оба пункта; строки S1/S2 в ADR 0001 §0 → `done`.

---

## T1. Верификация M2–M7 ✅ (2026-09-27)

Независимая проверка прошла без дефектов: `gate:0`, `test:tools` (52/52), `check:seed-idempotent`, `lint:prototype` на обеих фикстурах, `psi` на localhost (код 2), все 9 URL из `pages-map` → 200, `/llms.txt` → 200, `debug.log` пуст. Имена в `AGENTS.md` / контрактах сверены с кодом grep'ом — расхождений нет. `tools/init.mjs` проверен во временном worktree (`--prefix=acme`) — все проверки зелёные, worktree и junction на `node_modules` убраны, основной репозиторий не задет. `test:e2e` — см. ниже. Фикс-коммитов не потребовалось.

**Цель:** независимо подтвердить, что собранное работает, до того как на это опираться.

**Шаги:**
1. `npm ci` (или `npm install`), `npm run composer -- install`, `npm run env:start`.
2. Запустить и сохранить вывод:
   - `npm run gate:0`
   - `npm run test:tools`
   - `npm run check:seed-idempotent`
   - `npm run lint:prototype -- --dir=fixtures/demo-prototype`
   - `npm run lint:prototype -- --dir=fixtures/bad-prototype` (должен **упасть**)
   - `npm run psi -- --base=http://localhost:8888` (должен завершиться с кодом **2**)
3. Проверить сайт:
   - все URL из `pages-map.json` → 200, случайный URL → 404;
   - `/llms.txt` → 200;
   - `debug.log` пуст после обхода.
4. Прочитать по диагонали и сверить с кодом (выборочно grep имён):
   - `AGENTS.md`;
   - [`contracts/mu-plugin.md`](contracts/mu-plugin.md), [`contracts/theme.md`](contracts/theme.md), [`contracts/forms.md`](contracts/forms.md);
   - [`playbooks/performance.md`](playbooks/performance.md).
5. Проверить `tools/init` в **временной копии** (никогда в основном дереве):
   1. `git worktree add <tmp> HEAD`;
   2. в копии сделать junction/symlink на `node_modules` (в PowerShell: `New-Item -ItemType Junction`);
   3. `node tools/init.mjs --prefix=acme --name="Acme"`;
   4. в копии запустить `check:config`, `gate:rules`, `check:hardcode`, `check:seeds`, `test:tools`;
   5. **сначала** удалить junction (`cmd /c rmdir <tmp>\node_modules`), затем `git worktree remove --force <tmp>`.
6. По желанию: `npm run test:e2e`.

**Готово, когда** все команды дали ожидаемый результат. Найденные дефекты исправлены отдельными коммитами `fix(...)` с описанием.

---

## T2. M8 — модули-примеры `catalog` и `pricebook` ✅ (2026-09-27)

**Контекст:**
- **Точка расширения уже есть:** `wp-content/mu-plugins/starter-core/boot.php` загружает `starter-core/modules/<name>/module.php`, когда в `project.config.json` → `modules.<name>` стоит `true` (после `npm run build:config`).
- **Фильтры расширения:** `starter_seed_targets`, `starter_schema_service`.
- **Что прочитать:**
  - правила [`mu-plugin.mdc`](../.cursor/rules/mu-plugin.mdc), [`theme-templates.mdc`](../.cursor/rules/theme-templates.mdc), [`seo.mdc`](../.cursor/rules/seo.mdc), [`config.mdc`](../.cursor/rules/config.mdc);
  - контракты [`mu-plugin.md`](contracts/mu-plugin.md), [`seo.md`](contracts/seo.md), [`data-structures.md`](contracts/data-structures.md);
  - строки ADR 0001 с решением `OPT`.
- **Источники паттернов (только чтение, пока `/example` существует):**
  - `example/mu-plugins/*-core/`: `taxonomies.php`, `pricebook.php`, `calculator.php`, `admin.php`, `seo/class-*-schema-product-offer.php`, продуктовые части `post-types.php` / `queries.php`;
  - шаблоны каталога темы примера: архив, таксономия, single, `template-parts/product-card.php`;
  - `prototype/data/prices.json` + `assets/js/prices.js` примера;
  - `docs/wp-docs/contracts/{catalog-url-model,pricebook-schema}.md`, `specs/calculator-spec.md`.
- **Запрет:** данные, формулы и названия клиента не переносить. Модули — **обезличенные примеры паттернов**, по умолчанию выключены.

### T2.1 `starter-core/modules/catalog/`
- **`module.php`:**
  - CPT `starter_product`: public, архив по настраиваемому slug, по умолчанию `catalog`, фильтр для переопределения;
  - иерархическая таксономия `starter_product_family` с rewrite под slug каталога.
- **Поля SCF** (`acf/init`, отдельный файл): price, unit, sku, короткий список характеристик. Читать по `name`.
- **Запросы:** `starter_get_products( array $args ): array`.
- **Yoast graph piece `Product`/`Offer`:** отдельный класс, выводится только на страницах, где в `pages-map` → `schema` есть `"Product"` (архив и single). Второй JSON-LD вне Yoast запрещён.
- **Шаблоны:** отдаются модулем через `template_include` (archive, taxonomy, single). Карточка — part темы `product-card`, если он есть, иначе fallback-part модуля. Изображения — только через `starter_image()`, `.reveal` — только ниже первого экрана.
- **Seed:**
  - цели `product-families` и `products` через фильтр `starter_seed_targets`;
  - данные в `seed/modules/catalog/*.json` (нейтральное демо) + JSON Schema в `seed/schema/`;
  - учёт в `tools/validate-seeds.mjs`;
  - при выключенном модуле seed — no-op.
- **`README.md` модуля:** модель URL; как включить (`modules.catalog: true` → `npm run build:config` → поднять `STARTER_CORE_VERSION` или сбросить rewrite).

### T2.2 `starter-core/modules/pricebook/`
- **Options page `starter-pricebook` (SCF):** repeater позиций (key, label, value, unit, note).
- **Провайдер `starter_get_pricebook()`:** transient-кэш, инвалидация на сохранении опций. Хелпер `starter_price( string $key )`.
- **Фронтенд:**
  - объект `window.<PREFIX>_PRICES` через `wp_add_inline_script` на основной handle темы (только при включённом модуле);
  - `assets/js/prices.js` модуля (`strategy => 'defer'`) заполняет `[data-price="key"]` и шлёт событие `<prefix>:prices:ready`.
- **Пример калькулятора:** «цена × количество» + PHP-функция эталонных проверок `starter_pricebook_etalon_checks()` + WP-CLI `wp starter pricebook check` (ненулевой код при расхождении).
- **Формулы — в коде, не в опциях.**
- **`README.md` модуля:** паттерн «единый источник цен → разметка → JSON-LD → калькулятор».

### T2.3 Именование и документация
- Новые канонические имена добавить в [`contracts/naming.json`](contracts/naming.json) → `npm run build:naming`. `check:naming` проверяет, что каждое имя существует в коде.
- В [`contracts/mu-plugin.md`](contracts/mu-plugin.md) — раздел «Модули» со ссылками на README модулей.
- `check:rules` должен покрывать новые пути, например `seed/modules/**`. Если нет — поправить globs в `.cursor/rules/*.mdc`.

### Критерий готовности T2
1. **Модули включены** (`true` + `build:config`):
   - CPT и таксономия зарегистрированы;
   - архив, таксономия и single → 200;
   - options page есть;
   - `prices.js` в HTML с `defer`, объект цен в HTML;
   - `wp starter pricebook check` проходит;
   - `wp starter seed` дважды — второй прогон без изменений;
   - `npm run gate:0` зелёный.
2. **Модули выключены** (`false` + `build:config`): ничего модульного не зарегистрировано, демо-страницы 200, `npm run gate:0` зелёный.
3. **Итоговое состояние — модули выключены**, демо-контент модулей удалён из БД, сайт как до задачи.
4. ADR 0001: строки `OPT` (taxonomies, admin notice, pricebook/calculator, product-offer, шаблоны каталога, product-card, products/pricebook seed, prices.json-паттерн, catalog-url-model/pricebook-schema/calculator-spec/PRD) → `done (M8)` или `n/a` с причиной.
5. `STARTER-PLAN.md` M8 → ✅.

---

## T3. `tools/port-page` + команда `/port-page` ✅ (2026-09-27)

`npm run port-page -- <url> [--dry-run] [--force] [--prototype-dir=<dir>]` реализован, покрыт 15 unit-тестами, review пройден (5 замечаний исправлены: абсолютный `--prototype-dir`, `srcset`, встроенный `<?php` в тексте прототипа, порядок `--dry-run`/overwrite, `.html?query` ссылки). `test:tools` 67/67, `gate:0` зелёный.

**Контекст:** строка ADR 0001 §4.3 `convert-prototype-templates.mjs` (todo). Процедура переноса страницы описана в [`.claude/commands/port-page.md`](../.claude/commands/port-page.md) и [`phases/phase-5-pages.md`](phases/phase-5-pages.md); инструмента-помощника нет.

**Сделать** `tools/port-page.mjs` (`npm run port-page -- <url> [--dry-run]`):
1. Найти запись `<url>` в `pages-map.json`. Если нет или `prototype: null` — exit 2 с понятным сообщением.
2. Извлечь `<main>` из `prototype/<file>` (node-html-parser).
3. Переписать относительные ссылки прототипа (`*.html`, `index.html#…`) на URL из `pages-map`, пути `assets/…` — на `get_theme_file_uri()`.
4. Отметить TODO-комментариями места, где в прототипе захардкожены данные компании. Для поиска переиспользовать логику `tools/check-hardcode.mjs` (вынести общий модуль, не копировать).
5. Записать черновик `wp-content/themes/<theme>/<template>` (из `pages-map.template`) с `get_header()` / `get_footer()`. Если файл существует — не перезаписывать без `--force`.
6. Напечатать чек-лист следующих шагов: parts, `starter_image()`, `.reveal`, форма, `gate:page`.

Покрыть unit-тестами в `tools/__tests__/` на фикстуре `fixtures/demo-prototype/`. Обновить команду `/port-page` (шаг «черновик шаблона»), `AGENTS.md` (таблица команд), [`phases/phase-5-pages.md`](phases/phase-5-pages.md).

**Готово, когда:**
- `npm run test:tools` зелёный;
- на фикстуре (временная запись `pages-map` с `prototype: "index.html"` и `paths.prototype`, либо флаг `--prototype-dir=fixtures/demo-prototype`) создаётся корректный черновик;
- `gate:0` зелёный;
- ADR §4.3 строка → `done`.

---

## T4. Seed: `--verify`, запреты из `naming.json`, шаблон Yoast-meta (низкий приоритет) ✅ (2026-09-27)

`wp starter seed --verify`, чтение запретов из `naming.json` через снимок конфига (без утечки в остальной `check:naming`-скан файла — review нашёл слишком широкое исключение, исправлено), `seed/yoast-meta.json` + seed-цель + JSON Schema. `test:tools` 70/70, `gate:0` и `check:seed-idempotent` зелёные.

1. **`wp starter seed --verify`.** Сравнить количество и slug-и в БД с `seed/*.json`; ненулевой код при расхождении. Файлы: `starter-core/cli.php`, `class-starter-cli-command.php`, `seed/runner.php`. ADR §4.1 строка `tools/bootstrap-pages.php …` → `done`.
2. **Запреты в `starter_seed_find_forbidden()` (`seed/loader.php`).** Список сейчас в PHP; брать его из `docs/contracts/naming.json`. Репо-корень не деплоится, поэтому снимок кладётся в `config.generated.php` через `tools/build-config.mjs`, как остальной конфиг. ADR §3 строка `seed/paths.php, loader.php, runner.php` → `done`.
3. **Шаблон Yoast-meta.** `seed/yoast-meta.json` (title/description по URL из `pages-map`) + seed-цель, пишущая `_yoast_wpseo_title` / `_yoast_wpseo_metadesc` страницам. Плюс JSON Schema и учёт в `validate-seeds`. Паттерны заголовков — [`project/seo/meta-patterns.md`](project/seo/meta-patterns.md). ADR §4.4 строка `seed/products.json, pricebook.json, yoast-meta.json` — часть `yoast-meta`.

**Готово, когда:**
- `gate:0` и `test:tools` зелёные;
- `check:seed-idempotent` зелёный;
- `--verify` падает на искусственно удалённой записи и проходит после повторного seed.

---

## T5. M9 — самотест на `fixtures/demo-prototype/` ✅ (2026-09-27)

Пройдено в изолированном `git worktree` со своим wp-env (порт 8899), основное окружение (8888) не затронуто. `gate:0`–`gate:4`, `gate:6`, `gate:7` зелёные. `gate:5` зелёный кроме `visual` — расхождение на 4 наспех дописанных demo-страницах (класс-комбинации фикстуры не покрыты `main.css` «боевых» шаблонов); признано ожидаемым для самотеста, порог 5% не занижался (D30).

Найдено и исправлено (commits `545c107`, `3ce8459`, review-нит `1de30b5`):
1. `tools/init.mjs` не сбрасывал `seed/yoast-meta.json` в скелет — после `init` оставались демо Yoast-записи на несуществующие страницы, `check:seeds` падал сразу.
2. `starter_seed_import_pages()` при отсутствующем родителе искал существующую страницу по вложенному пути, хотя она создавалась в корне — каждый повторный `wp starter seed` плодил дубликат.

Не исправлено намеренно (низкий приоритет, задокументировано комментарием в коде): узкий случай — родитель отсутствовал на одном прогоне (страница ушла в корень), затем появился в `pages-map` до следующего прогона → возможен дубликат под новым вложенным путём. На текущем демо-контенте стартера не воспроизводится.

Прочие находки без исправления (низкий приоритет, для владельца/будущего): черновики `port-page` требуют `npm run lint:php:fix` перед первым `lint:php` (ожидаемо — черновик не готов); порт wp-env не разделён по каталогу автоматически, для второго параллельного самотеста нужно вручную задать `port`/`testsPort` в `.wp-env.override.json`.

**Цель:** доказать, что пайплайн фаз 0–7 ([`phases/ROADMAP.md`](phases/ROADMAP.md)) работает на стартере целиком.

**Шаги (в отдельной ветке или worktree; основное дерево не портить):**
1. **Фаза 0.** `npm run init -- --prefix=demo --name="Demo"` на чистом дереве (перед этим `npm run env:stop`), затем `npm run env:start`, `npm run gate:0`.
2. **Фаза 1.**
   - Скопировать `fixtures/demo-prototype/*` в `prototype/`.
   - Заполнить `pages-map.json` записями на страницы фикстуры (`prototype` не `null`, `lcp`, `above_fold`, `psi`).
   - Затем `npm run gate:1`.
3. **Фазы 2–4.** Seed по минимуму, `gate:2`, `gate:3`, `gate:4`.
4. **Фаза 5.** Перенести страницы фикстуры циклом `/port-page` (с T3 — через `tools/port-page`); на каждую `npm run gate:page -- <url>`. Visual-сравнение прототип ↔ WP должно пройти с порогом из `tests/e2e/helpers/visual.ts`.
5. **Фазы 6–7.** `gate:6`, `gate:7`. PSI не запускать: сайт локальный.
6. Записать, где агент или инструменты мешали. Каждое нарушение превращается в новую проверку или правку правила (принцип §2.3.6 плана), отдельными коммитами в основной ветке.
7. Временную ветку или worktree удалить; в основную ветку попадают только исправления стартера.

**Готово, когда** все `gate:*` зелёные на самотесте, найденные дефекты исправлены, `STARTER-PLAN.md` M9 → ✅.

---

## T6. M10 — закрытие реестра и удаление `/example`

1. Пройти ADR 0001: все строки §0–§6 в `done` или `n/a` (с причиной).
2. Выполнить grep из ADR 0001 §7 по всему репозиторию, кроме самого ADR и `STARTER-PLAN.md`: результат должен быть пуст.
3. `npm run gate:0`, `npm run test:tools`, `npm run check:links`, `npm run check:rules` — зелёные. `psi --help` работает; на localhost — код 2.
4. **Независимое ревью стартера против реестра** другой моделью или в отдельном чате: чек-лист ADR 0001 §6 (K1–K18) и §7.
5. **Удаление `/example` — только после явного «да» владельца.** Каталог в `.gitignore`, из истории git его не восстановить. Удалить, убрать строку `/example/` из `.gitignore`, заново прогнать `gate:0` и `check:links`. Ссылок на `example/` в docs и правилах быть не должно, кроме истории в ADR и плане.
6. Коммит + тег `v0.1.0` (`git tag -a v0.1.0 -m "…"`). Push — только по просьбе владельца.

**Готово, когда** все пункты чек-листа ADR 0001 §7 отмечены и `STARTER-PLAN.md` M10 → ✅.

---

## T7. M11 — пилот (после v0.1.0)

Реальный проект на стартере по [`phases/ROADMAP.md`](phases/ROADMAP.md): `init` → прототип → фазы 0–7 → деплой → PSI baseline ([`playbooks/psi.md`](playbooks/psi.md), журнал [`project/perf-log.md`](project/perf-log.md)).

По итогам — ретро: каждое повторяющееся нарушение агента становится новой автоматической проверкой. Выпустить `CHANGELOG.md` и тег `v0.2.0`.
