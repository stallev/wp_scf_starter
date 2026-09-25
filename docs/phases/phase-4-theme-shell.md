---
status: canonical
version: 1.0
updated: 2026-09-25
phase: 4
---

# Фаза 4: Оболочка темы

Prev: [`phase-3-core`](phase-3-core.md) · Next: [`phase-5-pages`](phase-5-pages.md) · [`ROADMAP`](ROADMAP.md)

## Связанные документы

- [`contracts/theme.md`](../contracts/theme.md) · [`contracts/template-parts.md`](../contracts/template-parts.md) · [`contracts/images.md`](../contracts/images.md)
- [`playbooks/performance.md`](../playbooks/performance.md) §Шрифты, §Render-blocking · [`playbooks/prototype-rules.md`](../playbooks/prototype-rules.md)
- Правила зоны: `.cursor/rules/assets.mdc`, `theme-templates.mdc`

## Задачи

### T4.1 Токены и базовые стили
**Сделать:** значения TOKENS / BASE / LAYOUT / компонентов `main.css` — из прототипа (правила компонентов стартера сохраняются, меняются значения токенов); порядок секций не нарушать.
**AC:**
- [ ] Happy: шапка, подвал, кнопки, формы визуально как в прототипе
- [ ] Negative: нет `!important` вне UTILITIES, нет сырых цветов в компонентах
- [ ] Security: нет внешних CSS / CDN

### T4.2 Шрифты
**Сделать:** woff2-подмножества в `assets/fonts/`, `@font-face` в начале `main.css`, `project.config.json` → `fonts.preload` (2–4 файла первого экрана), `npm run fonts:fallback -- --font=… --family="…"` → fallback-face в `main.css`.
**AC:**
- [ ] Happy: preload = конфиг, у каждого `crossorigin` (e2e `perf-markup` `@fonts`)
- [ ] Negative: нет Google Fonts (`check:naming`), нет `.woff`
- [ ] Security: N/A

### T4.3 Шапка, подвал, меню
**Сделать:** `site-header` / `site-footer` под разметку прототипа, walker-ы, fallback-навигация; NAP только из `starter_get_company()`; меню из `seed/menus.json`.
**AC:**
- [ ] Happy: desktop-меню с дропдаунами, мобильный drawer, skip link работают (e2e `navigation`)
- [ ] Negative: без назначенного меню — fallback из `pages-map`, без ошибок
- [ ] Security: нет литералов телефона / e-mail (`check:hardcode`)

### T4.4 Общие parts и скрипты
**Сделать:** parts для блоков ≥ 2 страниц (инвентаризация фазы 1), фичи `main.js` блоками `feature()`; скрипты — `starter_script_args()`.
**AC:**
- [ ] Happy: parts принимают `reveal` / `priority` по контракту
- [ ] Negative: страница без блока — фича молча выходит, консоль чистая
- [ ] Security: вывод экранирован; данные для JS — `wp_add_inline_script`

## Gate

- [ ] `npm run gate:4`
- [ ] `/doc-align` · `/phase-review`
