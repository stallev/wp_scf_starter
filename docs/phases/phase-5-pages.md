---
status: canonical
version: 1.0
updated: 2026-09-25
phase: 5
---

# Фаза 5: Перенос страниц

Prev: [`phase-4-theme-shell`](phase-4-theme-shell.md) · Next: [`phase-6-dynamic`](phase-6-dynamic.md) · [`ROADMAP`](ROADMAP.md)

## Связанные документы

- [`/port-page`](../../.claude/commands/port-page.md) — процедура одной страницы
- [`contracts/theme.md`](../contracts/theme.md) · [`contracts/template-parts.md`](../contracts/template-parts.md) · [`contracts/images.md`](../contracts/images.md) · [`contracts/blog.md`](../contracts/blog.md)
- [`playbooks/performance.md`](../playbooks/performance.md) (чеклист блока) · спецификации страниц `docs/project/pages/`

## Задачи

### T5.N Страница `<url>` (по одной задаче на запись `pages-map`)
**Сделать:** `/port-page <url>`: `<main>` прототипа → шаблон из записи; повторяющиеся блоки → parts; данные → провайдеры; картинки → `starter_image()`; ссылки → URL `pages-map`; стили — секция `PAGE: <NAME>`.
**AC:**
- [ ] Happy: 200, визуально = прототип (e2e `visual`), `npm run gate:page -- <url>` зелёный
- [ ] Negative: LCP и `above_fold` вне `.reveal`; ровно одна / ноль `form.js-lead` по `lead_form`; консоль без 404
- [ ] Security: нет литералов NAP / цен / домена (`check:hardcode`), вывод экранирован

### T5.B Блог
**Сделать:** `home.php` / `single.php` / `archive.php` под разметку прототипа по [`blog.md`](../contracts/blog.md).
**AC:**
- [ ] Happy: индекс, пост с TOC (≥ 600 слов, ≥ 2 заголовка), пагинация
- [ ] Negative: пост без обложки и короткий пост — без ошибок и без TOC
- [ ] Security: контент поста — `wp_kses_post` / `the_content`

Одна страница — один коммит при зелёном `gate:page`.

## Gate

- [ ] `npm run gate:page -- <url>` для каждой записи `pages-map`
- [ ] `npm run gate:5`
- [ ] `/doc-align` · `/phase-review`
