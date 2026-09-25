---
status: canonical
version: 1.0
updated: 2026-09-25
---

# Сущности и источники истины

У каждого факта — одно место, где он правится. Всё остальное его читает. Имена полей — [`naming.json`](naming.json); форматы seed — [`seed/README.md`](../../seed/README.md).

## Карта

| Сущность | Источник истины | Как меняется | Кто читает |
|---|---|---|---|
| Параметры проекта: префикс, slug-и, URL окружений, шрифты, размеры картинок, GA4-задержка, модули | `project.config.json` | Правка + `npm run build:config` | PHP через `starter_core_config()`; tools; e2e |
| Список страниц и их проверки: URL, прототип, шаблон, тип, форма, `noindex`, схемы, LCP, первый экран, PSI, спецификации | `pages-map.json` | Человек (фаза 1) + `build:config` | Сидер страниц, SEO-слой, тема (`starter_page_config()`), e2e, `psi`, `lint:prototype`, `/llms.txt` |
| Компания: NAP, телефоны, соцсети, часы, geo, `areaServed`, GA4 ID, запасная картинка | Опции SCF `starter-company` | Админка «Компания» (первично — `seed/company.json`) | `starter_get_company()` → шапка, подвал, контакты, LocalBusiness, `llms.txt`, geo meta |
| Контент страниц | Страницы WP (редактор) | Админка; сидер создаёт пустые, контент не трогает | Шаблоны |
| Разметка страниц из прототипа | `prototype/` → шаблон темы | `/port-page` | — |
| Карточка услуги (заголовок, текст, цена-строка, бейдж, порядок, картинка) | SCF-группа `group_starter_service_card` на странице услуги | Админка / `seed/service-cards.json` | `starter_get_service_card_pages()` → `service-card` |
| FAQ | CPT `starter_faq` (вопрос = title, ответ = content, `starter_faq_location`, `starter_faq_order`) | Админка «FAQ → По местам» / `seed/faq.json` | `faq` part + FAQPage-схема |
| Отзывы | CPT `starter_review` | Админка / seed | `reviews` |
| Портфолио | CPT `starter_project` (`starter_project_service` — slug услуги) | Админка / seed | `folio` |
| Блог | `post` + `category` | Редактор / seed | [`blog.md`](blog.md) |
| Заявки | CPT `starter_lead` (private) + meta `starter_lead_*` | Только форма ([`forms.md`](forms.md)); статус — админка | Админка, Telegram |
| Меню | WP Menus в областях `primary` / `mobile` / `footer` | «Внешний вид → Меню» / `seed/menus.json` | Walker-ы; без меню — fallback из `pages-map` |
| Telegram-бот | Опция `starter_telegram_bot` (`autoload=false`) | «Заявки → Telegram» | `forms.php` |
| Секреты tools (PSI-ключ) | `.env` | Вручную, вне git | `tools/psi.mjs` |
| Title / description / OG | Yoast (мета объекта) | Админка Yoast | Yoast |
| Тексты страниц, семантика, meta-паттерны | `docs/project/` | Проект | Человек → админка / seed |

## Правила

- **NAP только в опциях компании.** Литерал телефона, адреса, e-mail или цены в теме — ошибка `check:hardcode` (K17). Кейс: в исходном проекте телефон оказался захардкожен в 8 шаблонах при готовом провайдере.
- **Страницы не описываются в seed.** Их список — `pages-map.json`; новая страница = запись там, а не в сидере или тестах.
- **Цена в карточке услуги — отображаемый текст**, не расчётный прайс. Прайс с формулами — модуль `pricebook` (M8), свой источник.
- **Блок, данные которого меняет менеджер, — поля SCF**, а не вёрстка; блок, который меняет только разработчик, — вёрстка шаблона.
- **Новая сущность:** контракт → `naming.json` → поле / CPT в ядре → схема `seed/schema/*.json` → seed → шаблон.
- **Кэш:** провайдеры кэшируют на запрос (`static`); дорогие выборки — transient `starter_*` с инвалидацией на `save_post_<cpt>` / сохранении опций.
