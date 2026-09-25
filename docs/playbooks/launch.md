---
status: canonical
version: 1.0
updated: 2026-09-25
---

# Чеклист запуска («перед публикацией»)

Выполняется на фазе 7 ([`phases/phase-7-launch.md`](../phases/phase-7-launch.md)) после зелёного `npm run gate:7`. Каждый пункт — действие с проверкой; отметки и даты — в [`project/qa-report.md`](../project/qa-report.md).

## Индексация и домен

- [ ] **Индексация включена на проде:** «Настройки → Чтение» — снять «Попросить поисковые системы не индексировать» (`wp option update blog_public 1`). Проверка: нет `noindex` в `<head>` индексируемых страниц; `robots.txt` без `Disallow: /`, с AI-группой ([`contracts/seo.md`](../contracts/seo.md)).
- [ ] Staging закрыт `X-Robots-Tag: noindex` на сервере (не паролем — иначе PSI и проверки не пройдут).
- [ ] `project.config.json` → `urls.production` / `urls.staging` заполнены, `npm run build:config`. «Адрес WordPress» и «Адрес сайта» — боевой `https://` домен; canonical, `og:url`, URL в JSON-LD и `/llms.txt` — с боевого домена (grep HTML на staging-домен / `localhost` пуст).
- [ ] Один канонический хост: `http→https` и `www↔без www` — один 301, без цепочек (PSI: «multiple redirects»).
- [ ] Страницы с `noindex: true` в `pages-map` — `noindex, follow` и не в sitemap.

## Контент и медиа

- [ ] Yoast: title и description у каждой индексируемой страницы (по [`project/seo/meta-patterns.md`](../project/seo/meta-patterns.md)); кейс — страница без description дала SEO 92.
- [ ] OG-картинка по умолчанию (Yoast → Соцсети) 1200×630.
- [ ] Favicon-набор в `assets/icons/` заменён на проектный (`favicon.ico`, `.svg`, `apple-touch-icon.png`, 192/512, `site.webmanifest`); `theme-color` — фильтр `starter_theme_color`.
- [ ] Фото-заглушки заменены своими (с `alt`); в прототипе / контенте нет `ЗАГЛУШКА` и `___`.
- [ ] Опции «Компания» заполнены (NAP = профиль в Google Business Profile / Яндекс Бизнес), GA4 ID — боевой.
- [ ] Страница политики ПДн опубликована и назначена в «Настройки → Конфиденциальность» (ссылка у чекбокса формы).

## Хостинг (K15)

- [ ] **Page cache** для HTML (плагин хостинга / nginx fastcgi_cache / LiteSpeed); исключения — админка, `admin-ajax.php`, залогиненные. Кейс: без кэша отклик из региона аудитории 0.6–0.8 с.
- [ ] **`Cache-Control` для HTML** (короткий `max-age` или `must-revalidate` + `Age` от кэша) — сейчас часто нет вовсе.
- [ ] **`Cache-Control: public, max-age=31536000, immutable`** для `/wp-content/themes/*/assets/` и `/wp-content/uploads/` — файлы версионируются (`?ver=` filemtime, уникальные имена загрузок).
- [ ] HTTPS, HTTP/2+, сжатие (br / gzip) для HTML, CSS, JS, SVG.
- [ ] PHP ≥ 8.1 (как `project.config.json` → `php.min`), GD / Imagick с WebP.
- [ ] За прокси / балансировщиком: реальный IP клиента подставляет веб-сервер, доверяя `X-Forwarded-For` только от своих адресов (иначе обходится rate limit формы).

## WebP

- [ ] Регенерация подразмеров старых вложений в WebP — процедура с бэкапом и откатом, [`contracts/images.md`](../contracts/images.md) → «Регенерация» (K11). Проверка: `srcset` на 2–3 страницах только `.webp`.

## Формы и секреты

- [ ] **Telegram:** «Заявки → Telegram» — боевой бот и чат; тестовая заявка с прода дошла, лид в админке, затем удалить.
- [ ] **Ротация секретов:** пароли админки и хостинга, токен бота, PSI-ключ — не те, что светились в разработке / чатах / тикетах; `creds/`, `.env`, дампы БД не лежат в web root (проверить URL напрямую → 404).
- [ ] Учётные записи: нет `admin` / `password`, у каждого — свой пользователь с минимальной ролью.

## Поиск и мониторинг

- [ ] Google Search Console и Яндекс Вебмастер: подтвердить сайт, отправить `sitemap_index.xml` (Yoast).
- [ ] Google Business Profile / Яндекс Бизнес: NAP совпадает с сайтом и LocalBusiness.
- [ ] GA4 получает `page_view` и `generate_lead` (тестовая заявка).

## Бэкапы

- [ ] Автоматические бэкапы БД и `uploads/` (ежедневно, хранение ≥ 14 дней, вне сервера сайта); восстановление проверено один раз.

## PSI baseline

- [ ] После всех пунктов выше и прогрева кэша — `npm run psi` (по явному запросу): полный набор `psi: true`, mobile + desktop, GA4 on. Строки вех — в [`project/perf-log.md`](../project/perf-log.md); `floor` = худший результат − ~5 ([`psi.md`](psi.md) → «Пороги»).
