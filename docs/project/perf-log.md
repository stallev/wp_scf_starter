---
status: planned
version: 0.1
updated: 2026-09-25
---

# Журнал PSI: <Проект>

Только **вехи**: baseline после запуска, состояние после крупных изменений (шрифты, шаблоны, аналитика, хостинг). Процедура и пороги — [`playbooks/psi.md`](../playbooks/psi.md). Готовые строки — раздел «Строки для журнала прогонов» в конце `psi-reports/<UTC>/summary.md`; разбор — `/psi-analyze`.

Правила: медиана ≥ 3 прогонов; GA4 on/off в каждой строке (с аналитикой и без несравнимы); `flaky` — отметить в заметке; ключевая причина изменения — коротко в «Lab notes» (LCP-узел, CLS-виновник, коммит).

## Пороги

| Дата | mode | floor mobile P/A/BP/SEO | floor desktop P/A/BP/SEO | Основание |
|---|---|---|---|---|
| <YYYY-MM-DD> | report | — | — | до baseline |

## Прогоны

| Дата | URL | Warmup | Mobile P/A/BP/SEO | Desktop P/A/BP/SEO | GA4 | Lab notes |
|---|---|---|---|---|---|---|
| <YYYY-MM-DD> | `<https://домен/>` | GET 200; <server>; Cache-Control: <…>; TTFB <n> ms | <p / a / bp / seo> | <p / a / bp / seo> | on | **Baseline**, median of 3. <LCP-узел и время, CLS> |
