---
status: planned
version: 0.1
updated: 2026-09-25
---

# `/llms.txt` проекта

Текст файла правится **не здесь**, а в шаблоне ядра [`starter-core/data/llms.txt.md`](../../wp-content/mu-plugins/starter-core/data/llms.txt.md) (или в файле, на который указывает фильтр `starter_llms_txt_template`). Механизм и плейсхолдеры — [`contracts/seo.md`](../contracts/seo.md) → «`/llms.txt`».

## Что заполнить на проекте (фаза 6)

- H1 — название компании (`{{site_name}}`), blockquote — одно предложение: что, кому, где (`{{site_description}}` или текст).
- «Страницы» — `{{pages}}` строится из индексируемых записей `pages-map`; при необходимости добавить разделы вручную (услуги с одной строкой пояснения, блог).
- «Контакты» — только плейсхолдеры `{{phone}}`, `{{email}}`, `{{address}}`: NAP живёт в опциях «Компания».
- Без маркетинговой воды и без данных, которых нет на сайте.

Проверка: `http://localhost:8888/llms.txt` → 200 `text/plain`; e2e `seo`.
