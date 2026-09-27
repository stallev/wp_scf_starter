# Модуль `catalog` (пример)

Отключаемый образец каталога товаров: CPT `starter_product` + иерархическая таксономия
`starter_product_family`, свои шаблоны (archive/taxonomy/single, отдаются через `template_include`),
Yoast graph piece `Product`/`Offer` и цели seed `product_families` / `products`. По умолчанию
выключен (`project.config.json` → `modules.catalog: false`) — это демонстрация паттерна, не готовая
e-commerce-функция (нет корзины, оформления заказа и т.п.).

## Модель URL

| URL | Шаблон |
|---|---|
| `/catalog/` (slug — фильтр `starter_catalog_slug`) | Архив `starter_product` — `templates/archive-starter_product.php` |
| `/catalog/family/<slug>/` | Архив категории `starter_product_family` — `templates/taxonomy-starter_product_family.php` |
| `/catalog/<product-slug>/` | Карточка товара — `templates/single-starter_product.php` |

Категории лежат под `/catalog/family/...`, а не прямо под `/catalog/...`, чтобы slug товара и slug
категории никогда не конфликтовали за один и тот же путь.

## Включение

1. `project.config.json` → `modules.catalog: true`.
2. `npm run build:config`.
3. Поднять `STARTER_CORE_VERSION` в `wp-content/mu-plugins/starter-core.php` (или один раз выполнить
   «сбросить rewrite» — `wp rewrite flush`) — таксономия и CPT меняют rewrite-правила.
4. `npm run wp -- starter seed --only=product_families,products` — демо-данные (нейтральные, без
   привязки к клиенту).

Выключение — обратная процедура (`modules.catalog: false` → `build:config`); при выключенном модуле
CPT, таксономия, шаблоны и seed-цели не регистрируются вовсе (seed для `product_families`/`products`
становится no-op, а не ошибкой).

## Поля товара (SCF `group_starter_product`)

`starter_product_price` (число), `starter_product_unit` (текст), `starter_product_sku` (текст),
`starter_product_characteristics` (repeater `label`/`value`) — короткий список характеристик под
описанием. Читать только по имени: `starter_field( 'starter_product_price', $post_id )`.

## Карточка товара

`starter_catalog_render_product_card( $args )` использует part темы `template-parts/product-card.php`,
если он есть в теме проекта; иначе — fallback модуля (`template-parts/product-card-fallback.php`).
Это тот же паттерн, что и `starter_get_service_card_pages()` в ядре: проект может переопределить
вёрстку, не трогая модуль.

## SEO

`Starter_Schema_ProductOffer` (`seo/class-starter-schema-productoffer.php`) добавляет `Product` (с
инлайновым `Offer`) на single и `ItemList` из `Product` на архиве/таксономии. В отличие от
`Starter_Schema_Service`, видимость не завязана на запись `pages-map.json` — товарные URL динамические
и pages-map их не перечисляет; условие — `is_singular( 'starter_product' )` /
`is_post_type_archive( 'starter_product' )` / `is_tax( 'starter_product_family' )` напрямую. Данные
предложения (`offers.price`) берутся из собственного поля товара `starter_product_price` — модуль
`catalog` не зависит от модуля `pricebook` (единый источник цен для калькуляторов — отдельный паттерн,
см. `../pricebook/README.md`). Расширение — фильтр `starter_schema_product`.

## Seed

`seed/modules/catalog/product-families.json` → таксономия, `seed/modules/catalog/products.json` →
CPT (цена, unit, sku, характеристики, категория по slug, картинка). Схемы —
`seed/schema/modules/catalog/*.schema.json`; проверка — `npm run check:seeds`. Идемпотентно: второй
`wp starter seed` не создаёт дублей (совпадающие термины/посты попадают в `unchanged`).
