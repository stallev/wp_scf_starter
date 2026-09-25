---
status: canonical
version: 1.0
updated: 2026-09-25
---

# PageSpeed Insights: процедура

Уровень P3: lab-диагностика **задеплоенного** сайта через PSI API — [`tools/psi.mjs`](../../tools/psi.mjs), пороги — [`tools/psi.config.json`](../../tools/psi.config.json). Решения по скорости — [`performance.md`](performance.md); анализ готового отчёта — `/psi-analyze` ([`.claude/commands/psi-analyze.md`](../../.claude/commands/psi-analyze.md)).

## Когда

- Только вручную и **по явному запросу** пользователя. Не в CI, не на PR, не на каждый коммит: PSI мерит деплой, а не ветку.
- Коммит не ждёт оценок PSI; код по итогам не чинится, пока не попросили.
- Названа одна страница — `--paths=`; весь набор (`psi: true` в `pages-map`) — отдельный запрос.
- Вехи: baseline после запуска (фаза 7), после крупных изменений шаблонов / шрифтов / аналитики.

## Где

- Только публичный URL: `urls.production` (или `urls.staging` с `--target=staging`, или `--base`). PSI **не видит localhost** и приватные сети — скрипт отказывается с кодом 2 (K6). Локальный Lighthouse без троттлинга с PSI mobile несопоставим.
- Staging закрывать от индексации `X-Robots-Tag: noindex`, не паролем: PSI не проходит basic-auth.
- Мерить финальный URL: `http→https` и `www` дают редиректы («multiple redirects»); прогрев предупреждает о 3xx.
- Один клиент на ключ, вызовы последовательно с паузой `pauseMs`; параллельные прогоны — квота и `429`.

## Ключ

1. Google Cloud Console → APIs & Services: включить **PageSpeed Insights API**, создать ключ. API restrictions → только PageSpeed Insights API; Application restrictions → None (домашний IP плавающий); в Quotas — дневной cap.
2. `.env` в корне репо по образцу [`.env.example`](../../.env.example): `PAGESPEED_API_KEY=…` без кавычек, UTF-8 **без BOM**. В Windows PowerShell 5.1 не создавать через `>` / `Out-File` (пишут BOM / UTF-16) — редактором или `Set-Content -Encoding utf8NoBOM` в PowerShell 7 (K10).
3. Ключ не попадает в git, docs, `.cursor`, чат и логи. Скрипт шлёт его заголовком `X-Goog-Api-Key`, URL запроса не печатает, ошибки редактирует. Утёк — пересоздать.

## Запуск

```bash
npm run psi                                   # весь набор, mobile + desktop, runs из конфига
npm run psi -- --paths=/,/contacts/ --strategy=mobile --runs=1
npm run psi -- --mode=enforce                 # код 1 при нарушении floor
```

Git Bash превращает аргументы с `/` в пути Windows: запускать из PowerShell или с `MSYS_NO_PATHCONV=1` (K10). Коды выхода: 0 — ок (в `report` всегда), 1 — нарушен `floor` в `enforce`, 2 — нельзя мерить (localhost, нет ключа, конфиг, PSI недоступен, ни одного валидного прогона). Отчёт: `psi-reports/<UTC>/summary.md|json` + `runs/*.json` (gitignored).

Что делает скрипт: прогрев (статус, TTFB, `Cache-Control`, `Age`, заголовки кэша, GA4 on/off) → mobile, затем desktop, все 4 категории одним вызовом → ретраи на `429` / 5xx / `runtimeError` → медиана по прогонам, пометка `flaky` при разбросе Performance > 10. Кэш плагинов ради hit rate прогрева не отключать.

Время: URL × 2 стратегии × `runs` вызовов — полный набор идёт десятки минут, одна страница с `--runs=1` ≈ 30 с. Страницы с `noindex` в набор не входят (`psi: false`): SEO-аудит на них падает всегда.

## Пороги

Зелёная зона — **≥ 90** по Performance, Accessibility, Best Practices, SEO, mobile и desktop.

| Ключ `psi.config.json` | Смысл | Эффект |
|---|---|---|
| `thresholds.goal` | 90 по категориям | предупреждение в отчёте |
| `thresholds.floor.<strategy>` | жёсткий минимум; `null` — выключен | код 1 в `enforce` |
| `thresholds.budgets` | LCP ≤ 2500 мс, CLS ≤ 0.1, TBT ≤ 200 мс | предупреждение |

**Ввод `floor`:** `mode: report` → полный baseline после запуска (GA4 в рабочем состоянии) → `floor` = худший результат по всем URL стратегии **минус ~5** → `mode: enforce` → поднимать `floor` после улучшений. `floor` общий на стратегию, поэтому берётся минимум по URL.

## Как читать (Lighthouse 13)

Lighthouse 13 заменил часть аудитов «insight»-аудитами; скрипт понимает оба формата.

| Что | Поле ответа |
|---|---|
| Оценки | `lighthouseResult.categories.<id>.score` × 100 |
| LCP-узел и фазы (TTFB, load delay, render delay) | `audits['lcp-breakdown-insight']`, `lcp-discovery-insight` |
| CLS и причины | `layout-shifts`, `cls-culprits-insight` |
| Render-blocking | `render-blocking-insight` |
| Сеть, вес | `network-requests`, `total-byte-weight`, `network-dependency-tree-insight` |
| Картинки | `image-delivery-insight`, `image-size-responsive`, `unsized-images` |
| Третьи стороны | `third-parties-insight` (≤ 12: `third-party-summary`) |
| Кэш | `cache-insight` |
| JS | `unused-javascript`, `legacy-javascript-insight`, `duplicated-javascript-insight`, `bootup-time`, `long-tasks` |
| TTFB | `server-response-time`, `document-latency-insight` |
| Field (CrUX) | `loadingExperience`, `originLoadingExperience` |

Нет элемента в ответе — «не определён», не выдумывать LCP-узел.

Сопоставлять с записью `pages-map` (`lcp`, `above_fold`) и P1: большой `elementRenderDelay` → LCP в `.reveal`; большой `resourceLoadDelay` → LCP-картинка `lazy` / без `priority`; CLS от шрифтов → fallback-метрики; third-party в окне → GA4 / iframe без фасада.

## Выбросы и расхождения (K6)

- Медиана 3–5 прогонов, не лучший. `flaky` → повторить пару с большим `--runs`, а не чинить код. Разовый Best Practices, который на повторе 100, — flake.
- **Lab ≠ field:** Performance ≥ 90 может сосуществовать с плохим LCP у реальных пользователей (CrUX); у нового сайта CrUX пуст.
- **TTFB PSI ≠ TTFB из своей сети:** кейс — 2–4 мс в PSI против 0.7–1.3 с из офиса; серверный отклик проверять `curl` из региона аудитории.
- **UI ≠ API:** кейс — веб-интерфейс показал mobile 88 при серии API-прогонов 97–100 (регион, кэш, версия Lighthouse). Смотреть метрики, повторить 3–5 раз.

## Что не чинить

Правки — только если категория **вышла из зелёной зоны**, PSI явно назвал аудит **и** пользователь попросил (K8). Не трогать заранее: контраст при A11y ≥ 90, «unused JS» темы, «лишний preload», `fetchpriority` без LCP-узла, снятие GA4, установку / удаление плагинов «заодно».

## Журнал

Вехи — в [`project/perf-log.md`](../project/perf-log.md): строки готовы в конце `summary.md`. Остальные прогоны живут в локальных отчётах.
