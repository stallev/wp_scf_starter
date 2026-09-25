/**
 * Gate helpers that are more than a chain of npm scripts.
 *
 *   node tools/gate.mjs page <url>   gate for one ported page (/port-page): the URL must be in
 *                                     pages-map.json; runs check:config, check:hardcode, lint:php,
 *                                     then the page's e2e suites (Playwright — M6b; announced, not run yet).
 *   node tools/gate.mjs e2e <phase>  announce the e2e suites of a phase gate (gate:4 … gate:7) that
 *                                     M6b adds; the static part of the gate runs before it in package.json.
 * <url>: "/contacts/", "contacts" or "/" ("home"). Git Bash rewrites arguments starting with "/" into
 * Windows paths — this is detected and undone; MSYS_NO_PATHCONV=1 avoids it.
 * Exit: 0 ok, 1 a check failed, 2 bad arguments (unknown URL / phase).
 */
import { ROOT, isMain, loadConfig, readJson, run } from './lib.mjs';

/** Phase → e2e suites (tests/e2e/*.spec.ts, data from pages-map). */
export const PHASE_SUITES = {
  4: ['navigation', 'perf-markup (fonts, scripts)'],
  5: ['static', 'visual', 'perf-markup', 'console'],
  6: ['forms', 'dynamic', 'seo', 'a11y'],
  7: ['static', 'navigation', 'forms', 'dynamic', 'seo', 'a11y', 'perf-markup', 'console', 'visual'],
};
const PAGE_SUITES = ['static', 'perf-markup', 'console', 'visual'];

/** Normalise a page argument to a pages-map URL ("/x/y/"). */
export function normalizePageArg(arg) {
  let a = String(arg ?? '').trim().replace(/\\/g, '/');
  const msys = /^[A-Za-z]:\/.*?\/Git(\/.*)?$/i.exec(a); // "/contacts/" → "C:/Program Files/Git/contacts/"
  if (msys) a = msys[1] ?? '/';
  if (a === '' || a === 'home' || a === '/') return '/';
  return `/${a.replace(/^\/+|\/+$/g, '')}/`;
}

const node = (...args) => run(process.execPath, args, { shell: false });

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
  console.log(`\ne2e for ${url}: ${PAGE_SUITES.join(', ')} — M6b (Playwright), not run yet`);
  console.log(`gate:page ${url}: static checks green`);
  return 0;
}

function e2e(phase) {
  const suites = PHASE_SUITES[phase];
  if (!suites) {
    console.error(`gate e2e: unknown phase "${phase}" (one of ${Object.keys(PHASE_SUITES).join(', ')})`);
    return 2;
  }
  console.log(`gate:${phase}: static checks green; e2e ${suites.join(', ')} — M6b (Playwright), not run yet`);
  return 0;
}

if (isMain(import.meta.url)) {
  const [mode, arg] = process.argv.slice(2);
  process.chdir(ROOT);
  process.exitCode = mode === 'page' ? page(arg) : mode === 'e2e' ? e2e(arg) : (console.error('usage: node tools/gate.mjs page <url> | e2e <phase>'), 2);
}
