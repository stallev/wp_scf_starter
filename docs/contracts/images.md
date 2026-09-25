---
status: canonical
version: 1.0
updated: 2026-09-25
---

# Контракт изображений

Код — [`inc/images.php`](../../wp-content/themes/starter/inc/images.php) и [`inc/setup.php`](../../wp-content/themes/starter/inc/setup.php); почему так — [`playbooks/performance.md`](../playbooks/performance.md) §LCP и §WebP. Проверка разметки — e2e `perf-markup`.

## Размеры

| Размер | Параметры | Где |
|---|---|---|
| карточка (`project.config.json` → `images.card.name`, по умолчанию `card`) | ширина `images.card.width` (768), высота пропорциональна, без crop | Карточки услуг, постов, портфолио, отзывов |
| `large` | ядро WP | Обложка поста, лайтбокс |
| `full` | оригинал | Только `og:image` (Yoast) и источник лайтбокса; в карточках не выводить |

Имя размера — `starter_card_size()`, в Media Library подписан «Карточка N».

## Вывод: `starter_image( $id, $size, $args )`

Единственный способ печатать картинку в шаблоне (`<img>` вручную не писать).

- `priority => true` — `loading="eager"` + `fetchpriority="high"`. **Одна** на страницу — LCP-картинка из `pages-map` (`lcp.kind: image`), никогда внутри `.reveal`.
- Остальные — `loading="lazy"`. `loading` всегда явный: иначе ядро WP само повесит `fetchpriority="high"` на первую «большую» картинку, даже под сгибом (K3).
- Всегда `decoding="async"`, `width` / `height` из размера (резерв места, CLS = 0); `sizes` — аргументом, если ширина в вёрстке не совпадает с размером.
- `alt` — из вложения; пустой alt не выдумывать (декоративная картинка). Аргумент `alt` — только осознанное переопределение.
- `$id = 0` → запасная картинка компании (`starter_get_default_image_id()`), `fallback => false` — не подставлять.

## WebP

- Новые **подразмеры** JPEG/PNG пишутся в WebP (`image_editor_output_format`), только если GD/Imagick умеют WebP; качество — `images.webp_quality` (80).
- **Оригинал не трогается** (D24, K11): URL `full` для `og:image` и лайтбокса не меняется, перегенерация обратима.
- `srcset` — только WebP-кандидаты, если они есть (`starter_srcset_webp_only`): иначе hi-DPI экран выберет тяжёлый оригинал.
- `max_srcset_image_width` **не** ограничивать: лайтбокс берёт крупные кандидаты.

Кейс: PNG с альфой → WebP q80 ≈ −55 %, фото ≈ −15–20 %; для фото рычаг слабый, чудес на LCP не ждать.

## Регенерация уже загруженных вложений

Фильтр действует только на новые загрузки. Разовая процедура (на проде — пункт [`launch.md`](../playbooks/launch.md)):

1. «Здоровье сайта → Информация → Обработка медиа»: GD/Imagick поддерживает WebP. Нет — фильтр ничего не делает, остановиться.
2. **Бэкап** БД и `wp-content/uploads/`.
3. Пробный прогон на одном вложении: `wp media regenerate <id> --yes` (локально — `npm run wp -- media regenerate <id> --yes`; без WP-CLI — плагин Regenerate Thumbnails, после работы удалить). Проверить подразмеры `.webp` и страницу с этой картинкой.
4. Все вложения: `wp media regenerate --yes`.
5. Проверить `srcset` на 2–3 страницах, e2e `perf-markup` / `console` (нет 404 на старых URL подразмеров в контенте).

**Откат:** снять фильтр `image_editor_output_format` и перегенерировать ещё раз (оригиналы на месте) или вернуть `uploads/` из бэкапа.

## Статика темы: `npm run images:webp`

[`tools/convert-to-webp.mjs`](../../tools/convert-to-webp.mjs): `assets/images/source-photos/` → `assets/images/webp-photos/` (оба каталога в `.gitignore`), ширина ≤ `images.card.width`, качество из конфига, оригиналы не меняются. Только для картинок, которые поставляются с темой; контент — через Media Library.
