---
status: canonical
version: 1.0
updated: 2026-09-25
---

# Контракт темы `starter`

Тема = представление: шаблоны, parts, enqueue, изображения, `<head>`. Данные — только через API ядра ([`mu-plugin.md`](mu-plugin.md)). Части — [`template-parts.md`](template-parts.md); почему механизмы именно такие — [`playbooks/performance.md`](../playbooks/performance.md).

## Модули `inc/`

[`functions.php`](../../wp-content/themes/starter/functions.php) только подключает модули и задаёт `STARTER_THEME_VERSION`.

| Файл | Что | Публичное |
|---|---|---|
| [`inc/setup.php`](../../wp-content/themes/starter/inc/setup.php) | supports (`title-tag`, thumbnails, html5), меню `primary` / `mobile` / `footer`, размер карточки | `starter_card_size()`, фильтр `starter_content_width` |
| [`inc/template-tags.php`](../../wp-content/themes/starter/inc/template-tags.php) | Хелперы вывода: URL, NAP, иконки, меню-fallback, блог, автор, карта, запись `pages-map`, крошки, пагинация | `starter_url()`, `starter_company_value()`, `starter_page_config()`, `starter_page_has_lead_form()`, `starter_lead_source()` |
| [`inc/assets.php`](../../wp-content/themes/starter/inc/assets.php) | `main.css`, `main.js` (defer), `window.STARTER_LEAD` / `STARTER_I18N`; defer admin-bar | `starter_script_args()`, `starter_asset_url()`, `starter_asset_version()` |
| [`inc/images.php`](../../wp-content/themes/starter/inc/images.php) | WebP-подразмеры, качество, `starter_image()`, srcset только WebP | [`images.md`](images.md) |
| [`inc/analytics.php`](../../wp-content/themes/starter/inc/analytics.php) | Отложенный GA4 | [`playbooks/analytics.md`](../playbooks/analytics.md) |
| [`inc/head.php`](../../wp-content/themes/starter/inc/head.php) | preload шрифтов из конфига, favicon-набор, `theme-color`, geo meta, отключение emoji и Site Icon | фильтр `starter_theme_color` |
| [`inc/post-toc.php`](../../wp-content/themes/starter/inc/post-toc.php) | Якоря H2/H3 и оглавление поста | [`blog.md`](blog.md) |
| `inc/class-starter-walker-nav-*.php` | Walker-ы разметки шапки, мобильного меню, подвала | `Starter_Walker_Nav_Primary` / `_Mobile` / `_Footer` |

## Шаблоны

| Файл | Вид | Первый экран (без `.reveal`) |
|---|---|---|
| `front-page.php` | Главная (скелет: hero → секции parts → lead) | `.hero`, H1 — текстовый LCP |
| `page.php` | Страница по умолчанию: page-head → контент → дочерние карточки → проекты услуги → FAQ → lead | `page-head` |
| `page-<slug>.php` | Страница, перенесённая из прототипа (`/port-page`); образец — `page-contacts.php` | по `pages-map` |
| `home.php` | Индекс блога | page-head + первая карточка (`priority`) |
| `single.php` | Пост | шапка + обложка (`priority`) |
| `archive.php`, `search.php`, `404.php`, `index.php` | Архивы, поиск, 404, fallback | page-head |
| `header.php` / `footer.php` | Документ + `site-header` / `site-footer`; каждый шаблон открывает свой `<main id="main">` | — |

Какой шаблон рендерит URL, есть ли форма и что на первом экране — запись `pages-map.json` (`template`, `lead_form`, `lcp`, `above_fold`). В шаблоне её читает `starter_page_config()`.

## Правила enqueue

- Front-end assets подключает только тема (`wp_enqueue_scripts` в `inc/`).
- Каждый скрипт — `wp_enqueue_script( …, starter_script_args() )`: `in_footer` + `strategy => defer`. Зависимый скрипт без своей `strategy` делает отложенного родителя блокирующим (K2) — проверка по итоговому HTML: e2e `perf-markup`.
- Версии — `starter_asset_version()` (filemtime).
- Данные для JS — `wp_add_inline_script( …, 'before' )` → `window.STARTER_*`; inline `<script>` и `style=""` в шаблонах запрещены.
- Один render-blocking CSS: `@font-face` в начале `main.css`, не отдельным файлом.

## CSS / JS

Порядок секций `main.css`, БЭМ, токены, `is-*`, `feature()` в `main.js` — шапки [`main.css`](../../wp-content/themes/starter/assets/css/main.css) и [`main.js`](../../wp-content/themes/starter/assets/js/main.js) + правило `assets.mdc`; происхождение правил — [`playbooks/prototype-rules.md`](../playbooks/prototype-rules.md). События — `CustomEvent` на `document`: `starter:lead:success` `{ formId, source }`, `starter:lightbox:open`.

## `.reveal`

`.reveal` → `.is-in` (IntersectionObserver в `main.js`), без JS и при `prefers-reduced-motion` контент видим. Только ниже первого экрана: LCP-узел и блоки `above_fold` из `pages-map` — без `.reveal` (K1). Parts с аргументом `reveal` получают `'reveal' => false` на первом экране.

## `<head>`

Title / meta / canonical / OG / JSON-LD — Yoast ([`seo.md`](seo.md)), тема их не генерирует. Тема печатает: preload шрифтов (`fonts.preload`, `crossorigin`, без `?ver`), favicon-набор из `assets/icons/` (заменить плейсхолдеры на проекте), geo meta из адреса компании.
