---
status: canonical
version: 1.0
updated: 2026-09-25
---

# Аналитика: отложенный GA4

Код — [`inc/analytics.php`](../../wp-content/themes/starter/inc/analytics.php) и [`assets/js/analytics.js`](../../wp-content/themes/starter/assets/js/analytics.js). Consent Mode / cookie-баннер — вне стартера (решение проекта; если нужен — сначала документ).

## Источник ID

- Поле `starter_company_ga4_id` в опциях «Компания»; `starter_get_ga4_id()` принимает только `^G-[A-Z0-9]+$`. Пусто или неверный формат = аналитика **выключена**.
- Default-ID в коде и seed нет (`check:naming`: `hardcoded-ga4-id`). Measurement ID публичен (виден в HTML), это не секрет, но и не данные стартера.
- Не трекаются: админка и пользователи с `manage_options` (фильтр `starter_analytics_enabled`).

## Как грузится

1. В HTML — только `analytics.js` (`defer`) и inline-заглушка перед ним: `window.STARTER_GA4 = { id, delayMs }`, `window.dataLayer`, `function gtag(){dataLayer.push(arguments)}`, `gtag('js')`, `gtag('config')`.
2. `analytics.js` вставляет `https://www.googletagmanager.com/gtag/js` **один раз** — через `delayMs` после `load` (`project.config.json` → `analytics.ga4_delay_ms`, 2500) **или** при первом `pointerdown` / `keydown` / `scroll` / `touchstart`, что раньше.
3. События до загрузки копятся в `dataLayer` и уходят позже — ничего не теряется.

*Почему:* `gtag.js` (~170 КБ, сторонний origin) в `<script src>` попадал в критический путь; кейс — render-blocking ~1.8 с и TBT ~130 мс в окне PSI. Не регистрировать `gtag.js` отдельным handle с зависимостью: зависимый без `strategy` делает его блокирующим (K2). `perf-markup` проверяет, что `gtag.js` нет в HTML.

**Компромиссы (осознанные):** посетитель, ушедший раньше `delayMs` без взаимодействия, не попадёт в GA4 — `delayMs` единственный рычаг; `page_view` уходит с задержкой; GA4 пакетирует события (~5 с), в Realtime они видны не сразу.

## События

| DOM / триггер | GA4 event | Параметры |
|---|---|---|
| `gtag('config')` | `page_view` | стандарт |
| `starter:lead:success` `{ formId, source }` | `generate_lead` | `form_id`, `lead_source`, `page_path` |
| `starter:lightbox:open` `{ group, index }` | `lightbox_open` | `lightbox_group`, `item_index`, `page_path` |
| клик `a[href^="tel:"]` | `click_phone` | `link_url`, `page_path` |
| клик `t.me` / `tg:` | `click_telegram` | то же |
| клик `wa.me` / `whatsapp:` | `click_whatsapp` | то же |
| клик `viber:` / `mailto:` | `click_viber` / `click_email` | то же |

Ключевые события в GA4 Admin: `generate_lead`, `click_phone`, мессенджеры. Новое событие: `CustomEvent` на `document` в `main.js` (имя — в `naming.json`) → слушатель в `analytics.js`; бизнес-логика в `analytics.js` не живёт.

## PSI и аналитика

GA4 on/off меняет lab-оценки: фиксировать в каждом прогоне (K7, делает `psi.mjs`), сравнивать только прогоны с одинаковым состоянием. Снимать аналитику ради оценки — нельзя без просьбы.

## Диагностика «gtag не грузится»

1. ID задан и валиден? Вы не залогинены админом? (иначе скрипт не подключается по замыслу).
2. В HTML есть `window.STARTER_GA4`? Нет — фильтр `starter_analytics_enabled` или пустой ID.
3. Подождать `delayMs` или кликнуть / прокрутить: запрос `gtag/js` появится в DevTools → Network, затем `g/collect`.
4. Нет запроса — исключить блокировку на клиенте: VPN / антивирус с DNS-фильтром трекеров, расширения-блокировщики. Кейс: VPN-резолвер отвечал NXDOMAIN на домен GA за ~11 с, браузер висел ~22 с. Проверять в окне без расширений и без VPN.
