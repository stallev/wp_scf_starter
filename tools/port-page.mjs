/**
 * Draft-generate a WordPress template from a prototype page, per the pages-map entry.
 *
 * What it does (see docs/REMAINING-TASKS.md §T3, .claude/commands/port-page.md):
 *   1. Looks up `<url>` in pages-map.json. Missing entry or `prototype: null` → exit 2.
 *   2. Extracts <main> (falls back to #main) from prototype/<file> (node-html-parser).
 *   3. Rewrites links:
 *      - `assets/...` (relative, no leading slash) → `<?php echo esc_url( starter_asset_url( '...' ) ); ?>`
 *        — starter_asset_url() is the theme's canonical asset-URL helper (inc/assets.php); the ADR/
 *        command text mentions get_theme_file_uri() as an example, this is the project's own equivalent.
 *      - `<file>.html` / `<file>.html#hash` that matches another page's `prototype` field in
 *        pages-map → `<?php echo esc_url( starter_url( '<pages-map url>' ) ); ?>` (+ `#hash` appended
 *        outside the PHP tag, since starter_url() takes a path, not a fragment). Unmapped *.html
 *        links (pages not ported yet) are left untouched — nothing to point them at. A link with a
 *        query string (`page.html?x=1`) is also left untouched: pages-map maps bare paths, so there
 *        is no reliable base file to resolve it against — intentional, not a bug.
 *      - `srcset` is deliberately NOT rewritten (a real srcset is a comma-separated list of
 *        "url descriptor" candidates, e.g. "a.jpg 1x, a@2x.jpg 2x"; rewriting the whole attribute as
 *        one starter_asset_url() call would mangle it). Instead a line whose srcset references
 *        assets/ gets a `<!-- TODO(port-page): ... -->` comment above it for a manual per-candidate fix.
 *   4. Neutralizes literal `<?php`, `<?=` and `?>` sequences found in *text nodes* (each occurrence is
 *      HTML-entity-escaped: `&lt;?php`, `?&gt;`), so ordinary prototype copy can't turn into executable
 *      PHP once embedded in the .php draft. This runs before link/asset rewriting so it never touches
 *      the PHP those steps inject. Known, accepted gap: PHP scans a file's raw bytes for `<?php`
 *      regardless of HTML structure, so a literal tag sitting inside some other attribute value we
 *      don't rewrite (not href/src/srcset — those are covered above) would still execute; prototype
 *      attributes in practice hold class/id/data values, not free text, so this is treated as a
 *      documented, low-probability gap rather than a full guarantee.
 *   5. Runs the same hardcode scan as check:hardcode (tools/check-hardcode.mjs → buildNeedles() /
 *      scanText(), imported, not copied) against the rewritten draft and inserts an
 *      `<!-- TODO(hardcode): ... -->` comment on the line above every hit.
 *   6. Wraps the result in get_header()/get_footer() and writes it to
 *      wp-content/themes/<theme>/<template> (template name from the pages-map entry). Refuses to
 *      overwrite an existing file unless --force. --dry-run always builds and prints the draft —
 *      including when the target already exists and --force wasn't given — and never writes; only an
 *      actual write is blocked by a missing --force.
 *   7. Prints a checklist of manual follow-ups (parts, starter_image(), .reveal, lead form, gate:page).
 *
 * This is a draft generator, not a full port: template-parts extraction, starter_get_*() data wiring,
 * starter_image() and .reveal placement stay manual (per AGENTS.md cycle and /port-page).
 *
 * `--prototype-dir` may be relative (resolved against the repo root) or absolute.
 *
 * Usage: node tools/port-page.mjs <url> [--dry-run] [--force] [--prototype-dir=<dir>]
 *   (npm run port-page -- <url> [--dry-run] [--force] [--prototype-dir=<dir>])
 * Exit: 0 ok, 1 refused (existing file without --force / no <main> found), 2 pages-map lookup failed.
 */
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { parse } from 'node-html-parser';
import { loadNeedlesFromConfig, scanText } from './check-hardcode.mjs';
import { ROOT, isMain, loadConfig, parseArgs, readJson } from './lib.mjs';

// srcset is intentionally absent: see the "srcset" bullet in the file header.
const ASSET_ATTRS = [
  ['img', 'src'],
  ['source', 'src'],
  ['link', 'href'],
  ['script', 'src'],
  ['video', 'src'],
  ['video', 'poster'],
  ['audio', 'src'],
];
const LOCAL_ASSET = /^assets\//;
// No query-string form on purpose (page.html?x=1 stays unmapped) — see file header.
const HTML_HREF = /^([^#]+\.html)(#.*)?$/i;
/** A srcset attribute that references a local asset — flagged, not rewritten (see file header). */
const SRCSET_ASSET_LINE = /\bsrcset\s*=\s*"[^"]*\bassets\//i;
/** Literal PHP open/close tags found in prototype text — neutralized before they reach a .php draft. */
const PHP_TAG = /<\?php|<\?=|\?>/gi;

/** Find pages-map entry for `url`, or null. */
export function findPage(pages, url) {
  return pages.find((p) => p.url === url) ?? null;
}

/** <main> (fallback #main) innerHTML of a prototype document. Throws if neither is found. */
export function extractMainHtml(html, { file = 'prototype.html' } = {}) {
  const root = parse(html, { comment: false, blockTextElements: { script: true, noscript: true, style: true, pre: true } });
  const main = root.querySelector('main') ?? root.querySelector('#main');
  if (!main) throw new Error(`${file}: no <main> (or #main) element found`);
  return main.innerHTML;
}

/** Map of `page.prototype` (relative filename, as written in pages-map) → `page.url`, for ported pages. */
export function buildLinkMap(pages) {
  const map = new Map();
  for (const p of pages) if (p.prototype) map.set(p.prototype.replace(/\\/g, '/'), p.url);
  return map;
}

/**
 * Rewrite `assets/...` and mapped `*.html` links in a fragment of prototype markup.
 * @param {string} html Fragment (e.g. <main> innerHTML).
 * @param {Map<string,string>} linkMap From buildLinkMap().
 * @returns {string}
 */
export function rewriteLinks(html, linkMap) {
  const root = parse(`<div id="__port_page_root__">${html}</div>`, {
    comment: false,
    blockTextElements: { script: true, noscript: true, style: true, pre: true },
  });
  const wrapper = root.querySelector('#__port_page_root__');

  for (const el of wrapper.querySelectorAll('a[href]')) {
    const href = el.getAttribute('href') ?? '';
    const m = HTML_HREF.exec(href);
    if (!m) continue;
    const target = linkMap.get(m[1]);
    if (!target) continue; // Page not ported yet: nothing to point this link at.
    el.setAttribute('href', `<?php echo esc_url( starter_url( '${target}' ) ); ?>${m[2] ?? ''}`);
  }

  for (const [tag, attr] of ASSET_ATTRS) {
    for (const el of wrapper.querySelectorAll(`${tag}[${attr}]`)) {
      const value = el.getAttribute(attr) ?? '';
      if (LOCAL_ASSET.test(value)) el.setAttribute(attr, `<?php echo esc_url( starter_asset_url( '${value}' ) ); ?>`);
    }
  }

  return wrapper.innerHTML;
}

/**
 * Flag (not rewrite) any line whose `srcset` references a local asset, with a manual-fix TODO above
 * it — see the "srcset" bullet in the file header for why this isn't rewritten automatically.
 */
export function flagSrcsetForManualRewrite(html) {
  const out = [];
  for (const line of html.split(/\r?\n/)) {
    if (SRCSET_ASSET_LINE.test(line)) {
      out.push('<!-- TODO(port-page): srcset references assets/ — rewrite each "url descriptor" candidate through starter_asset_url() by hand, srcset was left as-is -->');
    }
    out.push(line);
  }
  return out.join('\n');
}

/**
 * Neutralize literal `<?php`, `<?=` and `?>` found in text nodes (not attribute values) of an HTML
 * fragment, so ordinary prototype copy can't execute as PHP once embedded in the generated draft.
 * Attribute values are left alone on purpose: href/src/srcset are fully replaced by the later rewrite
 * steps anyway, and any other attribute holding literal "<?php" text is an accepted, documented gap
 * (see the file header) rather than something this pass tries to catch.
 */
export function neutralizeEmbeddedPhp(html) {
  const root = parse(`<div id="__port_page_root__">${html}</div>`, {
    comment: false,
    blockTextElements: { script: true, noscript: true, style: true, pre: true },
  });
  const wrapper = root.querySelector('#__port_page_root__');
  const escapeTag = (m) => (m === '?>' ? '?&gt;' : `&lt;${m.slice(1)}`);
  const walk = (node) => {
    if (node.nodeType === 3) {
      node.rawText = node.rawText.replace(PHP_TAG, escapeTag);
      return;
    }
    for (const child of node.childNodes ?? []) walk(child);
  };
  for (const child of wrapper.childNodes) walk(child);
  return wrapper.innerHTML;
}

/**
 * Insert `<!-- TODO(hardcode): ... -->` above every line the check:hardcode scan flags.
 * @param {string} html
 * @param {ReturnType<typeof loadNeedlesFromConfig>} needles
 * @param {string} file Label used only for the (discarded) `file:line:` prefix scanText produces.
 */
export function annotateHardcode(html, needles, file = 'draft.php') {
  const findings = scanText(html, file, needles);
  if (!findings.length) return html;
  const byLine = new Map();
  for (const f of findings) {
    const m = /^.*?:(\d+): (.*)$/.exec(f);
    if (!m) continue;
    const line = Number(m[1]);
    if (!byLine.has(line)) byLine.set(line, []);
    byLine.get(line).push(m[2]);
  }
  const out = [];
  html.split(/\r?\n/).forEach((line, i) => {
    for (const msg of byLine.get(i + 1) ?? []) out.push(`<!-- TODO(hardcode): ${msg} -->`);
    out.push(line);
  });
  return out.join('\n');
}

/** Assemble the full draft template file: header comment + get_header() … get_footer(). */
export function buildDraft({ url, template, body }) {
  return `<?php
/**
 * Draft ported from the prototype for ${url} by tools/port-page.mjs.
 * TODO: extract repeated blocks into template-parts, wire data through starter_get_*() /
 * starter_company_value() / starter_page_config(), move images to starter_image(), keep .reveal only
 * below the first screen, check the lead form against lead_form in pages-map, then run
 * \`npm run gate:page -- ${url}\`.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="site-main">
${body}
</main>
<?php
get_footer();
`;
}

function checklist(url) {
  return [
    '',
    'Next manual steps:',
    '  - Move blocks reused on >=2 pages (or already in template-parts/) into a part with args.',
    "  - Replace remaining literals with starter_get_*() / starter_company_value() / starter_page_config().",
    '  - Route images through starter_image(); only the LCP image gets priority.',
    '  - Keep the lcp node and above_fold blocks free of .reveal; add .reveal only below the first screen.',
    '  - Check the lead form: exactly one form.js-lead if lead_form is true in pages-map, none otherwise.',
    '  - Resolve every <!-- TODO(hardcode): ... --> left in the draft.',
    `  - Run: npm run gate:page -- ${url}`,
  ].join('\n');
}

/**
 * Core logic, testable without touching disk (pass `write: false`) or the real config/pages-map.
 * @returns {{ page: object, protoFile: string, themeFile: string, content: string, wouldOverwrite: boolean }}
 */
export function portPage({
  url,
  cfg = loadConfig(),
  pages,
  prototypeDir,
  needles = loadNeedlesFromConfig(cfg),
  dryRun = false,
  force = false,
  write = true,
  themeRoot,
}) {
  const allPages = pages ?? readJson(cfg.paths.pages_map).pages;
  const page = findPage(allPages, url);
  if (!page || !page.prototype) {
    const reason = !page ? 'no such URL in pages-map.json' : 'pages-map entry has prototype: null (nothing to port)';
    const err = new Error(`port-page: ${url}: ${reason}`);
    err.exitCode = 2;
    throw err;
  }

  const dir = prototypeDir ?? cfg.paths.prototype;
  // path.resolve (not path.join): an absolute --prototype-dir must replace ROOT outright. path.join
  // does not reset on a later absolute segment on Windows the way it does on POSIX.
  const protoFile = path.resolve(ROOT, dir, page.prototype);
  if (!existsSync(protoFile)) {
    const err = new Error(`port-page: prototype file not found: ${path.relative(ROOT, protoFile)}`);
    err.exitCode = 2;
    throw err;
  }

  // Neutralize embedded PHP tags BEFORE rewriting links/assets: those steps inject our own, legitimate
  // PHP into attribute values, which must not be touched by the text-only neutralization pass.
  const mainHtml = neutralizeEmbeddedPhp(extractMainHtml(readFileSync(protoFile, 'utf8'), { file: page.prototype }));
  const rewritten = flagSrcsetForManualRewrite(rewriteLinks(mainHtml, buildLinkMap(allPages)));
  const annotated = annotateHardcode(rewritten, needles, page.template);
  const content = buildDraft({ url, template: page.template, body: annotated });

  const themeFile = path.join(themeRoot ?? path.join(ROOT, 'wp-content/themes', cfg.slug.theme), page.template);
  const wouldOverwrite = existsSync(themeFile);

  // --dry-run always previews, existing target or not: only an actual write is guarded by --force.
  if (write && !dryRun) {
    if (wouldOverwrite && !force) {
      const err = new Error(`port-page: ${path.relative(ROOT, themeFile)} already exists — use --force to overwrite`);
      err.exitCode = 1;
      throw err;
    }
    mkdirSync(path.dirname(themeFile), { recursive: true });
    writeFileSync(themeFile, content, 'utf8');
  }

  return { page, protoFile, themeFile, content, wouldOverwrite };
}

function main() {
  const { opts, positional } = parseArgs(process.argv.slice(2));
  if (opts.help || !positional[0]) {
    console.log('usage: node tools/port-page.mjs <url> [--dry-run] [--force] [--prototype-dir=<dir>]');
    process.exitCode = opts.help ? 0 : 2;
    return;
  }
  const url = positional[0];
  try {
    const cfg = loadConfig();
    const result = portPage({
      url,
      cfg,
      prototypeDir: opts['prototype-dir'],
      dryRun: Boolean(opts['dry-run']),
      force: Boolean(opts.force),
    });
    const rel = path.relative(ROOT, result.themeFile).split(path.sep).join('/');
    if (opts['dry-run']) {
      console.log(`port-page: --dry-run, nothing written. Would ${result.wouldOverwrite ? 'overwrite' : 'create'} ${rel}:\n`);
      console.log(result.content);
    } else {
      console.log(`port-page: wrote ${rel}`);
    }
    console.log(checklist(url));
  } catch (err) {
    console.error(err.message);
    process.exit(err.exitCode ?? 1);
  }
}

if (isMain(import.meta.url)) main();
