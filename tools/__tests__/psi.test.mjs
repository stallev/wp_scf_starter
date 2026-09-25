/**
 * tools/psi.mjs without the real API: argument parsing, host refusal (exit 2 contract) and one full
 * run against a mocked fetch (key only in the header, GA4 detection, reports, enforce → 1).
 */
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, readdirSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { afterEach, test } from 'node:test';
import { FatalError, assertPublicHost, detectGa4, loadConfig, main, median, parseArgs, redact, setIo, setSecret } from '../psi.mjs';

const project = { slug: { prefix: 'demo' }, locale: 'ru_RU', urls: { local: 'http://localhost:8888', staging: 'https://stage.example.org', production: null } };
const pages = [{ url: '/', psi: true }, { url: '/contacts/', psi: true }, { url: '/about/', psi: false }];
const psi = {
  runs: 3,
  mode: 'report',
  pauseMs: 0,
  timeoutMs: 1000,
  thresholds: { goal: { performance: 90 }, floor: { mobile: { performance: 95 }, desktop: {} }, budgets: {} },
};
const sources = { psi, project, pages };

afterEach(() => setSecret(''));

test('parseArgs: --key=value, --help, unknown argument', () => {
  assert.deepEqual(parseArgs(['--paths=/,/a/', '--runs=1', '--help']), { paths: '/,/a/', runs: '1', help: true });
  assert.throws(() => parseArgs(['/contacts/']), FatalError);
});

test('assertPublicHost refuses localhost and private networks', () => {
  for (const url of ['http://localhost:8888', 'http://127.0.0.1', 'http://10.0.0.5', 'http://192.168.1.10', 'http://172.20.0.2', 'http://site.local', 'http://site.test', 'http://intranet', 'http://[::1]:8080']) {
    assert.throws(() => assertPublicHost(url), FatalError, url);
  }
  assert.throws(() => assertPublicHost('ftp://example.org'), FatalError);
  assert.doesNotThrow(() => assertPublicHost('https://example.org'));
});

test('loadConfig: base URL from config / --target / --base, paths from pages-map psi:true', async () => {
  await assert.rejects(loadConfig({}, sources), /urls\.production = null/);
  const staging = await loadConfig({ target: 'staging' }, sources);
  assert.equal(staging.baseUrl, 'https://stage.example.org');
  assert.deepEqual(staging.paths, ['/', '/contacts/']);
  assert.deepEqual(staging.strategies, ['mobile', 'desktop']);
  assert.equal(staging.locale, 'ru');
  await assert.rejects(loadConfig({ base: 'http://localhost:8888' }, sources), /localhost/);
  await assert.rejects(loadConfig({ base: 'https://example.org', paths: 'C:/Program Files/Git/contacts/' }, sources), /MSYS_NO_PATHCONV=1/);
  await assert.rejects(loadConfig({ base: 'https://example.org', runs: '0' }, sources), /runs/);
});

test('main: localhost and missing key are exit-2 errors before any request', async () => {
  let calls = 0;
  setIo({ fetch: async () => (calls++, new Response('')) });
  await assert.rejects(main(['--base=http://localhost:8888'], { sources, env: { PAGESPEED_API_KEY: 'k' } }), FatalError);
  await assert.rejects(main(['--base=https://example.org'], { sources, env: {} }), /PAGESPEED_API_KEY не задан/);
  assert.equal(calls, 0);
});

test('redact hides the key; median; GA4 detection uses the project prefix', () => {
  setSecret('SECRET123');
  assert.equal(redact('bad key SECRET123 here'), 'bad key *** here');
  assert.equal(median([3, 1, 2]), 2);
  assert.equal(median([4, 1, 2, 3]), 2.5);
  assert.equal(detectGa4('<script>window.DEMO_GA4 = {}</script>', 'demo'), true);
  assert.equal(detectGa4('<script src="https://www.googletagmanager.com/gtag/js?id=x"></script>', 'demo'), true);
  assert.equal(detectGa4('<p>no analytics</p>', 'demo'), false);
});

function lighthouse(perf) {
  const cat = (score) => ({ score, auditRefs: [] });
  return {
    lighthouseResult: {
      lighthouseVersion: '13.0.0',
      finalDisplayedUrl: 'https://example.org/',
      categories: { performance: cat(perf), accessibility: cat(1), 'best-practices': cat(1), seo: cat(1) },
      audits: { 'largest-contentful-paint': { numericValue: 1800 }, 'cumulative-layout-shift': { numericValue: 0.01 } },
    },
  };
}

test('main: full mocked run writes reports; key only in the header; enforce fails below floor', async () => {
  const dir = mkdtempSync(path.join(tmpdir(), 'psi-test-'));
  const requests = [];
  const perfs = [0.9, 0.8, 0.85];
  setIo({
    sleep: async () => {},
    fetch: async (url, init) => {
      requests.push({ url: String(url), headers: init.headers });
      if (String(url).startsWith('https://example.org')) {
        return new Response('<script>window.DEMO_GA4 = {}</script>', { status: 200, headers: { 'cache-control': 'max-age=60' } });
      }
      return Response.json(lighthouse(perfs[(requests.length - 2) % 3]));
    },
  });
  try {
    // Silence progress output without touching process.stdout (the test runner reports through it).
    const log = console.log;
    const write = process.stdout.write;
    console.log = () => {};
    let code;
    try {
      process.stdout.write = (chunk, ...rest) => (String(chunk).includes('прогон') ? true : write.call(process.stdout, chunk, ...rest));
      code = await main(['--base=https://example.org', '--paths=/', '--strategy=mobile', '--mode=enforce', '--runs=3'], {
        sources,
        env: { PAGESPEED_API_KEY: 'KEY-abc' },
        reportRoot: dir,
      });
    } finally {
      console.log = log;
      process.stdout.write = write;
    }
    assert.equal(code, 1, 'median performance 85 < floor 95 in enforce mode');
    const api = requests.filter((r) => r.url.includes('pagespeedonline'));
    assert.equal(api.length, 3);
    for (const r of api) {
      assert.equal(r.url.includes('KEY-abc'), false, 'key never in the URL');
      assert.equal(r.headers['x-goog-api-key'], 'KEY-abc');
      assert.match(r.url, /locale=ru/);
    }
    const [stamp] = readdirSync(dir);
    const summary = JSON.parse(readFileSync(path.join(dir, stamp, 'summary.json'), 'utf8'));
    assert.equal(summary.warmups['/'].ga4, true);
    assert.equal(summary.pairs[0].scores.performance.median, 85);
    assert.deepEqual(summary.pairs[0].failures, ['P 85 < floor 95']);
    assert.equal(readdirSync(path.join(dir, stamp, 'runs')).length, 3);
    const md = readFileSync(path.join(dir, stamp, 'summary.md'), 'utf8');
    assert.match(md, /## Строки для журнала прогонов/);
    assert.doesNotMatch(md + JSON.stringify(summary), /KEY-abc/);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});

test('assertPublicHost normalises trailing dots and case', () => {
  for (const url of ['http://localhost.:8888', 'http://LOCALHOST', 'http://127.0.0.1.', 'http://Site.Local.']) {
    assert.throws(() => assertPublicHost(url), FatalError, url);
  }
  assert.doesNotThrow(() => assertPublicHost('https://Example.org.'));
});

/** Run main() against scripted PSI responses; warmup GETs always succeed. */
async function runMocked(apiResponses, argv = []) {
  const dir = mkdtempSync(path.join(tmpdir(), 'psi-test-'));
  const sleeps = [];
  let api = 0;
  setIo({
    sleep: async (ms) => void sleeps.push(ms),
    fetch: async (url) => {
      if (String(url).startsWith('https://example.org')) return new Response('<p>page</p>', { status: 200 });
      const r = apiResponses[Math.min(api++, apiResponses.length - 1)];
      return Response.json(r.body, { status: r.status ?? 200 });
    },
  });
  const log = console.log;
  const write = process.stdout.write;
  console.log = () => {};
  process.stdout.write = (chunk, ...rest) => (String(chunk).includes('прогон') ? true : write.call(process.stdout, chunk, ...rest));
  try {
    const code = await main(['--base=https://example.org', '--paths=/', '--strategy=mobile', ...argv], { sources, env: { PAGESPEED_API_KEY: 'KEY-abc' }, reportRoot: dir });
    const [stamp] = readdirSync(dir);
    const summary = stamp ? JSON.parse(readFileSync(path.join(dir, stamp, 'summary.json'), 'utf8')) : null;
    return { code, summary, sleeps, api };
  } finally {
    console.log = log;
    process.stdout.write = write;
    rmSync(dir, { recursive: true, force: true });
  }
}

test('mocked PSI: 429 (rate limit) is retried with backoff, then succeeds', async () => {
  const res = await runMocked([{ status: 429, body: { error: { message: 'Rate limit exceeded' } } }, { body: lighthouse(0.95) }], ['--runs=1']);
  assert.equal(res.code, 0);
  assert.equal(res.api, 2);
  assert.ok(res.sleeps.includes(5000), 'first backoff step');
  assert.equal(res.summary.pairs[0].scores.performance.median, 95);
});

test('mocked PSI: Lighthouse runtimeError is retried', async () => {
  const broken = { lighthouseResult: { runtimeError: { code: 'NO_FCP', message: 'no paint' }, categories: {}, audits: {} } };
  const res = await runMocked([{ body: broken }, { body: lighthouse(0.91) }], ['--runs=1']);
  assert.equal(res.code, 0);
  assert.equal(res.api, 2);
  assert.equal(res.summary.pairs[0].ok, true);
});

test('mocked PSI: per-day quota is fatal (exit 2), no retries', async () => {
  await assert.rejects(
    runMocked([{ status: 429, body: { error: { message: 'Quota exceeded for quota metric Queries per day' } } }], ['--runs=1']),
    (err) => err instanceof FatalError && /per day/.test(err.message),
  );
});

test('mocked PSI: spread > 10 marks the pair flaky', async () => {
  const res = await runMocked([{ body: lighthouse(0.95) }, { body: lighthouse(0.8) }, { body: lighthouse(0.9) }], ['--runs=3']);
  assert.equal(res.code, 0, 'report mode');
  assert.equal(res.summary.pairs[0].perfSpread, 15);
  assert.equal(res.summary.pairs[0].flaky, true);
  const stable = await runMocked([{ body: lighthouse(0.9) }, { body: lighthouse(0.92) }, { body: lighthouse(0.95) }], ['--runs=3']);
  assert.equal(stable.summary.pairs[0].flaky, false);
});
