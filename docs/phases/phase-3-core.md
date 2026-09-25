---
status: canonical
version: 1.0
updated: 2026-09-25
phase: 3
---

# Фаза 3: Ядро mu-plugin

Prev: [`phase-2-data-model`](phase-2-data-model.md) · Next: [`phase-4-theme-shell`](phase-4-theme-shell.md) · [`ROADMAP`](ROADMAP.md)

## Связанные документы

- [`contracts/mu-plugin.md`](../contracts/mu-plugin.md) · [`contracts/data-structures.md`](../contracts/data-structures.md) · [`contracts/naming.json`](../contracts/naming.json)
- [`playbooks/architecture.md`](../playbooks/architecture.md) · [`seed/README.md`](../../seed/README.md)
- Правила зоны: `.cursor/rules/mu-plugin.mdc`, `php.mdc`

## Задачи

### T3.1 CPT, поля, опции
**Сделать:** новые CPT в `post-types.php` (или модуле), группы в `fields/<entity>.php` (`acf/init`, чтение по name), подключение в `boot.php`. Изменены аргументы CPT / rewrite — поднять `STARTER_CORE_VERSION`.
**AC:**
- [ ] Happy: поля видны в админке, значения читаются `starter_field()` / `get_field( …, 'option' )`
- [ ] Negative: чтение по `field_…` key — ошибка `check:naming`
- [ ] Security: непубличные CPT без REST / rewrite; admin-действия с nonce + capability

### T3.2 Провайдеры
**Сделать:** `starter_get_*()` для каждой сущности, которую выводит тема (поля, порядок, fallback, кэш на запрос; дорогие выборки — transient с инвалидацией на `save_post_<cpt>`). PHPDoc на каждой.
**AC:**
- [ ] Happy: провайдер возвращает данные из seed; имя есть в `naming.json` (`check:naming` проверяет объявление)
- [ ] Negative: нет данных — пустой массив / `''`, не notice
- [ ] Security: входы `sanitize_*`, SQL только через `WP_Query` / `get_posts`

### T3.3 Seed
**Сделать:** импортёры новых целей (`starter_seed_targets`), поиск по стабильному slug, dry-run.
**AC:**
- [ ] Happy: `npm run wp -- starter seed` наполняет сайт; `--dry-run` ничего не пишет
- [ ] Negative: второй прогон — `created 0 / updated 0` (`npm run check:seed-idempotent`)
- [ ] Security: ключи-секреты отклоняются загрузчиком (`starter_seed_forbidden_keys`)

## Gate

- [ ] `npm run gate:3` + `npm run gate:0`
- [ ] `/doc-align` · `/phase-review`
