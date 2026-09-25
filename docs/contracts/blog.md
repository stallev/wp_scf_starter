---
status: canonical
version: 1.0
updated: 2026-09-25
---

# Контракт блога

Блог — стандартные `post` + `category`, **не** CPT (`check:naming`: `blog-cpt`). В ядре блог не регистрируется; ядро только сидит посты (`seed/posts.json`).

## URL и шаблоны

| URL | Шаблон | `pages-map` `type` |
|---|---|---|
| Страница записей (`/blog/`, «Настройки → Чтение», ставит seed) | `home.php` | `blog-index` |
| `/blog/page/N/` | `home.php` | — |
| Пост | `single.php` | `post` |
| Рубрика / метка / автор / дата | `archive.php` | `archive` / `taxonomy` |

Постоянные ссылки — `/%postname%/` (ставит `env-setup`); префикс блога для постов — решение проекта (настройки WP / Yoast), фиксируется в [`project/sitemap.md`](../project/sitemap.md).

## Индекс блога (`home.php`)

page-head (H1 — текстовый LCP) → чипы рубрик (только при ≥ 3 рубриках с постами, `starter_blog_filter_categories()`) → карточки `post-card` → пагинация → `lead-section`. Первая карточка первой страницы — `priority` (LCP-картинка, без `.reveal`), остальные `lazy`. Нет постов — пустое состояние; скрывать ли пункт меню «Блог» до первых постов — решение проекта (хелпер `starter_has_blog()`).

## Пост (`single.php`)

Крошки, H1, дата, время чтения (`starter_post_reading_minutes()`, скорость — фильтр `starter_reading_wpm`, 180 слов/мин), `post-author` byline → обложка (`large`, `priority`, вне `.reveal`) → контент + оглавление → карточка автора → похожие посты (`post-card` с `heading => h3`) → `lead-section`. Featured image обязателен для карточек (без него — запасная картинка компании).

## Оглавление и якоря

- TOC собирается на сервере из H2/H3 тела поста ([`inc/post-toc.php`](../../wp-content/themes/starter/inc/post-toc.php)), не руками.
- Показ: ≥ 2 пункта **и** ≥ 600 слов (фильтр `starter_post_toc_min_words`).
- Якоря: H2/H3 без `anchor` получают `h-` + 10 символов `[a-z0-9]` — в редакторе (`editor-heading-anchors.js`) и при сохранении (`wp_insert_post_data`). Существующие якоря не перезаписываются: ссылки на них не ломаются.

## Автор

`starter_get_post_author_data()`: имя, инициалы, ссылка, Instagram из профиля (поле добавляет `starter_user_contactmethods()`); аватар — только фильтр `starter_post_author_avatar` (локальная `<img>` с размерами), иначе инициалы. Gravatar не запрашивается — нет стороннего origin.

## SEO

Title / description постов и архивов, schema `Article` / `Blog` — Yoast; второй JSON-LD запрещён. Комментарии выключены ядром (D23). Правила текста постов — [`project/seo/post-outline.md`](../project/seo/post-outline.md).
