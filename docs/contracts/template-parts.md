---
status: canonical
version: 1.0
updated: 2026-09-25
---

# Контракт template-parts

Повторяющийся блок (≥ 2 страниц) → `template-parts/<block>.php`. Вызов: `get_template_part( 'template-parts/<block>', null, $args )`; внутри — `wp_parse_args()` с дефолтами в `$starter_args`. Полное описание аргументов — PHPDoc-шапка каждого файла ([`template-parts/`](../../wp-content/themes/starter/template-parts/)); здесь — сводка и общий контракт разметки.

## Общий контракт

- Нет данных → part **ничего не выводит** (не пустая секция).
- Данные — провайдеры ядра / темы, не литералы (`check:hardcode`).
- Изображения — `starter_image()`; `priority` только у LCP-картинки страницы.
- Аргумент `reveal` (по умолчанию `true`) → `false`, если блок на первом экране (`above_fold` в `pages-map`).
- Корневой элемент — БЭМ-блок с тем же именем, что у файла (`.faq`, `.folio`, `.lead-form`); состояния — `is-*`.

## Parts

| Part | Аргументы | Источник данных | Разметка / заметки |
|---|---|---|---|
| `site-header` | — | меню `primary` / `mobile`, `starter_get_company()` | `.header`, `.nav`, `#burger`, `#menu`; без меню — `nav-*-fallback` |
| `site-footer` | — | меню `footer`, компания | `.footer`; без меню — `footer-links-fallback` |
| `nav-primary-fallback`, `nav-mobile-fallback`, `footer-links-fallback` | — | страницы `pages-map` | Показ, пока меню не назначено (фильтр `starter_fallback_nav_links`) |
| `page-head` | `title`, `lead`, `crumbs` | queried object | Крошки + H1 (текстовый LCP внутренних страниц). **Никогда** не `.reveal` |
| `lead-section` | `title`, `text`, `form` (args формы), `reveal` | — | `#lead`: `lead-call` + `lead-form`. Только при `starter_page_has_lead_form()` |
| `lead-form` | `form_id`, `title`, `text`, `submit_label`, `done_title`, `done_text`, `variant` (`contact` / `name_contact` / `full`), `service`, `source`, `reveal` | `starter_lead_hidden_fields()` | Единственная `form.js-lead` страницы — [`forms.md`](forms.md) |
| `lead-call` | `title`, `reveal` | телефон и мессенджеры компании | Карточка «позвонить / написать», **не** форма, без `js-lead` |
| `faq` | `location`, `title`, `id`, `reveal` | `starter_get_faqs_for()` | Без JS ответы открыты; `main.js` добавляет `.is-enhanced`. Место совпадает с FAQPage-схемой |
| `reviews` | `limit`, `title`, `reveal` | `starter_get_reviews()` | Сетка отзывов |
| `folio` | `limit`, `service`, `title`, `reveal`, `priority_first` | `starter_get_projects()` | Портфолио + лайтбокс (`starter:lightbox:open`); `priority_first` — первая плитка = LCP |
| `service-card` | `page` (WP_Post), `priority`, `reveal` | `starter_get_service_card()` | Карточка услуги, картинка — размер карточки |
| `post-card` | `priority`, `reveal`, `heading` (`h2` / `h3`) | текущий пост цикла | `priority` — только первая карточка первой страницы блога |
| `post-author` | `variant` (`byline` / `card`), `reveal` | `starter_get_post_author_data()` | Аватар — фильтр `starter_post_author_avatar` |
| `post-toc` | `items` (`id`, `text`, `level`) | `starter_render_post_toc()` | [`blog.md`](blog.md) |
| `embed-facade` | `src`, `title`, `kind` (`map` / `video` / `widget`), `ratio`, `link`, `link_label`, `button`, `allow`, `reveal` | аргументы / `starter_company_map()` | Заглушка с `aspect-ratio`, iframe из `data-src` только по клику; сторонних запросов до клика нет |

## Новый part

1. Блок встречается на ≥ 2 страницах прототипа или уже похож на существующий part → модификатор / аргумент, а не копия.
2. PHPDoc-шапка: назначение + `Args:` с типами и дефолтами (источник этого контракта).
3. Новые данные — сначала поле в ядре + `naming.json` + seed, потом part.
4. Строка в таблице выше (документ, потом код).
