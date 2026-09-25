---
description: Анализ готового отчёта PageSpeed Insights и предложения только по категориям вне зелёной зоны
argument-hint: "<psi-reports/<stamp>>"
---

# /psi-analyze — анализ отчёта PSI

Отчёт: `$ARGUMENTS` — каталог прогона (`summary.json`, `summary.md`, `runs/*.json`). Отчёт создаёт `npm run psi` (`tools/psi.mjs`, пороги — `tools/psi.config.json`), процедура и пороги — [`docs/playbooks/psi.md`](../../docs/playbooks/psi.md). Эта команда **ничего не запускает**: PSI — только по явному запросу пользователя и только на задеплоенном публичном URL.

## Шаги

1. Прочитать `summary.json`: `baseUrl`, `mode`, `runsPerPair`, `strategies`, `lighthouseVersions`, `pairs` (медианы по URL × стратегии, пометка `flaky`, GA4 on/off), `incomplete`. `baseUrl` на localhost / приватном адресе — отчёт недействителен, остановиться.
2. Для каждой пары — оценки категорий Performance / Accessibility / Best Practices / SEO. Зелёная зона — ≥ 90 (или `floor` из конфига PSI, когда он задан).
3. **Только** для категорий вне зелёной зоны открыть соответствующий `runs/*.json` и найти причину в `lighthouseResult.audits`: LCP-узел и фазы (`lcp-breakdown-insight`), CLS (`cls-culprits-insight`, `layout-shifts`), render-blocking, third-party, изображения, кэш, TTFB. Если аудит не вернул элемент — писать «не определён», не выдумывать.
4. Сопоставить с P1-механизмами (`AGENTS.md` → инвариант 7) и записью страницы в `pages-map.json` (`lcp`, `above_fold`): LCP в `.reveal`, скрипт без `strategy`, картинка без `priority` / с лишним `priority`, внешний origin, iframe без фасада, шрифты.
5. `flaky` или разброс > 10 пунктов — рекомендовать повтор с большим числом прогонов, а не правку кода.

## Не делать

- Предлагать правки для аудитов внутри зелёной категории.
- Сравнивать прогоны без медианы и без пометки GA4 on/off.
- Выводить или просить ключ `PAGESPEED_API_KEY`; ставить/снимать плагины и аналитику «заодно».

## Вывод

```text
Отчёт: <dir>, <baseUrl>, Lighthouse <v>, runs <n>, GA4 <on/off>
| URL | strategy | Perf | A11y | BP | SEO | flaky |
Вне зелёной зоны:
1. <URL/strategy/категория> — причина (аудит, узел) → предлагаемая правка (файл) → ожидаемый эффект
Запись для docs/project/perf-log.md: <дата | URL | warmup | mobile P/A/BP/SEO | desktop P/A/BP/SEO | GA4 | заметка>
```

Правки кода — только после подтверждения пользователя.
