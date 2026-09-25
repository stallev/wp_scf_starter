---
status: canonical
version: 1.0
updated: 2026-09-25
phase: 1
---

# Фаза 1: Приёмка прототипа

Prev: [`phase-0-init`](phase-0-init.md) · Next: [`phase-2-data-model`](phase-2-data-model.md) · [`ROADMAP`](ROADMAP.md)

## Связанные документы

- [`playbooks/prototype-rules.md`](../playbooks/prototype-rules.md) · [`playbooks/performance.md`](../playbooks/performance.md)
- [`schemas/pages-map.schema.json`](../../schemas/pages-map.schema.json) · [`contracts/template-parts.md`](../contracts/template-parts.md)
- [`project/sitemap.md`](../project/sitemap.md) · [`project/pages/_template.md`](../project/pages/_template.md) · [`project/pages/_checklist.md`](../project/pages/_checklist.md)

## Задачи

### T1.1 Аудит по регламенту
**Сделать:** прототип в `prototype/` (read-only после приёмки); `npm run lint:prototype`; ручная проверка того, что линтер не ловит (токены, DRY, иерархия заголовков, доступность, SEO-набор).
**AC:**
- [ ] Happy: `lint:prototype` зелёный, список ручных замечаний передан заказчику / верстальщику
- [ ] Negative: найденная ошибка прототипа не правится агентом — фиксируется в спецификации страницы
- [ ] Security: в прототипе нет ключей, реальных токенов, чужих трекеров; `noindex` на всех страницах

### T1.2 `pages-map.json`
**Сделать:** запись на каждую страницу: `url`, `title`, `prototype`, `template`, `type`, `lead_form`, `noindex`, `schema`, `lcp` (селектор + `text` / `image`), `above_fold`, `psi` (один представитель шаблона), `specs`. `npm run build:config`.
**AC:**
- [ ] Happy: `npm run check:config` зелёный; `lint:prototype` сверяет формы, LCP и первый экран с записями
- [ ] Negative: служебные страницы — `lead_form: false`, `noindex: true`, `psi: false`
- [ ] Security: URL без персональных данных и параметров

### T1.3 Инвентаризация компонентов
**Сделать:** список блоков прототипа → существующий part / модификатор / новый part / страничная секция; данные блока → поле ядра или вёрстка. Результат — в [`project/sitemap.md`](../project/sitemap.md) (раздел «Компоненты»).
**AC:**
- [ ] Happy: каждый блок ≥ 2 страниц сопоставлен part-у ([`template-parts.md`](../contracts/template-parts.md))
- [ ] Negative: нет «нового компонента», который отличается от существующего 2–3 свойствами
- [ ] Security: N/A

### T1.4 Утверждение человеком
**Сделать:** показать `pages-map.json` и инвентаризацию заказчику / владельцу; зафиксировать решение в `project/sitemap.md` (журнал решений).
**AC:**
- [ ] Happy: запись «утверждено <дата>»
- [ ] Negative: без утверждения фаза 2 не начинается
- [ ] Security: N/A

## Gate

- [ ] `npm run gate:1`
- [ ] Утверждение `pages-map.json` человеком
- [ ] `/doc-align` · `/phase-review`
