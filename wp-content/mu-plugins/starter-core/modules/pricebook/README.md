# Модуль `pricebook` (пример)

Отключаемый образец паттерна «единый источник цен → разметка → JSON-LD → калькулятор»: options page →
провайдер с transient-кэшем → JS, заполняющий разметку → тестируемая формула с эталонными проверками
через WP-CLI. По умолчанию выключен (`project.config.json` → `modules.pricebook: false`).

## Включение

1. `project.config.json` → `modules.pricebook: true`.
2. `npm run build:config`.
3. Открыть «Прайс-лист» в админке и заполнить позиции (или оставить демонстрационную позицию по
   умолчанию — `starter_demo_unit`, 100).
4. `npm run wp -- starter pricebook check` — сверить калькулятор с эталонными значениями.

## Провайдер

`starter_get_pricebook()` — все позиции (`key => { label, value, unit, note }`), transient
`starter_pricebook_v1`, инвалидация на сохранении экрана «Прайс-лист» (`acf/options_page/save`).
`starter_price( $key )` — числовое значение одной позиции. Базовая демо-позиция задана в коде
(`starter_pricebook_default_items()`), опции её дополняют/переопределяют по `key`.

## Фронтенд

Front-end enqueue **не** живёт в этом mu-plugin (инвариант 1 AGENTS.md, проверка `check:naming`
`frontend-enqueue-in-core`): модуль отдаёт только данные и URL своего скрипта
(`starter_pricebook_asset_url()`), а подключает его тема — `wp-content/themes/starter/inc/assets.php`,
внутри `starter_enqueue_assets()`, только когда `function_exists( 'starter_get_pricebook' )` (модуль
включён):

```php
if ( function_exists( 'starter_get_pricebook' ) ) {
	wp_enqueue_script( 'starter-pricebook-prices', starter_pricebook_asset_url(), array(), starter_pricebook_asset_version(), starter_script_args() );
	wp_add_inline_script( 'starter-pricebook-prices', 'window.STARTER_PRICES = ' . wp_json_encode( starter_get_pricebook(), JSON_UNESCAPED_UNICODE ) . ';', 'before' );
}
```

`assets/js/prices.js` (strategy `defer`) заполняет `[data-price="<key>"]` значением `price × qty`
(атрибут `data-price-qty`, по умолчанию 1 — тот же пример «цена × количество», что и
`starter_pricebook_calc_total()` в PHP) и шлёт `document.dispatchEvent( new CustomEvent( 'starter:prices:ready' ) )`.

## Калькулятор и эталонные проверки

`starter_pricebook_calc_total( $key, $qty )` — «цена × количество», округление до 2 знаков.
`starter_pricebook_etalon_checks()` сверяет калькулятор с зафиксированными эталонными значениями
(демо: `starter_demo_unit × 3 = 300`, `× 10 = 1000`) — паттерн из исходного проекта (§6 эталонов),
адаптированный под нейтральный пример. `wp starter pricebook check` печатает каждую проверку и
завершается ненулевым кодом при расхождении.

**На реальном проекте:** при изменении прайса нужно обновить и позиции в админке, и эталонные значения
в `starter_pricebook_etalon_checks()` — в одном коммите, чтобы проверка продолжала защищать от
случайного расхождения цены и формулы.

## Интеграция с каталогом (опционально)

Модуль не зависит от `catalog` и наоборот — у товара своя цена (`starter_product_price`). Если проекту
нужен единый прайс для карточек каталога и калькулятора, самый простой способ — заполнить цену товара
из `starter_price( $key )` при сохранении карточки (или через фильтр `starter_schema_product` для
JSON-LD), не создавая жёсткой связи между модулями.
