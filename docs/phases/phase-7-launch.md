---
status: canonical
version: 1.0
updated: 2026-09-25
phase: 7
---

# Фаза 7: QA и запуск

Prev: [`phase-6-dynamic`](phase-6-dynamic.md) · Next: — (эксплуатация) · [`ROADMAP`](ROADMAP.md)

## Связанные документы

- [`playbooks/launch.md`](../playbooks/launch.md) · [`playbooks/psi.md`](../playbooks/psi.md) · [`contracts/testing.md`](../contracts/testing.md)
- [`project/qa-report.md`](../project/qa-report.md) · [`project/perf-log.md`](../project/perf-log.md) · [`project/manager-guide.md`](../project/manager-guide.md)

## Задачи

### T7.1 Полный регресс
**Сделать:** `npm run gate:7` (gate:0 + `test:tools` + все e2e + Firefox / WebKit smoke); ручной проход 360 / 768 / 1440 px и клавиатуры; итоги — `qa-report.md`.
**AC:**
- [ ] Happy: `gate:7` зелёный, отчёт заполнен
- [ ] Negative: каждое найденное вручную нарушение — issue или новая проверка
- [ ] Security: axe serious / critical — 0; секретов в репо нет

### T7.2 Деплой и чеклист запуска
**Сделать:** деплой `wp-content/` (без корня репо), импорт seed или перенос БД, все пункты [`launch.md`](../playbooks/launch.md).
**AC:**
- [ ] Happy: индексация включена, домен в canonical / OG / JSON-LD, хостинг-кэш и заголовки настроены, WebP регенерирован
- [ ] Negative: служебные страницы `noindex`; staging закрыт `X-Robots-Tag`
- [ ] Security: секреты ротированы, Telegram проверен тестовой заявкой, бэкапы включены

### T7.3 PSI baseline
**Сделать:** по явному запросу — `npm run psi` на проде (весь набор, GA4 on), разбор `/psi-analyze`, строки вех — в `perf-log.md`, `floor` в `tools/psi.config.json`.
**AC:**
- [ ] Happy: все категории ≥ 90 или записан план по категориям вне зелёной зоны
- [ ] Negative: `flaky`-пары перемерены с большим `--runs`, а не «починены»
- [ ] Security: ключ только в `.env`, в отчётах и логах его нет

### T7.4 Передача
**Сделать:** [`manager-guide.md`](../project/manager-guide.md) заполнен под проект и передан заказчику.
**AC:**
- [ ] Happy: менеджер меняет контакты, FAQ, меню без разработчика
- [ ] Negative: N/A
- [ ] Security: у менеджера своя учётная запись с ролью Editor

## Gate

- [ ] `npm run gate:7`
- [ ] Чеклист [`launch.md`](../playbooks/launch.md) закрыт (отметки в `qa-report.md`)
- [ ] Строка baseline в `perf-log.md`
- [ ] `/doc-align` · `/phase-review`
