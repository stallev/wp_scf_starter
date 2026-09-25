---
status: canonical
version: 1.0
updated: 2026-09-25
phase: 6
---

# Фаза 6: Динамика, формы, SEO, AI-visibility

Prev: [`phase-5-pages`](phase-5-pages.md) · Next: [`phase-7-launch`](phase-7-launch.md) · [`ROADMAP`](ROADMAP.md)

## Связанные документы

- [`contracts/forms.md`](../contracts/forms.md) · [`contracts/seo.md`](../contracts/seo.md) · [`contracts/template-parts.md`](../contracts/template-parts.md) · [`contracts/testing.md`](../contracts/testing.md)
- [`playbooks/analytics.md`](../playbooks/analytics.md) · [`project/seo-strategy.md`](../project/seo-strategy.md) · [`project/seo/meta-patterns.md`](../project/seo/meta-patterns.md) · [`project/llms.txt.md`](../project/llms.txt.md)

## Задачи

### T6.1 Динамические блоки
**Сделать:** FAQ (места — `starter_faq_locations`), отзывы, портфолио, карточки услуг, блог — из CPT / полей через parts.
**AC:**
- [ ] Happy: блоки из seed видны (e2e `dynamic`), FAQ-аккордеон работает
- [ ] Negative: пустые данные — блок не выводится; без JS контент читаем
- [ ] Security: N/A (только чтение)

### T6.2 Лид-форма и Telegram
**Сделать:** форма по контракту на страницах с `lead_form: true`; бот в «Заявки → Telegram» (тестовый).
**AC:**
- [ ] Happy: лид в админке, `is-sent`, событие `starter:lead:success`, сообщение в Telegram
- [ ] Negative: без согласия / контакта — 400 с сообщением; без кредов бота — лид сохраняется
- [ ] Security: неверный nonce — 403; honeypot — «успех» без записи; 6-я заявка за 10 мин — 429 (e2e `forms`)

### T6.3 SEO
**Сделать:** `schema` в записях `pages-map`, данные компании для LocalBusiness, мета Yoast по meta-паттернам, `noindex` служебных страниц.
**AC:**
- [ ] Happy: один граф JSON-LD с ожидаемыми pieces, title Yoast на каждой странице (e2e `seo`)
- [ ] Negative: `noindex`-страницы вне sitemap; лиды вне sitemap
- [ ] Security: нет второго `ld+json` (`check:naming`)

### T6.4 AI-visibility и аналитика
**Сделать:** текст `starter-core/data/llms.txt.md` под проект, список ботов (`starter_ai_bots`), GA4 ID в опциях.
**AC:**
- [ ] Happy: `/llms.txt` 200 `text/plain`, AI-группа в `robots.txt` при `blog_public = 1`
- [ ] Negative: `blog_public = 0` — AI-группа не дописывается
- [ ] Security: `gtag.js` не в HTML (e2e `perf-markup`), админы не трекаются

## Gate

- [ ] `npm run gate:6`
- [ ] `/doc-align` · `/phase-review`
