---
status: canonical
version: 1.0
updated: 2026-09-25
---

# Контракт лид-формы

Сервер — [`starter-core/forms.php`](../../wp-content/mu-plugins/starter-core/forms.php) (шапка файла — краткая версия этого контракта); разметка — [`template-parts/lead-form.php`](../../wp-content/themes/starter/template-parts/lead-form.php); клиент — блок lead-формы в [`main.js`](../../wp-content/themes/starter/assets/js/main.js). Проверки: e2e `static` (количество форм), `forms` (happy / negative / security).

## Правило страницы

- Страница с `lead_form: true` в `pages-map.json` — **ровно одна** `form.js-lead`; с `false` (служебные: политика, 404, поиск, `noindex`-страницы) — ни одной (K14).
- Решает `starter_page_has_lead_form()`; форма выводится через `lead-section` / `lead-form`.
- Остальные CTA-точки — карточка `lead-call` («позвонить / написать») со ссылкой на `#lead`, без полей и без `js-lead`.

*Почему одна:* каждая лишняя форма — копия полей, которую чинят синхронно на N страницах; ранний CTA закрывает кнопка звонка. Кейс: в исходном проекте 2–3 формы на странице заменили одной у подвала + карточки звонка, конверсионный путь не пострадал (основной канал — звонок).

## Поля

| `name` | Обязательно | Санитизация | Хранение |
|---|---|---|---|
| `contact` (алиас `phone`) | да (фильтр `starter_lead_required_fields`) | `sanitize_text_field`, ≤ 200 | meta `starter_lead_contact` |
| `name` | в вариантах `name_contact`, `full` — по разметке; сервер не требует | `sanitize_text_field`, ≤ 100 | `starter_lead_name` |
| `message` | нет | `sanitize_textarea_field`, ≤ 2000 | `post_content` |
| `service` | нет (hidden, услуга страницы) | ≤ 150 | `starter_lead_service` |
| `consent` | да (`1` / `on` / `yes` / `true`) | whitelist | `starter_lead_consent` |
| `source` | hidden, `starter_lead_source()` | `sanitize_title` | `starter_lead_source` |
| `action`, `starter_lead_nonce`, `starter_hp_company` | служебные | — | — |

Скрытые поля печатает только `starter_lead_hidden_fields( $source )`. Массивы вместо строк считаются пустыми. Статус лида — `starter_lead_status`: `new` → `in_progress` / `done` / `spam`.

## Транспорт и ответы

`POST admin-ajax.php`, `action=starter_submit_lead` (`wp_ajax_` + `wp_ajax_nopriv_`). Порядок проверок и ответы:

| Шаг | Условие | Ответ |
|---|---|---|
| 1 | nonce пуст / неверен (`starter_lead_submit`) | `403 { code: nonce }` |
| 2 | honeypot заполнен | `200` успех — **тот же**, что у настоящего лида; ничего не пишется и не отправляется |
| 3 | лимит исчерпан | `429 { code: rate_limit }` |
| 4 | нет согласия | `400 { code: consent }` |
| 5 | нет обязательного поля | `400 { code: <field> }` |
| 6 | `wp_insert_post` не удался | `500 { code: save }` |
| 7 | сохранено | `200 { message, accepted: true }`, action `starter_lead_created( $id, $data )` |

Ошибки — `wp_send_json_error( { message, code }, status )`; клиент показывает `data.message`.

## Rate limit

5 успешных лидов / 10 минут на IP (фильтр `starter_lead_rate_limit`); ключ — `starter_lead_rl_<hash>`, сырой IP не хранится. IP — только `REMOTE_ADDR`; за прокси реальный адрес подставляет веб-сервер, доверяя `X-Forwarded-For` лишь от своих балансировщиков (README → «Безопасность форм»).

## Клиент (UX)

- Валидация до отправки (обязательные поля, согласие), `fetch` с `credentials: same-origin`.
- Состояния формы: `is-sending` → `is-sent` (показ `.lead-form__done`, фокус на нём) или `is-error` (текст в статусе). **Без** reload и redirect.
- Успех → `CustomEvent('starter:lead:success', { detail: { formId, source } })` на `document`; `analytics.js` → `generate_lead`.
- Ссылка на политику ПДн у чекбокса — новая вкладка (`target="_blank" rel="noopener"`), страница с формой не уходит.
- Тексты ошибок — `window.STARTER_LEAD.i18n` (`inc/assets.php`).

## Telegram

`starter_notify_telegram_lead()` на `starter_lead_created`: HTML-сообщение, контакт кликабелен (`t.me` / `tel:`), ссылка на лид в админке. Креды — `starter_get_telegram_bot_credentials()` из защищённой опции `starter_telegram_bot` (`autoload=false`), настраиваются в «Заявки → Telegram»; токен обратно не печатается. Без кредов лид сохраняется, уведомление пропускается. Пропуск по фильтру `starter_lead_skip_telegram` (e2e: опция `starter_e2e_mode`, действует только при `WP_ENVIRONMENT_TYPE=local`). Лог ошибок — только при `WP_DEBUG`, токен редактируется.

## Хранение

CPT `starter_lead`: `public => false`, без REST, поиска, архива и rewrite; создавать вручную нельзя; видят Editor и Admin (`edit_others_posts`). Не попадает в sitemap (проверка e2e `seo`).
