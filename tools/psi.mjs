/**
 * Ручной прогон PageSpeed Insights для ЗАДЕПЛОЕННОГО сайта (не localhost) — уровень P3.
 *
 * Запуск (из корня репо):  npm run psi -- [--paths=/,/contacts/] [--strategy=both] [--runs=3] [--mode=report]
 *                                         [--base=https://…] [--target=production|staging]
 * Только по явному запросу, не в CI, без параллельных прогонов на один ключ. Процедура, пороги и
 * журнал — docs/playbooks/psi.md (M7), анализ отчёта — /psi-analyze.
 *
 * Базовый URL: --base, иначе project.config.json → urls.production (или urls.staging при
 * --target=staging). Пути: --paths, иначе pages-map.json → записи с psi: true.
 * Пороги и режим: tools/psi.config.json (floor = null, пока нет baseline).
 * Ключ: PAGESPEED_API_KEY из окружения или из .env в корне репо (скрипт сам читает его через
 * process.loadEnvFile, если файл есть; .env — UTF-8 без BOM). Ключ уходит только заголовком
 * X-Goog-Api-Key; URL запроса и заголовки в логи не пишутся, любой вывод ошибок — через redact().
 * Каждый прогон фиксирует GA4 on/off (прогрев ищет window.{PREFIX}_GA4 / gtag.js в HTML): lab-оценки
 * с аналитикой и без неё не сравнимы (K7). PSI не видит localhost и приватные сети (K6) — код 2.
 * Отчёты: psi-reports/<UTC-время>/summary.md|json + runs/*.json (gitignored).
 *
 * Коды выхода: 0 — ок (в режиме report всегда 0), 1 — нарушен floor в режиме enforce,
 * 2 — нельзя мерить: localhost/приватный хост, нет ключа, ошибка конфига, PSI недоступен,
 * ни одного валидного прогона.
 */
import { existsSync } from 'node:fs';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { ROOT, isMain } from './lib.mjs';

const API_URL = 'https://pagespeedonline.googleapis.com/pagespeedonline/v5/runPagespeed';
const CATEGORIES = [
  { id: 'performance', api: 'PERFORMANCE', short: 'P' },
  { id: 'accessibility', api: 'ACCESSIBILITY', short: 'A' },
  { id: 'best-practices', api: 'BEST_PRACTICES', short: 'BP' },
  { id: 'seo', api: 'SEO', short: 'SEO' },
];
const BACKOFF_MS = [5000, 15000, 45000];
const FLAKY_SPREAD = 10;
const MAX_FAILING_AUDITS = 15;
const NON_SCORED_MODES = new Set(['informative', 'notApplicable', 'manual', 'error']);
const METRIC_KEYS = ['lcpMs', 'fcpMs', 'tbtMs', 'cls', 'siMs', 'ttiMs', 'ttfbMs', 'totalBytes', 'requests'];

const PSI_CONFIG = path.join(ROOT, 'tools', 'psi.config.json');

export const HELP = `Использование: npm run psi -- [опции]

  --paths=/,/contacts/     пути через запятую (по умолчанию pages-map.json → psi: true)
  --strategy=both          mobile | desktop | both
  --runs=3                 прогонов на пару URL x стратегия (медиана; по умолчанию из tools/psi.config.json)
  --mode=report            report (всегда код 0) | enforce (код 1 при нарушении floor)
  --base=https://…         базовый URL (по умолчанию project.config.json → urls.production)
  --target=staging         взять urls.staging вместо urls.production
  --help                   эта справка

Только задеплоенный публичный URL: localhost и приватные сети → код 2.
Ключ: PAGESPEED_API_KEY в .env (корень репо, UTF-8 без BOM) или в окружении.
Git Bash превращает аргументы с «/» в пути Windows: MSYS_NO_PATHCONV=1 npm run psi -- --paths=/…
Отчёты: psi-reports/<время>/ (в .gitignore).`;

export class FatalError extends Error {}

let secret = '';

/** Register the API key so that every later message is redacted. */
export function setSecret(value) {
  secret = value;
}

export function redact(text) {
  const s = String(text);
  return secret ? s.split(secret).join('***') : s;
}

function describeError(err) {
  const code = err?.cause?.code ? ` (${err.cause.code})` : '';
  return redact(`${err?.name ?? 'Error'}: ${err?.message ?? err}${code}`);
}

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

export function parseArgs(argv) {
  const out = {};
  for (const arg of argv) {
    if (arg === '--help' || arg === '-h') {
      out.help = true;
      continue;
    }
    const m = /^--([a-z-]+)=(.*)$/.exec(arg);
    if (!m) {
      throw new FatalError(`Неизвестный аргумент: ${arg}\n\n${HELP}`);
    }
    out[m[1]] = m[2];
  }
  return out;
}

function normalizePath(p) {
  if (/^[A-Za-z]:[\\/]/.test(p)) {
    throw new FatalError(
      `Путь «${p}» похож на путь Windows: Git Bash преобразует аргументы, начинающиеся с «/». ` +
        'Запустите из PowerShell или добавьте MSYS_NO_PATHCONV=1.'
    );
  }
  return p.startsWith('/') ? p : `/${p}`;
}

export function assertPublicHost(baseUrl) {
  let u;
  try {
    u = new URL(baseUrl);
  } catch {
    throw new FatalError(`baseUrl не является URL: ${baseUrl}`);
  }
  if (!/^https?:$/.test(u.protocol)) {
    throw new FatalError(`baseUrl должен быть http(s): ${baseUrl}`);
  }
  // Normalise: IPv6 brackets, trailing root dot ("localhost."), case (URL already lowercases ASCII).
  const h = u.hostname.replace(/^\[|\]$/g, '').replace(/\.+$/, '').toLowerCase();
  const isLocal =
    h === 'localhost' ||
    /\.(local|localhost|test|internal|lan|home\.arpa)$/i.test(h) ||
    /^(0\.|127\.|10\.|192\.168\.|169\.254\.|172\.(1[6-9]|2\d|3[01])\.|100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.)/.test(h) ||
    /^(::1?|f[cd][0-9a-f]{2}:.*|fe80:.*)$/i.test(h) ||
    !h.includes('.');
  if (isLocal) {
    throw new FatalError(`PSI не видит localhost/локальные хосты (${h}). Мерить нужно задеплоенный публичный сайт.`);
  }
}

/**
 * Resolve the run config: tools/psi.config.json (thresholds, runs, mode, pauses) + project.config.json
 * (base URL) + pages-map.json (paths with psi: true) + CLI args. Throws FatalError (exit 2).
 * `sources` lets tests inject { psi, project, pages } instead of reading files.
 */
export async function loadConfig(args, sources = {}) {
  const readRoot = async (rel) => JSON.parse(await readFile(path.join(ROOT, rel), 'utf8'));
  let cfg;
  let project;
  let pages;
  try {
    cfg = structuredClone(sources.psi ?? JSON.parse(await readFile(PSI_CONFIG, 'utf8')));
    project = sources.project ?? (await readRoot('project.config.json'));
    pages = sources.pages ?? (await readRoot(project.paths?.pages_map ?? 'pages-map.json')).pages ?? [];
  } catch (err) {
    throw new FatalError(`Не удалось прочитать конфиг: ${err.message}`);
  }

  const target = args.target ?? 'production';
  if (!['production', 'staging'].includes(target)) {
    throw new FatalError(`target должен быть production или staging, получено: ${target}`);
  }
  cfg.baseUrl = args.base ?? project.urls?.[target] ?? null;
  if (!cfg.baseUrl) {
    throw new FatalError(
      `Нет базового URL: project.config.json → urls.${target} = null. Задайте URL задеплоенного сайта в конфиге или передайте --base=https://…`
    );
  }
  cfg.prefix = project.slug?.prefix ?? '';
  cfg.locale = String(project.locale ?? 'en_US').split('_')[0];
  cfg.paths = args.paths
    ? args.paths.split(',').map((s) => s.trim()).filter(Boolean).map(normalizePath)
    : pages.filter((p) => p.psi).map((p) => p.url);
  if (args.runs) cfg.runs = Number(args.runs);
  if (args.mode) cfg.mode = args.mode;
  cfg.strategy = args.strategy ?? 'both';

  // Localhost first: the most common mistake, and it must fail before anything else is checked.
  assertPublicHost(cfg.baseUrl);
  if (!Array.isArray(cfg.paths) || cfg.paths.length === 0) {
    throw new FatalError('Список путей пуст: отметьте страницы psi: true в pages-map.json или передайте --paths=/,/…');
  }
  cfg.paths = cfg.paths.map(normalizePath);
  if (!Number.isInteger(cfg.runs) || cfg.runs < 1 || cfg.runs > 10) {
    throw new FatalError(`runs должен быть целым от 1 до 10, получено: ${cfg.runs}`);
  }
  if (!['report', 'enforce'].includes(cfg.mode)) {
    throw new FatalError(`mode должен быть report или enforce, получено: ${cfg.mode}`);
  }
  if (!['mobile', 'desktop', 'both'].includes(cfg.strategy)) {
    throw new FatalError(`strategy должен быть mobile, desktop или both, получено: ${cfg.strategy}`);
  }
  cfg.strategies = cfg.strategy === 'both' ? ['mobile', 'desktop'] : [cfg.strategy];
  return cfg;
}

/** Network and timers; tests replace them with setIo() (no real PSI calls). */
const io = { fetch: (...a) => globalThis.fetch(...a), sleep };

export function setIo(overrides) {
  Object.assign(io, overrides);
}

/** GA4 marker in HTML: the theme's deferred-loader config window.{PREFIX}_GA4 or a gtag.js tag. */
export function detectGa4(html, prefix) {
  const marker = prefix ? `window\\.${prefix.toUpperCase()}_GA4|` : '';
  return new RegExp(`${marker}googletagmanager\\.com/gtag/js`, 'i').test(html);
}

/** Прогрев: GET без следования редиректам; хост изредка сбрасывает соединение — до 3 попыток. */
async function warmup(url, prefix) {
  let result = { ok: false, error: 'нет попыток' };
  for (let attempt = 0; attempt < 3; attempt++) {
    if (attempt > 0) await io.sleep(2000);
    result = await warmupOnce(url, prefix);
    if (result.ok) break;
  }
  return result;
}

/** Один GET: заголовки кэша, TTFB, наличие gtag в HTML. */
async function warmupOnce(url, prefix) {
  const started = performance.now();
  try {
    const res = await io.fetch(url, {
      redirect: 'manual',
      headers: { accept: 'text/html', 'user-agent': `${prefix || 'site'}-psi-manual/1.0` },
      signal: AbortSignal.timeout(30000),
    });
    const ttfbMs = Math.round(performance.now() - started);
    let html = '';
    if (res.status === 200) html = await res.text();
    else await res.arrayBuffer();
    const h = (name) => res.headers.get(name) || '';
    return {
      ok: true,
      status: res.status,
      ttfbMs,
      location: h('location'),
      server: h('server'),
      cacheControl: h('cache-control'),
      age: h('age'),
      xCache: h('x-cache'),
      cfCacheStatus: h('cf-cache-status'),
      litespeed: h('x-litespeed-cache'),
      contentEncoding: h('content-encoding'),
      ga4: detectGa4(html, prefix),
    };
  } catch (err) {
    return { ok: false, error: describeError(err) };
  }
}

async function callPsi(target, strategy, apiKey, timeoutMs, locale) {
  const params = new URLSearchParams({ url: target, strategy: strategy.toUpperCase(), locale });
  for (const c of CATEGORIES) params.append('category', c.api);
  const res = await io.fetch(`${API_URL}?${params}`, {
    headers: { accept: 'application/json', 'x-goog-api-key': apiKey },
    signal: AbortSignal.timeout(timeoutMs),
  });
  let body = null;
  try {
    body = await res.json();
  } catch {
    // Не-JSON ответ (например, HTML-страница ошибки) — обработаем по статусу.
  }
  return { status: res.status, body };
}

/** Узел DOM в details: сам элемент type:'node' (Lighthouse 13 insights) или поле node (старый формат). */
function findNode(items) {
  for (const it of items ?? []) {
    if (it?.type === 'node') return it;
    if (it?.node) return it.node;
    const nested = findNode(it?.items);
    if (nested) return nested;
  }
  return null;
}

const shortName = (url) => String(url ?? '').split('?')[0].split('/').pop();

/** Фазы LCP (TTFB, load delay, load time, render delay) из lcp-breakdown-insight. */
function lcpBreakdown(audits) {
  const table = (audits['lcp-breakdown-insight']?.details?.items ?? []).find((it) => it?.type === 'table');
  return (table?.items ?? [])
    .filter((it) => typeof it?.duration === 'number')
    .map((it) => ({ subpart: it.subpart ?? it.label ?? '', durationMs: Math.round(it.duration) }));
}

/** Причины layout shift: узел и причины (веб-шрифт, изображение без размеров и т.п.). */
function layoutShiftCulprits(audits) {
  return (audits['layout-shifts']?.details?.items ?? [])
    .slice(0, 3)
    .map((it) => ({
      selector: it?.node?.selector ?? '',
      causes: (it?.subItems?.items ?? []).map((s) => `${s.cause ?? ''}${s.extra?.value ? `: ${shortName(s.extra.value)}` : ''}`),
    }))
    .filter((x) => x.selector || x.causes.length);
}

/** Render-blocking ресурсы с оценкой потерь (render-blocking-insight). */
function renderBlocking(audits) {
  return (audits['render-blocking-insight']?.details?.items ?? [])
    .map((it) => ({ resource: shortName(it.url), wastedMs: it.wastedMs ?? null, totalBytes: it.totalBytes ?? null }))
    .slice(0, 5);
}

function compactCrux(exp) {
  if (!exp?.metrics) return null;
  const metrics = {};
  for (const [key, m] of Object.entries(exp.metrics)) {
    metrics[key] = { p75: m.percentile, category: m.category };
  }
  return { overall: exp.overall_category ?? null, originFallback: Boolean(exp.origin_fallback), metrics };
}

function failingAudits(lh) {
  const seen = new Set();
  const out = [];
  for (const c of CATEGORIES) {
    for (const ref of lh.categories[c.id].auditRefs ?? []) {
      const audit = lh.audits[ref.id];
      if (!audit || seen.has(ref.id) || NON_SCORED_MODES.has(audit.scoreDisplayMode)) continue;
      if (typeof audit.score !== 'number' || audit.score >= 0.9) continue;
      seen.add(ref.id);
      out.push({
        category: c.id,
        id: ref.id,
        title: audit.title,
        score: audit.score,
        displayValue: audit.displayValue ?? '',
      });
    }
  }
  return out.sort((a, b) => a.score - b.score).slice(0, MAX_FAILING_AUDITS);
}

function extractRun(body, target) {
  const lh = body.lighthouseResult;
  const a = lh.audits ?? {};
  const num = (id) => (typeof a[id]?.numericValue === 'number' ? a[id].numericValue : null);

  const scores = {};
  for (const c of CATEGORIES) scores[c.id] = Math.round(lh.categories[c.id].score * 100);

  // Lighthouse ≤12: largest-contentful-paint-element; Lighthouse 13+: узел внутри lcp-breakdown-insight.
  const node =
    findNode(a['largest-contentful-paint-element']?.details?.items) ?? findNode(a['lcp-breakdown-insight']?.details?.items);
  const thirdPartyItems = (a['third-parties-insight'] ?? a['third-party-summary'])?.details?.items ?? [];
  const finalUrl = lh.finalDisplayedUrl ?? lh.finalUrl ?? '';

  return {
    scores,
    metrics: {
      lcpMs: num('largest-contentful-paint'),
      fcpMs: num('first-contentful-paint'),
      tbtMs: num('total-blocking-time'),
      cls: num('cumulative-layout-shift'),
      siMs: num('speed-index'),
      ttiMs: num('interactive'),
      ttfbMs: num('server-response-time'),
      totalBytes: num('total-byte-weight'),
      requests: a['network-requests']?.details?.items?.length ?? null,
    },
    lcpElement: node ? { selector: node.selector ?? '', snippet: String(node.snippet ?? '').slice(0, 200) } : null,
    lcpBreakdown: lcpBreakdown(a),
    layoutShiftCulprits: layoutShiftCulprits(a),
    renderBlocking: renderBlocking(a),
    failingAudits: failingAudits(lh),
    thirdParty: thirdPartyItems
      .map((it) => ({
        entity: typeof it.entity === 'string' ? it.entity : (it.entity?.text ?? ''),
        transferSize: it.transferSize ?? null,
        blockingTimeMs: it.mainThreadTime ?? it.blockingTime ?? null,
      }))
      .sort((x, y) => (y.transferSize ?? 0) - (x.transferSize ?? 0))
      .slice(0, 5),
    crux: { url: compactCrux(body.loadingExperience), origin: compactCrux(body.originLoadingExperience) },
    runWarnings: lh.runWarnings ?? [],
    lighthouseVersion: lh.lighthouseVersion ?? '',
    fetchTime: lh.fetchTime ?? '',
    finalUrl,
    redirected: finalUrl !== '' && new URL(finalUrl).href !== new URL(target).href,
  };
}

/** Один валидный прогон PSI с ретраями; Fatal — если дальше пытаться бессмысленно (ключ, квота). */
async function measureOnce(target, strategy, cfg, apiKey) {
  let lastError = 'неизвестная ошибка';
  for (let attempt = 0; attempt <= BACKOFF_MS.length; attempt++) {
    if (attempt > 0) await io.sleep(BACKOFF_MS[attempt - 1]);

    let resp;
    try {
      resp = await callPsi(target, strategy, apiKey, cfg.timeoutMs, cfg.locale);
    } catch (err) {
      lastError = describeError(err);
      continue;
    }

    const { status, body } = resp;
    if (status === 200 && body?.lighthouseResult) {
      const lh = body.lighthouseResult;
      if (lh.runtimeError) {
        lastError = `Lighthouse ${lh.runtimeError.code}: ${redact(lh.runtimeError.message)}`;
        continue;
      }
      if (!CATEGORIES.every((c) => typeof lh.categories?.[c.id]?.score === 'number')) {
        lastError = 'В ответе нет оценок всех четырёх категорий';
        continue;
      }
      return { ok: true, run: extractRun(body, target) };
    }

    const message = redact(body?.error?.message || `HTTP ${status}`);
    if (
      status === 401 ||
      status === 403 ||
      (status === 400 && /api key|api_key|key not valid/i.test(message)) ||
      (status === 429 && /per day/i.test(message))
    ) {
      throw new FatalError(`PSI отклонил запрос (HTTP ${status}): ${message}`);
    }
    lastError = `HTTP ${status}: ${message}`;
    if (status === 400 || status === 404) break;
  }
  return { ok: false, error: lastError };
}

export function median(values) {
  const v = values.filter((n) => typeof n === 'number' && !Number.isNaN(n)).sort((x, y) => x - y);
  if (v.length === 0) return null;
  const mid = Math.floor(v.length / 2);
  return v.length % 2 ? v[mid] : (v[mid - 1] + v[mid]) / 2;
}

export function aggregate(runs) {
  const scores = {};
  for (const c of CATEGORIES) {
    const v = runs.map((r) => r.scores[c.id]);
    scores[c.id] = { median: Math.round(median(v)), min: Math.min(...v), max: Math.max(...v) };
  }
  const metrics = {};
  for (const key of METRIC_KEYS) metrics[key] = median(runs.map((r) => r.metrics[key]));

  // Репрезентативный прогон — ближайший по Performance к медиане: из него берём LCP-узел, аудиты, CrUX.
  const perfMedian = scores.performance.median;
  const representative = runs.reduce(
    (best, r) => (Math.abs(r.scores.performance - perfMedian) < Math.abs(best.scores.performance - perfMedian) ? r : best),
    runs[0]
  );
  const perfSpread = scores.performance.max - scores.performance.min;
  return { scores, metrics, perfSpread, flaky: perfSpread > FLAKY_SPREAD, representative };
}

function fmtMs(ms) {
  if (ms === null || ms === undefined) return '—';
  return ms >= 1000 ? `${(ms / 1000).toFixed(1)} s` : `${Math.round(ms)} ms`;
}

function fmtCls(v) {
  return v === null || v === undefined ? '—' : String(Number(v.toFixed(3)));
}

export function evaluate(agg, strategy, thresholds) {
  const warnings = [];
  const failures = [];
  for (const c of CATEGORIES) {
    const score = agg.scores[c.id].median;
    const goal = thresholds?.goal?.[c.id];
    if (typeof goal === 'number' && score < goal) warnings.push(`${c.short} ${score} < цель ${goal}`);
    const floor = thresholds?.floor?.[strategy]?.[c.id];
    if (typeof floor === 'number' && score < floor) failures.push(`${c.short} ${score} < floor ${floor}`);
  }
  const b = thresholds?.budgets?.[strategy] ?? {};
  const m = agg.metrics;
  if (typeof b.lcpMs === 'number' && m.lcpMs > b.lcpMs) warnings.push(`LCP ${fmtMs(m.lcpMs)} > ${fmtMs(b.lcpMs)}`);
  if (typeof b.tbtMs === 'number' && m.tbtMs > b.tbtMs) warnings.push(`TBT ${fmtMs(m.tbtMs)} > ${fmtMs(b.tbtMs)}`);
  if (typeof b.cls === 'number' && m.cls > b.cls) warnings.push(`CLS ${fmtCls(m.cls)} > ${b.cls}`);
  return { warnings, failures };
}

const slugOf = (p) => (p === '/' ? 'home' : p.replace(/^\/|\/$/g, '').replace(/\//g, '_'));
const scoreCell = (s) => (s.min === s.max ? String(s.median) : `${s.median} (${s.min}–${s.max})`);
const mdEscape = (t) => String(t).replace(/\|/g, '\\|');

function renderMarkdown(s) {
  const L = [];
  L.push(`# PSI ${s.generatedAt.slice(0, 10)} — ${s.baseUrl}`, '');
  L.push(
    `Режим: **${s.mode}**, прогонов на пару: **${s.runsPerPair}**, Lighthouse: ${s.lighthouseVersions.join(', ') || '—'}`,
    ''
  );

  L.push('## Оценки и метрики (медиана)', '');
  L.push('| URL | Стратегия | P | A | BP | SEO | LCP | FCP | TBT | CLS | SI | TTFB | Заметки |');
  L.push('|---|---|---|---|---|---|---|---|---|---|---|---|---|');
  for (const p of s.pairs) {
    if (!p.ok) {
      L.push(`| \`${p.path}\` | ${p.strategy} | ошибка измерения: ${mdEscape(p.error)} |||||||||||`);
      continue;
    }
    const m = p.metrics;
    const notes = [
      ...(p.flaky ? [`flaky (Perf ${p.scores.performance.min}–${p.scores.performance.max})`] : []),
      ...(p.redirected ? [`редирект → ${p.finalUrl}`] : []),
      ...p.failures.map((x) => `**FAIL** ${x}`),
      ...p.warnings,
    ];
    L.push(
      `| \`${p.path}\` | ${p.strategy} | ${scoreCell(p.scores.performance)} | ${scoreCell(p.scores.accessibility)} | ` +
        `${scoreCell(p.scores['best-practices'])} | ${scoreCell(p.scores.seo)} | ${fmtMs(m.lcpMs)} | ${fmtMs(m.fcpMs)} | ` +
        `${fmtMs(m.tbtMs)} | ${fmtCls(m.cls)} | ${fmtMs(m.siMs)} | ${fmtMs(m.ttfbMs)} | ${mdEscape(notes.join('; '))} |`
    );
  }

  L.push('', '## Прогрев (GET)', '');
  L.push('| URL | HTTP | TTFB | Cache-Control | Age | X-Cache / CF / LiteSpeed | Server | GA4 |');
  L.push('|---|---|---|---|---|---|---|---|');
  for (const [p, w] of Object.entries(s.warmups)) {
    if (!w.ok) {
      L.push(`| \`${p}\` | ошибка: ${mdEscape(w.error)} |||||||`);
      continue;
    }
    const cache = [w.xCache, w.cfCacheStatus, w.litespeed].filter(Boolean).join(' / ') || 'нет';
    L.push(
      `| \`${p}\` | ${w.status}${w.location ? ` → ${w.location}` : ''} | ${w.ttfbMs} ms | ${mdEscape(w.cacheControl || 'нет')} | ` +
        `${w.age || 'нет'} | ${mdEscape(cache)} | ${mdEscape(w.server || '—')} | ${w.ga4 ? 'on' : 'off'} |`
    );
  }

  L.push('', '## Детали по парам', '');
  for (const p of s.pairs.filter((x) => x.ok)) {
    L.push(`### \`${p.path}\` — ${p.strategy}`, '');
    L.push(
      p.lcpElement
        ? `- LCP-узел: \`${mdEscape(p.lcpElement.selector)}\` — ${mdEscape(p.lcpElement.snippet)}`
        : '- LCP-узел: не определён'
    );
    if (p.lcpBreakdown.length) {
      L.push(`- LCP по фазам: ${p.lcpBreakdown.map((x) => `${mdEscape(x.subpart)} ${x.durationMs} ms`).join(', ')}`);
    }
    if (p.renderBlocking.length) {
      L.push(
        `- Render-blocking: ${p.renderBlocking
          .map((x) => `${mdEscape(x.resource)} (${x.wastedMs ?? '—'} ms)`)
          .join(', ')}`
      );
    }
    for (const c of p.layoutShiftCulprits) {
      L.push(`- Layout shift: \`${mdEscape(c.selector || '—')}\`${c.causes.length ? ` ← ${mdEscape(c.causes.join('; '))}` : ''}`);
    }
    if (p.thirdParty.length) {
      L.push(
        `- Third-party: ${p.thirdParty
          .slice(0, 3)
          .map((t) => `${t.entity} (${t.transferSize !== null ? `${Math.round(t.transferSize / 1024)} KiB` : '—'})`)
          .join(', ')}`
      );
    }
    const crux = p.crux.url ?? p.crux.origin;
    L.push(
      crux
        ? `- CrUX (${p.crux.url ? 'URL' : 'origin'}): ${crux.overall ?? '—'}`
        : '- CrUX: нет данных (мало трафика)'
    );
    if (p.runWarnings.length) L.push(`- runWarnings: ${mdEscape(p.runWarnings.join('; '))}`);
    if (p.failingAudits.length) {
      L.push('- Аудиты ниже 0.9:');
      for (const f of p.failingAudits.slice(0, 10)) {
        L.push(`  - [${f.category}] ${mdEscape(f.title)}${f.displayValue ? ` — ${mdEscape(f.displayValue)}` : ''} (\`${f.id}\`)`);
      }
    }
    L.push('');
  }

  L.push('## Строки для журнала прогонов', '');
  L.push('| Дата | URL | Warmup | Mobile P/A/BP/SEO | Desktop P/A/BP/SEO | GA4 | Lab notes |');
  L.push('|---|---|---|---|---|---|---|');
  for (const [p, w] of Object.entries(s.warmups)) {
    const cell = (strategy) => {
      const pair = s.pairs.find((x) => x.path === p && x.strategy === strategy && x.ok);
      if (!pair) return '—';
      const sc = pair.scores;
      return `${sc.performance.median} / ${sc.accessibility.median} / ${sc['best-practices'].median} / ${sc.seo.median}`;
    };
    const warm = w.ok
      ? `GET ${w.status}; ${w.server || 'server?'}; Cache-Control: ${w.cacheControl || 'нет'}; TTFB ${w.ttfbMs} ms`
      : 'ошибка прогрева';
    L.push(
      `| ${s.generatedAt.slice(0, 10)} | \`${s.baseUrl}${p}\` | ${mdEscape(warm)} | ${cell('mobile')} | ${cell('desktop')} | ` +
        `${w.ok ? (w.ga4 ? 'on' : 'off') : '?'} | median of ${s.runsPerPair} |`
    );
  }
  L.push('');
  return L.join('\n');
}

async function writeReports(summary, runsToStore, reportRoot) {
  const stamp = summary.generatedAt.replace(/[:.]/g, '-').slice(0, 19);
  const dir = path.join(reportRoot, stamp);
  await mkdir(path.join(dir, 'runs'), { recursive: true });
  await writeFile(path.join(dir, 'summary.json'), `${JSON.stringify(summary, null, 2)}\n`);
  await writeFile(path.join(dir, 'summary.md'), renderMarkdown(summary));
  for (const r of runsToStore) {
    await writeFile(path.join(dir, 'runs', r.file), `${JSON.stringify(r.data, null, 2)}\n`);
  }
  return dir;
}

/**
 * Full run. Returns the exit code; throws FatalError for exit 2.
 * `options` (tests): env, sources (see loadConfig), reportRoot.
 */
export async function main(argv = process.argv.slice(2), options = {}) {
  const args = parseArgs(argv);
  if (args.help) {
    console.log(HELP);
    return 0;
  }

  const cfg = await loadConfig(args, options.sources);
  const env = options.env ?? process.env;
  const apiKey = (env.PAGESPEED_API_KEY || '').trim();
  if (!apiKey) {
    throw new FatalError(
      'PAGESPEED_API_KEY не задан. Создайте .env в корне репо по образцу .env.example (UTF-8 без BOM) ' +
        'или задайте переменную окружения. Запросов не выполнялось.'
    );
  }
  setSecret(apiKey);

  const total = cfg.paths.length * cfg.strategies.length * cfg.runs;
  console.log(
    `PSI: ${cfg.baseUrl} | URL: ${cfg.paths.length} | стратегии: ${cfg.strategies.join('+')} | прогонов: ${cfg.runs} | ` +
      `вызовов: ${total} | режим: ${cfg.mode}`
  );

  const warmups = {};
  const pairs = [];
  const runsToStore = [];
  let fatal = null;
  let calls = 0;

  try {
    for (const p of cfg.paths) {
      const target = new URL(p, cfg.baseUrl).href;
      const w = await warmup(target, cfg.prefix);
      warmups[p] = w;
      if (!w.ok) console.warn(`  прогрев ${p}: ${w.error}`);
      else if (w.status >= 300) console.warn(`  прогрев ${p}: HTTP ${w.status}${w.location ? ` → ${w.location}` : ''} — мерить нужно финальный URL`);

      for (const strategy of cfg.strategies) {
        const runs = [];
        let lastError = '';
        for (let i = 1; i <= cfg.runs; i++) {
          if (calls > 0) await io.sleep(cfg.pauseMs);
          calls++;
          process.stdout.write(`[${calls}/${total}] ${strategy} ${p} прогон ${i}/${cfg.runs} … `);
          const res = await measureOnce(target, strategy, cfg, apiKey);
          if (res.ok) {
            runs.push(res.run);
            runsToStore.push({ file: `${slugOf(p)}-${strategy}-${i}.json`, data: { requestedUrl: target, strategy, index: i, ...res.run } });
            console.log(`P${res.run.scores.performance} A${res.run.scores.accessibility} BP${res.run.scores['best-practices']} SEO${res.run.scores.seo}`);
          } else {
            lastError = res.error;
            console.log(`ошибка: ${res.error}`);
          }
        }

        if (runs.length === 0) {
          pairs.push({ path: p, url: target, strategy, ok: false, error: lastError || 'нет валидных прогонов' });
          continue;
        }
        const agg = aggregate(runs);
        const verdict = evaluate(agg, strategy, cfg.thresholds);
        const rep = agg.representative;
        pairs.push({
          path: p,
          url: target,
          strategy,
          ok: true,
          validRuns: runs.length,
          scores: agg.scores,
          metrics: agg.metrics,
          perfSpread: agg.perfSpread,
          flaky: agg.flaky,
          redirected: rep.redirected,
          finalUrl: rep.finalUrl,
          lcpElement: rep.lcpElement,
          lcpBreakdown: rep.lcpBreakdown,
          layoutShiftCulprits: rep.layoutShiftCulprits,
          renderBlocking: rep.renderBlocking,
          failingAudits: rep.failingAudits,
          thirdParty: rep.thirdParty,
          crux: rep.crux,
          runWarnings: rep.runWarnings,
          lighthouseVersion: rep.lighthouseVersion,
          warnings: verdict.warnings,
          failures: verdict.failures,
        });
      }
    }
  } catch (err) {
    if (err instanceof FatalError) fatal = err;
    else throw err;
  }

  if (pairs.length === 0) {
    if (fatal) throw fatal;
    return 2;
  }

  const summary = {
    generatedAt: new Date().toISOString(),
    baseUrl: cfg.baseUrl.replace(/\/$/, ''),
    mode: cfg.mode,
    runsPerPair: cfg.runs,
    strategies: cfg.strategies,
    lighthouseVersions: [...new Set(pairs.filter((x) => x.ok).map((x) => x.lighthouseVersion).filter(Boolean))],
    warmups,
    pairs,
    incomplete: Boolean(fatal),
  };
  const dir = await writeReports(summary, runsToStore, options.reportRoot ?? path.join(ROOT, 'psi-reports'));

  console.log('');
  for (const p of pairs) {
    if (!p.ok) {
      console.log(`${p.strategy.padEnd(7)} ${p.path}  ОШИБКА: ${p.error}`);
      continue;
    }
    const sc = p.scores;
    console.log(
      `${p.strategy.padEnd(7)} ${p.path}  P${sc.performance.median} A${sc.accessibility.median} BP${sc['best-practices'].median} ` +
        `SEO${sc.seo.median}  LCP ${fmtMs(p.metrics.lcpMs)}  TBT ${fmtMs(p.metrics.tbtMs)}  CLS ${fmtCls(p.metrics.cls)}  ` +
        `TTFB ${fmtMs(p.metrics.ttfbMs)}${p.flaky ? '  [flaky]' : ''}${p.redirected ? '  [редирект]' : ''}`
    );
    for (const f of p.failures) console.log(`    FAIL: ${f}`);
    for (const w of p.warnings) console.log(`    warn: ${w}`);
  }
  console.log(`\nОтчёт: ${path.relative(process.cwd(), dir) || dir}${path.sep}summary.md`);

  const errors = pairs.filter((x) => !x.ok).length + (fatal ? 1 : 0);
  const failures = pairs.reduce((n, x) => n + (x.failures?.length ?? 0), 0);
  if (fatal) console.error(`\nПрогон прерван: ${fatal.message}`);
  if (errors > 0) return 2;
  if (failures > 0 && cfg.mode === 'enforce') return 1;
  if (failures > 0) console.log('Режим report: нарушения floor не влияют на код выхода.');
  return 0;
}

if (isMain(import.meta.url)) {
  // .env from the repo root when present (no notice when absent); real environment variables win.
  const envFile = path.join(ROOT, '.env');
  if (existsSync(envFile) && !process.env.PAGESPEED_API_KEY) process.loadEnvFile(envFile);
  main()
    .then((code) => {
      process.exitCode = code;
    })
    .catch((err) => {
      console.error(redact(err instanceof FatalError ? err.message : (err?.stack ?? err)));
      process.exitCode = 2;
    });
}
