---
description: Перенос одной страницы из prototype/ в шаблон WordPress по записи pages-map
argument-hint: "<url из pages-map, например /contacts/>"
---

# /port-page — цикл одной страницы

Страница: `$ARGUMENTS` (значение `url` из `pages-map.json`). Нет записи — остановиться: сначала запись в `pages-map.json` (утверждает человек), `npm run build:config`, `npm run check:config`.

## 1. Контекст

- Запись страницы в `pages-map.json`: `prototype`, `template`, `type`, `lead_form`, `noindex`, `schema`, `lcp`, `above_fold`, `psi`, `specs`.
- HTML прототипа `prototype/<prototype>` (read-only) и спецификация страницы из `specs` (если есть).
- L1-правила: `.cursor/rules/theme-templates.mdc`, `assets.mdc`, при SEO-изменениях `seo.mdc`.
- Уже существующие parts (`wp-content/themes/starter/template-parts/`) и API (`docs/contracts/naming-dictionary.md`).

## 2. Реализация

1. `<main>` прототипа → шаблон `template` из записи (`page.php` / `page-<slug>.php` / `front-page.php`); шапка и подвал уже в `header.php` / `footer.php`.
2. Блок, который есть на ≥ 2 страницах или уже есть в `template-parts/`, → part с аргументами.
3. Данные (телефоны, адреса, соцсети, FAQ, отзывы, проекты, карточки услуг) → API `starter_get_*()` / `starter_company_value()`; новые данные — сначала поле в ядре + словарь + seed.
4. Изображения → `starter_image()`; `priority` только у LCP-картинки (`lcp.kind = image`).
5. Блоки из `above_fold` и узел `lcp` — без `.reveal`; ниже первого экрана `.reveal` можно.
6. Лид-форма — по флагу `lead_form` (part `lead-form` / `lead-section`), одна на странице.
7. Страничные стили — секция `PAGE: <NAME>` в `main.css`; новые JS-фичи — блок `feature()` в `main.js`.
8. Ссылки прототипа на другие страницы → URL из `pages-map` (через `home_url()`), не относительные `*.html`.

## 3. Проверка

```bash
npm run build:config     # если менялся pages-map.json
npm run gate:0
npm run wp -- starter seed --dry-run
```

Открыть `http://localhost:8888<url>`: 200, нет PHP notices, визуально совпадает с прототипом. Затем обязательный `npm run gate:page -- <url>` (`check:config` + `check:hardcode` + `lint:php` + e2e `static` / `perf-markup` / `console` / `a11y` / `visual`, отфильтрованные по этому URL; отчёт при падении — `npx playwright show-report`).

## 4. Ревью и коммит

- Ревью diff против записи `pages-map` и контрактов — `/phase-review` отдельным агентом; изменился контракт — сначала документ.
- Одна страница — один коммит; красный gate — нет коммита.
