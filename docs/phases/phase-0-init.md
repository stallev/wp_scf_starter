---
status: canonical
version: 1.0
updated: 2026-09-25
phase: 0
---

# Фаза 0: Init

Prev: — · Next: [`phase-1-prototype`](phase-1-prototype.md) · [`ROADMAP`](ROADMAP.md)

## Связанные документы

- [`AGENTS.md`](../../AGENTS.md) (плейсхолдеры, команды) · [`README.md`](../../README.md) (требования, окружение)
- [`contracts/naming.json`](../contracts/naming.json) · [`contracts/mu-plugin.md`](../contracts/mu-plugin.md) · [`contracts/theme.md`](../contracts/theme.md)
- [`project/README.md`](../project/README.md)

## Задачи

### T0.1 Переименование стартера
**Сделать:** на чистом дереве при остановленном wp-env — `npm run init -- --prefix=<p> --name="<Name>" --dry-run`, затем без `--dry-run`. Демо-данные очищаются (`--keep-demo` — оставить для примера).
**AC:**
- [ ] Happy: каталоги темы и ядра, функции, хуки, CPT, handles, text domain — с префиксом проекта; `npm run build:config` и `build:naming` отработали внутри init
- [ ] Negative: повторный `init` без `--force` отказывает (код 2); грязное дерево — отказ
- [ ] Security: в репо нет данных исходного проекта и секретов; `.env` отсутствует в git

### T0.2 Окружение
**Сделать:** `npm install`, `npm run composer -- install`, `npm run env:start`; `.env` из `.env.example`.
**AC:**
- [ ] Happy: http://localhost:8888 отвечает 200, тема и mu-plugin активны, постоянные ссылки `/%postname%/`, locale из конфига
- [ ] Negative: при `WP_DEBUG` нет PHP notices / warnings на главной и в админке
- [ ] Security: `blog_public = 0` локально; `.env` — UTF-8 без BOM, в `.gitignore`

### T0.3 Конфиг проекта
**Сделать:** `project.config.json` — `project.name`, `urls` (local / staging / production, если известны), locale; `npm run build:config`.
**AC:**
- [ ] Happy: `npm run check:config` зелёный
- [ ] Negative: несвежий снимок ловится `check:config`
- [ ] Security: в конфиге нет ключей и паролей

### T0.4 Проектная документация
**Сделать:** завести `docs/project/` по [`project/README.md`](../project/README.md): sitemap, SEO-стратегия, спецификации страниц (если есть от заказчика).
**AC:**
- [ ] Happy: `npm run check:links` зелёный
- [ ] Negative: нет пустых шаблонов без статуса `planned`
- [ ] Security: нет персональных данных сверх нужного (контакты заказчика — не в репо)

## Gate

- [ ] `npm run gate:0`
- [ ] `/doc-align` · `/phase-review`
