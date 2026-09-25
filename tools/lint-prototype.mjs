/**
 * Machine-checkable rules of the prototype regulation (docs/playbooks/prototype-rules.md).
 *
 * Every *.html under the directory (default project.config.json → paths.prototype) is parsed with
 * node-html-parser and checked:
 *   errors   - style="" with anything but custom properties (style="--d:120ms" is allowed);
 *            - inline event handlers (on*=);
 *            - stylesheets not from local assets (<link rel=stylesheet> outside assets/, @import of
 *              remote CSS) and any reference to external font CDNs (fonts.googleapis.com, …);
 *            - <img> without width, height or alt (alt="" is fine for decorative images);
 *            - not exactly one <h1>; <h1> inside .reveal;
 *            - more than one form.js-lead; for a page mapped in pages-map.json (prototype field):
 *              form count must match lead_form, lcp.selector must exist and, like every
 *              above_fold selector, must not be (inside) .reveal;
 *   warnings - class names that do not look like BEM (block, block__element, block--modifier,
 *              is-*, js-*).
 * Usage: node tools/lint-prototype.mjs [--dir=<dir>]   (npm run lint:prototype -- --dir=fixtures/demo-prototype)
 * Exit: 0 ok (also for an empty directory), 1 violations, 2 directory not found.
 */
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import path from 'node:path';
import { parse } from 'node-html-parser';
import { ROOT, isMain, loadConfig, parseArgs, readJson, report } from './lib.mjs';

const EXTERNAL_FONT = /fonts\.(?:googleapis|gstatic)\.com|use\.typekit\.net|fonts\.bunny\.net/i;
const LOCAL_ASSET = /^(?:\.{0,2}\/)*assets\//;
const BEM = /^[a-z][a-z0-9]*(?:-[a-z0-9]+)*(?:__[a-z0-9]+(?:-[a-z0-9]+)*)?(?:--[a-z0-9]+(?:-[a-z0-9]+)*)?$/;
const STATE = /^(?:is|has|js)-[a-z0-9]+(?:-[a-z0-9]+)*$/;

function lineOf(html, node) {
  const offset = node?.range?.[0] ?? 0;
  let line = 1;
  for (let i = 0; i < offset && i < html.length; i++) if (html.charCodeAt(i) === 10) line++;
  return line;
}

const attrs = (el) => Object.fromEntries(Object.entries(el.attributes).map(([k, v]) => [k.toLowerCase(), v]));

function inReveal(el) {
  for (let n = el; n && n.tagName; n = n.parentNode) {
    if (n.classList?.contains('reveal')) return true;
  }
  return false;
}

/** Only custom properties: "--a: 1; --b: calc(…)". */
export function isCustomPropertyStyle(style) {
  return splitDeclarations(style).every((d) => /^--[A-Za-z0-9_-]+\s*:/.test(d));
}

/** Split a style attribute on top-level ";" only (not inside url(), quotes or parentheses). */
export function splitDeclarations(style) {
  const out = [];
  let buf = '';
  let depth = 0;
  let quote = '';
  for (const ch of String(style)) {
    if (quote) {
      if (ch === quote) quote = '';
    } else if (ch === '"' || ch === "'") quote = ch;
    else if (ch === '(') depth++;
    else if (ch === ')') depth = Math.max(0, depth - 1);
    else if (ch === ';' && depth === 0) {
      out.push(buf);
      buf = '';
      continue;
    }
    buf += ch;
  }
  out.push(buf);
  return out.map((d) => d.trim()).filter(Boolean);
}

function inTemplate(el) {
  for (let n = el.parentNode; n && n.tagName; n = n.parentNode) if (n.tagName.toLowerCase() === 'template') return true;
  return false;
}

/** querySelectorAll that reports an invalid selector instead of throwing. */
function select(root, selector) {
  try {
    return { nodes: root.querySelectorAll(selector) };
  } catch (e) {
    return { nodes: [], error: e.message };
  }
}

/**
 * Lint one HTML document.
 * @param {string} html
 * @param {{ file?: string, page?: object|null }} ctx page = pages-map entry mapped to this file
 * @returns {{ errors: string[], warnings: string[] }}
 */
export function lintHtml(html, { file = 'page.html', page = null } = {}) {
  const errors = [];
  const warnings = [];
  const root = parse(html, { comment: false, blockTextElements: { script: true, noscript: true, style: true, pre: true } });
  const err = (node, msg) => errors.push(`${file}:${lineOf(html, node)}: ${msg}`);
  const badClasses = new Set();

  for (const el of root.querySelectorAll('*')) {
    const a = attrs(el);
    const tag = el.tagName.toLowerCase();

    if ('style' in a && !isCustomPropertyStyle(a.style)) {
      err(el, `<${tag}> inline style="${a.style}" → BEM modifier in CSS (only custom properties like style="--d:120ms" are allowed)`);
    }
    for (const name of Object.keys(a).filter((k) => /^on[a-z]+$/.test(k))) {
      err(el, `<${tag}> inline event handler ${name}= → listener in assets/js (find the node by id or .js-* class)`);
    }
    for (const key of ['href', 'src']) {
      if (a[key] && EXTERNAL_FONT.test(a[key])) err(el, `<${tag}> ${key} to an external font CDN (${a[key]}) → local woff2 in assets/fonts`);
    }
    if (tag === 'link' && /\bstylesheet\b/i.test(a.rel ?? '') && !LOCAL_ASSET.test(a.href ?? '')) {
      err(el, `stylesheet "${a.href ?? ''}" is not a local assets/ file → one local assets/css/main.css`);
    }
    if (tag === 'img') {
      const missing = ['width', 'height', 'alt'].filter((k) => !(k in a) || (k !== 'alt' && String(a[k]).trim() === ''));
      if (missing.length) err(el, `<img src="${a.src ?? ''}"> without ${missing.join(', ')} → set intrinsic width/height and alt (alt="" if decorative)`);
    }
    for (const cls of (a.class ?? '').split(/\s+/).filter(Boolean)) {
      if (!BEM.test(cls) && !STATE.test(cls) && cls !== 'reveal') badClasses.add(cls);
    }
  }

  for (const style of root.querySelectorAll('style')) {
    const text = style.text ?? '';
    if (/@import\s+(?:url\()?\s*['"]?(?:https?:)?\/\//i.test(text) || EXTERNAL_FONT.test(text)) {
      err(style, '<style> imports remote CSS / fonts → local assets');
    }
  }

  // <template> content is inert (not rendered until cloned by JS), so its <h1> does not count.
  const h1 = root.querySelectorAll('h1').filter((h) => !inTemplate(h));
  if (h1.length !== 1) err(h1[1] ?? root, `${h1.length} <h1> on the page → exactly one`);
  for (const h of h1) if (inReveal(h)) err(h, '<h1> is inside .reveal → the first screen renders without .reveal (LCP render delay)');

  const forms = root.querySelectorAll('form.js-lead');
  if (forms.length > 1) err(forms[1], `${forms.length} form.js-lead on the page → at most one (other CTAs are call cards without .js-lead)`);

  if (page) {
    const want = page.lead_form ? 1 : 0;
    if (forms.length !== want) err(forms[0] ?? root, `pages-map ${page.url}: lead_form=${page.lead_form}, found ${forms.length} form.js-lead`);
    const lcp = page.lcp?.selector;
    if (lcp) {
      const found = select(root, lcp);
      const node = found.nodes[0];
      if (found.error) err(root, `pages-map ${page.url}: invalid lcp selector "${lcp}" (${found.error}) → fix pages-map.json`);
      else if (!node) err(root, `pages-map ${page.url}: lcp selector "${lcp}" not found`);
      else if (inReveal(node)) err(node, `pages-map ${page.url}: LCP "${lcp}" is inside .reveal`);
    }
    for (const sel of page.above_fold ?? []) {
      const found = select(root, sel);
      if (found.error) err(root, `pages-map ${page.url}: invalid above_fold selector "${sel}" (${found.error}) → fix pages-map.json`);
      for (const node of found.nodes) {
        if (inReveal(node)) err(node, `pages-map ${page.url}: first-screen block "${sel}" is (inside) .reveal`);
      }
    }
  }

  if (badClasses.size) warnings.push(`${file}: non-BEM class names: ${[...badClasses].sort().join(', ')}`);
  return { errors, warnings };
}

function listHtml(dir) {
  const out = [];
  for (const name of readdirSync(dir)) {
    if (name === 'node_modules' || name.startsWith('.')) continue;
    const full = path.join(dir, name);
    if (statSync(full).isDirectory()) out.push(...listHtml(full));
    else if (/\.html?$/i.test(name)) out.push(full);
  }
  return out.sort();
}

/** Lint a directory. Pages are mapped only when it is the configured prototype directory. */
export function lintDir(dirRel, { cfg = loadConfig(), pages = null } = {}) {
  const abs = path.resolve(ROOT, dirRel);
  const isProjectPrototype = path.resolve(ROOT, cfg.paths.prototype) === abs;
  const map = pages ?? (isProjectPrototype ? readJson(cfg.paths.pages_map).pages : []);
  const byFile = new Map(map.filter((p) => p.prototype).map((p) => [p.prototype.replace(/\\/g, '/'), p]));
  const errors = [];
  const warnings = [];
  const files = listHtml(abs);
  for (const full of files) {
    const rel = path.relative(abs, full).split(path.sep).join('/');
    const shown = path.relative(ROOT, full).split(path.sep).join('/');
    const res = lintHtml(readFileSync(full, 'utf8'), { file: shown, page: byFile.get(rel) ?? null });
    errors.push(...res.errors);
    warnings.push(...res.warnings);
  }
  return { files, errors, warnings, mapped: files.filter((f) => byFile.has(path.relative(abs, f).split(path.sep).join('/'))).length };
}

function main() {
  const { opts } = parseArgs(process.argv.slice(2));
  if (opts.help) {
    console.log('usage: node tools/lint-prototype.mjs [--dir=<dir>]   (default: project.config.json → paths.prototype)');
    return;
  }
  const cfg = loadConfig();
  const dir = String(opts.dir ?? cfg.paths.prototype);
  if (!existsSync(path.resolve(ROOT, dir)) || !statSync(path.resolve(ROOT, dir)).isDirectory()) {
    console.error(`lint-prototype: directory not found: ${dir}`);
    process.exit(2);
  }
  const res = lintDir(dir, { cfg });
  if (res.warnings.length) console.warn(res.warnings.map((w) => `  ! ${w}`).join('\n'));
  if (!res.files.length) {
    console.log(`lint-prototype: ok — no *.html in ${dir}/ yet (phase 1 puts the project prototype there)`);
    return;
  }
  report('lint-prototype', res.errors, `ok (${res.files.length} file(s) in ${dir}/, ${res.mapped} mapped in pages-map)`);
}

if (isMain(import.meta.url)) main();
