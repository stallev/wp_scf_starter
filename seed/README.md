# seed/ — данные для импорта

JSON в этом каталоге импортирует mu-plugin `starter-core`:

```bash
npm run wp -- starter seed                      # всё
npm run wp -- starter seed --only=faq,reviews   # выбранные цели
npm run wp -- starter seed --dry-run            # отчёт без записи
```

То же из админки: **Инструменты → Starter Seed** (нужны права `manage_options`).

Каталог лежит в корне репозитория и на хостинг не уезжает. Локально `.wp-env.json` монтирует его в контейнер как `wp-content/starter-seed` (константа `STARTER_SEED_DIR`). На другом окружении задайте `STARTER_SEED_DIR` в `wp-config.php` или передайте `--dir=<путь>`.

Сейчас здесь **нейтральные демо-данные** (Demo Company, `example.com`, телефоны `+000…`). На проекте замените их данными клиента.

## Правила

1. **Имена — из словаря.** Поля, CPT и meta — только с префиксом проекта (`starter_*`). Новые сущности сначала описываются в контракте, потом появляются в seed.
2. **Стабильный `slug`.** Запись находится по `slug` (страницы — по пути URL). Сменить slug = создать новую запись; старую удалите вручную.
3. **Идемпотентность.** Повторный запуск не создаёт дубликатов: неизменённые записи попадают в `unchanged`, меню пересобирается только при изменении пунктов. Проверка: два прогона подряд дают одинаковые количества записей.
4. **Никаких секретов.** Ключи `token`, `password`, `secret`, `api_key`, `chat_id` и т. п. отклоняются загрузчиком. Telegram-бот настраивается в админке (Заявки → Telegram), а не через seed.
5. **Файл отсутствует — цель пропускается** с предупреждением. Ошибка JSON — ошибка прогона.
6. **Страницы не описываются здесь.** Их список — `pages-map.json` (через `npm run build:config` попадает в `config.generated.php`); сидер создаёт недостающие страницы и не трогает их контент. Пустой `pages-map` — цель `pages` ничего не делает.
7. **Изображения** (`image`, `default_image`): абсолютный URL или путь относительно этого каталога (например `images/project-1.jpg`). Импорт повторно использует уже загруженное вложение.

## Порядок целей

`company` → `pages` → `posts` → `faq` → `reviews` → `projects` → `service_cards` → `menus`. Модули (catalog, pricebook) добавляют свои цели фильтром `starter_seed_targets`.

## Файлы

| Файл | Цель | Куда пишет |
|---|---|---|
| `company.json` | `company` | Опции «Компания» (`starter-company`) |
| — (`pages-map.json`) | `pages` | Страницы, главная и страница блога |
| `posts.json` | `posts` | Записи блога + рубрики |
| `faq.json` | `faq` | CPT `starter_faq` |
| `reviews.json` | `reviews` | CPT `starter_review` |
| `projects.json` | `projects` | CPT `starter_project` |
| `service-cards.json` | `service_cards` | Поля карточки услуги на существующих страницах |
| `menus.json` | `menus` | Меню и области `primary` / `mobile` / `footer` |

### company.json

Объект; записываются только присутствующие ключи.

| Ключ | Тип | Поле SCF |
|---|---|---|
| `name`, `legal_name`, `description` | string | `starter_company_name`, `_legal_name`, `_description` |
| `business_type` | string | `starter_company_business_type` — подтип schema.org `LocalBusiness` |
| `phones` | `[{ number, label }]` | `starter_company_phones`; первый — основной |
| `email` | string | `starter_company_email` |
| `address` | `{ street, locality, region, postal_code, country, text }` | `starter_company_street` … `_address_text`; `country` — ISO 3166-1 alpha-2 |
| `geo` | `{ lat, lng }` (число или `null`) | `starter_company_geo_lat`, `_geo_lng` |
| `hours_text` | string | `starter_company_hours_text` |
| `opening_hours` | `[{ days: ["Monday", …], opens: "09:00", closes: "18:00" }]` | `starter_company_opening_hours` |
| `socials` | `[{ network, url, label }]`; `network`: telegram, whatsapp, viber, instagram, vk, facebook, youtube, other | `starter_company_socials` |
| `ga4_id` | string, `G-…` или `""` (выключено) | `starter_company_ga4_id` |
| `area_served` | string[] | `starter_company_area_served` |
| `price_range` | string | `starter_company_price_range` |
| `default_image` | string (путь/URL) | `starter_company_default_image` |

### posts.json, faq.json, reviews.json, projects.json

Объект `{ "items": [ … ] }`. Общие ключи элемента: `slug` и `title` — обязательны, `content` — HTML/блоки.

| Файл | Дополнительные ключи |
|---|---|
| `posts.json` | `excerpt`, `date` (`YYYY-MM-DD`), `categories` (`[{ name, slug }]`), `image` |
| `faq.json` | `title` — вопрос, `content` — ответ; `location` — `home` или slug страницы; `order` (число) |
| `reviews.json` | `author`, `location`, `service`, `date_label`, `rating` (1–5), `source_url`, `order` |
| `projects.json` | `excerpt`, `location`, `date_label`, `service` (slug услуги), `image`, `order` |

### service-cards.json

`{ "items": [ { "page": "/services/x/", "enabled": true, "title", "text", "price", "price_note", "badge", "order", "image" } ] }`. Карточка записывается на существующую страницу по пути; если страницы нет (её нет в `pages-map`), элемент пропускается с предупреждением.

### menus.json

`{ "menus": [ { "location": "primary", "name": "Primary", "items": [ … ] } ] }`. Пункт: `title` + одна цель — `page` (путь страницы), `post` (slug записи) или `url` (абсолютный или относительный от главной, например `/#contacts`); вложенность — `children`. Ненайденная страница/запись превращается в произвольную ссылку с предупреждением.

Формат каждого файла — JSON Schema в [`schema/`](schema/) (`<файл>.schema.json`). Проверка до импорта — `npm run check:seeds`: схема, уникальные slug, существование локальных картинок, ссылки меню и карточек на `pages-map` / `posts.json`, секреты и запрещённые имена из `naming.json`.
