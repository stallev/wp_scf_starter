---
status: canonical
version: 1.0
updated: 2026-09-25
---

# Контракт ядра `starter-core` (mu-plugin)

Ядро = данные и логика сайта: CPT, SCF-поля и опции, формы/лиды, seed, Yoast-схемы, `llms.txt`. Представление (enqueue фронта, шаблоны) — только в теме ([`theme.md`](theme.md)). Почему так — [`playbooks/architecture.md`](../playbooks/architecture.md). Имена — [`naming.json`](naming.json).

## Загрузка

[`starter-core.php`](../../wp-content/mu-plugins/starter-core.php) задаёт `STARTER_CORE_VERSION`, `STARTER_CORE_PATH` и подключает [`boot.php`](../../wp-content/mu-plugins/starter-core/boot.php). Порядок в `boot.php`: config → helpers → defaults → CPT → options → поля → queries → forms → comments → admin → seo → llms-txt → seed → CLI → модули. Новый файл ядра — строка `require_once` в `boot.php`.

| Файл | Что |
|---|---|
| `config.php` | `starter_core_config( 'a.b' )`, `starter_core_pages()`, `starter_core_page_by_url()` — снимок конфигов |
| `helpers.php` | `starter_get_company()`, `starter_field()`, `starter_update_field()`, `starter_phone_href()`, `starter_money()`, `starter_get_ga4_id()`, `starter_get_default_image_id()`, шорткод `[starter_company]`, билдеры SCF-полей `starter_scf_*()` |
| `defaults.php` | Пустой скелет компании, соцсети, дни недели, статусы лида. Никаких данных клиента и default-ID |
| `post-types.php` | CPT `starter_lead` (непубличный), `starter_review`, `starter_project`, `starter_faq`; аргументы — фильтр `starter_post_type_args` |
| `options.php` | Страница опций `starter-company` (SCF), защищённая опция `starter_telegram_bot` |
| `fields/<entity>.php` | Одна SCF-группа на файл: `group_starter_<entity>`, поля `starter_<entity>_<field>` |
| `queries.php` | Провайдеры для шаблонов: `starter_get_faqs_for()`, `starter_get_reviews()`, `starter_get_projects()`, `starter_get_service_card_pages()`, `starter_get_service_card()` |
| `forms.php` | Лид-форма — [`forms.md`](forms.md) |
| `comments.php` | Комментарии выключены (фильтр `starter_disable_comments`, D23) |
| `admin-leads.php`, `admin-faq.php`, `admin-seed.php` | Колонки/фильтр заявок и экран Telegram; FAQ «По местам» (drag-reorder); «Инструменты → Starter Seed» |
| `seo.php`, `seo/`, `llms-txt.php`, `data/llms.txt.md` | [`seo.md`](seo.md) |
| `seed/`, `cli.php`, `class-starter-cli-command.php` | Seed и `wp starter seed` |
| `modules/<name>/module.php` | Точка расширения: грузится, если `project.config.json` → `modules.<name>: true` и файл есть |

## Публичный API

Шаблоны и тема читают данные **только** через эти функции (инвариант 2 `AGENTS.md`). Полный список — раздел «Public PHP API (mu-plugin)» в [`naming-dictionary.md`](naming-dictionary.md); `check:naming` проверяет, что каждое имя объявлено в коде.

- `starter_get_company( 'address.locality' )` — NAP и реквизиты одним массивом (ключи — PHPDoc функции). Кэш на запрос, не раньше `acf/init`.
- `starter_field( $name, $post_id )` / `starter_update_field()` — SCF по **name**; опции — `get_field( 'starter_…', 'option' )`. По `field_…` key — запрещено (`check:naming`).
- `starter_core_config()` — конфиг проекта. Прямо читать `project.config.json` из PHP нельзя: корень репо не деплоится (D22).

## Хуки

Канонический список фильтров и actions — `naming.json` (роль «Filters», «Actions»). Ключевые точки расширения:

| Хук | Зачем |
|---|---|
| `starter_post_type_args` | Сменить slug/видимость CPT без правки ядра (затем поднять версию) |
| `starter_company` | Дополнить данные компании |
| `starter_lead_created` (action) | Реакция на новый лид (Telegram висит здесь) |
| `starter_lead_rate_limit`, `starter_lead_required_fields`, `starter_lead_skip_telegram`, `starter_lead_client_ip` | Контур формы |
| `starter_seed_targets`, `starter_seed_path`, `starter_seed_forbidden_keys` | Модули добавляют свои цели seed |
| `starter_faq_locations`, `starter_schema_*`, `starter_ai_bots`, `starter_llms_txt_*` | FAQ-места, схемы, AI-видимость |

## Опции, meta, transients

Таблица «что ядро пишет в базу» — README → «Данные и очистка» (единственное место). Правила: секреты — только защищённая опция с `autoload=false` (образец `starter_telegram_bot`), не SCF и не seed; скрытые meta — с подчёркиванием (`_starter_seed_source`).

## Seed

`npm run wp -- starter seed [--only=company,pages,…] [--dry-run] [--dir=…]` и «Инструменты → Starter Seed» вызывают один `starter_seed_run()` ([`seed/runner.php`](../../wp-content/mu-plugins/starter-core/seed/runner.php)). Формат файлов, порядок целей и правила идемпотентности — [`seed/README.md`](../../seed/README.md). Страницы создаются по `pages-map.json`, их контент сидер не трогает. Проверки: `npm run check:seeds` (схемы, ссылки, секреты), `npm run check:seed-idempotent` (второй прогон = без изменений).

## Снимок конфигов

`project.config.json` + `pages-map.json` → `npm run build:config` → `config.generated.php` (коммитится, руками не править). Несвежий снимок ловит `npm run check:config`.

## Версия и rewrite

У mu-plugin нет activation hook, поэтому `starter_core_maybe_flush_rewrites()` сбрасывает rewrite один раз при смене `STARTER_CORE_VERSION` (K12). **Поднять версию** обязательно при изменении аргументов CPT (rewrite slug, `public`, `has_archive`), rewrite-правил или query vars (`/llms.txt`) — иначе на живом сайте останутся старые правила.

## Безопасность

Вход: `wp_unslash()` + `sanitize_*()`; выход: `esc_*()`; каждое действие — nonce + `current_user_can()`. Front-end enqueue в ядре запрещён (`check:naming`, правило `frontend-enqueue-in-core`); admin-assets — только на своих экранах через `admin_enqueue_scripts`.
