---
status: canonical
version: 1.0
updated: 2026-09-25
---

# SEO-контракт

Здесь — механизм. Тексты title / H1 / description — `docs/project/` ([`pages/_template.md`](../project/pages/_template.md), [`seo/meta-patterns.md`](../project/seo/meta-patterns.md)). Код: [`seo.php`](../../wp-content/mu-plugins/starter-core/seo.php), [`seo/`](../../wp-content/mu-plugins/starter-core/seo/), [`llms-txt.php`](../../wp-content/mu-plugins/starter-core/llms-txt.php). Проверка — e2e `seo`.

## Кто за что отвечает

| Слой | Ответственный | Как |
|---|---|---|
| Title, meta description, canonical, OG / Twitter | **Yoast** | Тема даёт только `title-tag`; своя генерация запрещена (`check:naming`: `custom-title`) |
| WebSite, WebPage, BreadcrumbList, Organization | **Yoast graph** | Имя / `legalName` Organization — из опций компании (фильтр `wpseo_schema_organization`) |
| LocalBusiness (NAP, geo, часы, `areaServed`) | `Starter_Schema_LocalBusiness` | На всех страницах, один `@id` `…/#/schema/localbusiness`; есть, если задано имя компании |
| Service | `Starter_Schema_Service` | `type: service` или `Service` в `schema` записи; `provider` → `@id` LocalBusiness; доп. поля — фильтр `starter_schema_service` |
| FAQPage | `Starter_Schema_FAQPage` | Только если `schema` записи содержит `FAQPage` **и** у места есть FAQ; тексты = видимый блок `faq` |
| XML sitemap | **Yoast** | Минус страницы с `noindex` из `pages-map` |
| `/llms.txt`, AI-группа в `robots.txt` | ядро | См. ниже |
| geo meta | тема (`inc/head.php`) | Из адреса и координат компании |

**Запрещено:** второй `<script type="application/ld+json">` вне графа Yoast (`check:naming`: `second-json-ld`), NAP-литералы в классах схем, Yoast Local SEO как источник NAP.

## Graph pieces

Класс `Starter_Schema_<Type>` в `starter-core/seo/`: свойства `$identifier`, `$context`; методы `is_needed()`, `generate()`. Регистрация — `starter_yoast_schema_graph_pieces()` на `wpseo_schema_graph_pieces`. Связи — по `@id`. Новый тип — сначала значение в `enum` поля `schema` схемы [`pages-map.schema.json`](../../schemas/pages-map.schema.json) и в `naming.json`, потом класс. API Yoast — через Context7.

## `pages-map.json` → SEO

| Поле | Эффект |
|---|---|
| `schema: ["LocalBusiness", "Service", "FAQPage"]` | Какие свои pieces **ожидаются** на странице (проверяет e2e `seo`); `Service` / `FAQPage` включают их |
| `noindex: true` | `wpseo_robots` → `noindex, follow`; ID в `wpseo_exclude_from_sitemap_by_post_ids`; страницы нет в `/llms.txt`. Такая страница без формы и без PSI |
| `type` | `service` → Service; `front` → FAQ-место `home` |

После правки — `npm run build:config`.

## Индексация по окружениям

`env-setup` ставит `blog_public = 0` локально. Staging закрывать `X-Robots-Tag: noindex` на сервере, **не** паролем (PSI и проверки не пройдут basic-auth). Включение индексации — пункт [`launch.md`](../playbooks/launch.md).

## `/llms.txt`

Курируемый Markdown: шаблон [`data/llms.txt.md`](../../wp-content/mu-plugins/starter-core/data/llms.txt.md) (путь — фильтр `starter_llms_txt_template`), плейсхолдеры `{{site_name}}`, `{{site_description}}`, `{{origin}}`, `{{pages}}` (индексируемые страницы `pages-map`), `{{phone}}`, `{{email}}`, `{{address}}`; доп. переменные — `starter_llms_txt_vars`. Отдаётся rewrite-правилом как `text/plain; charset=utf-8` + `X-Robots-Tag: noindex`. Yoast-генерацию llms.txt не включать (`check:naming`: `yoast-llms-txt`). Проектная заметка — [`project/llms.txt.md`](../project/llms.txt.md).

## `robots.txt`: AI-группа

При `blog_public = 1` в конец дописывается одна группа `User-agent:` для всех ботов из `starter_ai_bots` (`[]` — выключить) с **копией** правил группы `*` + `Allow: /`. Копия нужна: бот слушает только самую специфичную группу, голый `Allow: /` открыл бы ему `/wp-admin/`. Фильтр стоит после Yoast (приоритет 100000).
