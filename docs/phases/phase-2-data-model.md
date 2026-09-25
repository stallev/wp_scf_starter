---
status: canonical
version: 1.0
updated: 2026-09-25
phase: 2
---

# Фаза 2: Модель данных

Prev: [`phase-1-prototype`](phase-1-prototype.md) · Next: [`phase-3-core`](phase-3-core.md) · [`ROADMAP`](ROADMAP.md)

## Связанные документы

- [`contracts/data-structures.md`](../contracts/data-structures.md) · [`contracts/naming.json`](../contracts/naming.json) · [`contracts/mu-plugin.md`](../contracts/mu-plugin.md)
- [`seed/README.md`](../../seed/README.md) · [`seed/schema/`](../../seed/schema/)
- Спецификации страниц в `docs/project/pages/` · [`project/prds/_template.md`](../project/prds/_template.md)

## Задачи

### T2.1 Сущности и источники истины
**Сделать:** по инвентаризации фазы 1 решить для каждого блока: опции компании / поле страницы / CPT / `post` / вёрстка. Новая бизнес-сущность — PRD по шаблону и строка в [`data-structures.md`](../contracts/data-structures.md) проекта. Каталог / прайс — модули (`project.config.json` → `modules`).
**AC:**
- [ ] Happy: у каждого факта одно место правки
- [ ] Negative: нет CPT для блога (`post` + `category`), нет данных, дублирующих опции компании
- [ ] Security: персональные данные — только лиды (непубличный CPT)

### T2.2 Имена
**Сделать:** новые CPT, поля (`starter_<entity>_<field>`), группы, хуки, опции — сначала в `naming.json`, `npm run build:naming`.
**AC:**
- [ ] Happy: `npm run check:naming` зелёный
- [ ] Negative: имя без префикса или из `forbidden` — ошибка проверки
- [ ] Security: секреты не моделируются как SCF-поля (только защищённые опции)

### T2.3 Seed клиента
**Сделать:** `seed/*.json` с данными клиента (компания, FAQ, отзывы, проекты, посты, карточки услуг, меню); новые файлы — JSON Schema в `seed/schema/`. Картинки — `seed/images/`.
**AC:**
- [ ] Happy: `npm run check:seeds` зелёный; slug-и стабильны и уникальны
- [ ] Negative: меню / карточки ссылаются только на URL из `pages-map` (иначе ошибка)
- [ ] Security: нет токенов, паролей, `chat_id`, ключей (загрузчик и `check:seeds` отклоняют)

## Gate

- [ ] `npm run gate:2`
- [ ] `/doc-align` · `/phase-review`
