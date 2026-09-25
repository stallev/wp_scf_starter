/**
 * Gate helpers that are more than a chain of npm scripts.
 *
 *   node tools/gate.mjs page <url>   gate for one ported page (/port-page): the URL must be in
 *                                     pages-map.json; runs check:config, check:hardcode, lint:php,
 *                                     then the page's e2e suites (PAGE_SUITES) with E2E_PAGE_URL=<url>.
 *   node tools/gate.mjs e2e <phase>  e2e suites of a phase gate (gate:4 … gate:7, PHASE_SUITES); the
 *                                     static part of the gate runs before it in package.json.
 *                                     `e2e smoke` — Firefox + WebKit smoke (E2E_SMOKE=1).
 * <url>: "/contacts/", "contacts" or "/" ("home"). Git Bash rewrites arguments starting with "/" into
 * Windows paths — this is detected and undone; MSYS_NO_PATHCONV=1 avoids it.
 * Suites = Playwright projects of playwright.config.ts (tests/e2e/<suite>.spec.ts).
 * Exit: 0 ok, 1 a check failed, 2 bad arguments (unknown URL / phase).
 */
import path from 'node:path';
import { ROOT, isMain, loadConfig, readJson, run } from './lib.mjs';

/**
 * Phase → Playwright run: projects (wildcards allowed) and an optional --grep.
 * gate:4 = shell (navigation + perf-markup fonts/scripts), 5 = ported pages, 6 = dynamics/forms/SEO,
 * 7 = full regression incl. cross-browser smoke.
 */
export const PHASE_SUITES = {
  4: { projects: ['navigation-*', 'perf-markup'], grep: '@navigation|@fonts|@scripts' },
  5: { projects: ['static', 'perf-markup', 'console', 'a11y', 'visual'] },
  6: { projects: ['forms', 'dynamic', 'seo'] },
  7: { projects: ['static', 'navigation-*', 'forms', 'dynamic', 'seo', 'perf-markup', 'console', 'a11y', 'visual', 'smoke-*'], smoke: true },
  smoke: { projects: ['smoke-*'], smoke: true },
};
export const PAGE_SUITES = ['static', 'perf-markup', 'console', 'a11y', 'visual'];

/** Normalise a page argument to a pages-map URL ("/x/y/"). */
export function normalizePageArg(arg) {
  let a = String(arg ?? '').trim().replace(/\\/g, '/');
  const msys = /^[A-Za-z]:\/.*?\/Git(\/.*)?$/i.exec(a); // "/contacts/" → "C:/Program Files/Git/contacts/"
  if (msys) a = msys[1] ?? '/';
  if (a === '' || a === 'home' || a === '/') return '/';
  return `/${a.replace(/^\/+|\/+$/g, '')}/`;
}

/** Playwright CLI arguments for a set of projects. */
export function playwrightArgs({ projects, grep }) {
  return ['test', ...projects.map((p) => `--project=${p}`), ...(grep ? [`--grep=${grep}`] : [])];
}

const node = (...args) => run(process.execPath, args, { shell: false });

function playwright(suite, env = {}) {
  const cli = path.join(ROOT, 'node_modules', '@playwright', 'test', 'cli.js');
  const args = playwrightArgs(suite);
  console.log(`\n> playwright ${args.join(' ')}${Object.keys(env).length ? `  (${Object.entries(env).map(([k, v]) => `${k}=${v}`).join(' ')})` : ''}`);
  return run(process.execPath, [cli, ...args], { shell: false, env: { ...process.env, ...env, ...(suite.smoke ? { E2E_SMOKE: '1' } : {}) } });
}

function page(arg) {
  if (!arg) {
    console.error('usage: npm run gate:page -- <url>   (e.g. /contacts/ or contacts)');
    return 2;
  }
  const url = normalizePageArg(arg);
  const cfg = loadConfig();
  const entry = readJson(cfg.paths.pages_map).pages.find((p) => p.url === url);
  if (!entry) {
    console.error(`gate:page: ${url} is not in ${cfg.paths.pages_map} — add the page there first (phase 1, human-approved)`);
    return 2;
  }
  console.log(`gate:page ${url} (${entry.template}, lead_form: ${entry.lead_form}, lcp: ${entry.lcp.selector})`);
  const steps = [
    ['check:config', ['tools/validate-config.mjs']],
    ['check:hardcode', ['tools/check-hardcode.mjs']],
    ['lint:php', ['tools/composer.mjs', 'lint']],
  ];
  for (const [name, args] of steps) {
    console.log(`\n> ${name}`);
    if (node(...args) !== 0) {
      console.error(`\ngate:page ${url}: ${name} failed`);
      return 1;
    }
  }
  if (playwright({ projects: PAGE_SUITES }, { E2E_PAGE_URL: url }) !== 0) {
    console.error(`\ngate:page ${url}: e2e (${PAGE_SUITES.join(', ')}) failed — report: npx playwright show-report`);
    return 1;
  }
  console.log(`\ngate:page ${url}: green`);
  return 0;
}

function e2e(phase) {
  const suite = PHASE_SUITES[phase];
  if (!suite) {
    console.error(`gate e2e: unknown phase "${phase}" (one of ${Object.keys(PHASE_SUITES).join(', ')})`);
    return 2;
  }
  const code = playwright(suite);
  console.log(code === 0 ? `\ngate e2e ${phase}: green` : `\ngate e2e ${phase}: failed — report: npx playwright show-report`);
  return code === 0 ? 0 : 1;
}

if (isMain(import.meta.url)) {
  const [mode, arg] = process.argv.slice(2);
  process.chdir(ROOT);
  process.exitCode = mode === 'page' ? page(arg) : mode === 'e2e' ? e2e(arg) : (console.error('usage: node tools/gate.mjs page <url> | e2e <phase|smoke>'), 2);
}
