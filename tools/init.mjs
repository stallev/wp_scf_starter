/**
 * Project init (phase 0): replace the starter placeholders with project names and reset demo data.
 *
 *   npm run init -- --prefix=acme --name="Acme" [--theme=acme] [--core=acme-core] [--text-domain=acme]
 *                   [--keep-demo] [--dry-run] [--force] [--allow-dirty]
 *
 * Names (defaults): theme = prefix, core = <prefix>-core, text domain = theme. Source names are read
 * from project.config.json → slug / project.name (the starter ships "starter" / "starter-core" / "Starter").
 *
 * Replacements — word-boundary aware, applied to repo files (tracked + untracked, not ignored) except
 * binaries, lock files, example/, node_modules/, vendor/, history (docs/decisions/, docs/STARTER-PLAN.md)
 * and this tool with its test:
 *   starter_ / STARTER_ / Starter_ → acme_ / ACME_ / Acme_      (functions, hooks, meta, options, CPT,
 *                                                                 constants, classes; also _starter_…,
 *                                                                 group_starter_…, \bstarter_ in regexes)
 *   starter-core → core slug; themes/starter → themes/<theme>
 *   starter- / starter: / starterX → acme- / acme: / acmeX     (handles, CSS classes, options slug,
 *                                                                 seed mapping, JS events, JS globals)
 *   'starter' / "starter" / `starter` → text domain / theme;  wp starter … → wp acme …
 *   slug values in project.config.json and naming.json, phpcs prefixes / text_domain, Text Domain,
 *   Theme Name / Plugin Name / web manifest → --name; standalone "Starter" in wp-content → Acme.
 * Plain prose ("the starter") is left alone. Renames: theme dir, mu-plugin loader + dir, class-starter-*.php.
 * Unless --keep-demo: pages-map pages → [], seed/*.json → empty skeletons, seed/images/demo-* deleted.
 * Then runs build:config and build:naming. Refuses to run when the prefix is no longer "starter"
 * (already initialised) unless --force; --dry-run prints the plan and writes nothing.
 *
 * Safety: refuses a dirty working tree (unless --allow-dirty) so init can be undone with git; stop wp-env
 * first (Windows bind mounts lock renames); a failed rename prints what moved and how to recover.
 * Exit: 0 ok, 1 post-init build failed, 2 bad arguments / already initialised / dirty tree / rename failed.
 */
import { existsSync, mkdirSync, readFileSync, readdirSync, renameSync, rmSync, rmdirSync, writeFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { BINARY_EXT, ROOT, isMain, listRepoFiles, loadConfig, parseArgs, readJson, run } from './lib.mjs';

const STARTER_PREFIX = 'starter';
const SKIP = [
  /^example\//,
  /(^|\/)node_modules\//,
  /(^|\/)vendor\//,
  /^docs\/decisions\//,
  /^docs\/STARTER-PLAN\.md$/,
  /^tools\/init\.mjs$/,
  /^tools\/__tests__\/(?:init|review-fixes)\.test\.mjs$/,
  /(^|\/)(package-lock\.json|composer\.lock)$/,
];
const RESERVED = new Set(['wp', 'wordpress', 'admin', 'core', 'theme', 'plugin']);
const SEED_SKELETONS = {
  'faq.json': { items: [] },
  'reviews.json': { items: [] },
  'projects.json': { items: [] },
  'posts.json': { items: [] },
  'service-cards.json': { items: [] },
  'menus.json': { menus: [] },
};

const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);
/** Not preceded by a letter/digit — or preceded by a regex `\b` (naming.json patterns). */
const START = String.raw`(?:(?<![A-Za-z0-9])|(?<=\\b))`;
const END = '(?![A-Za-z0-9_-])';

/** Complete name set from CLI-style options. */
export function resolveNames(o) {
  const prefix = String(o.prefix ?? '');
  const theme = String(o.theme ?? prefix);
  return {
    prefix,
    theme,
    core: String(o.core ?? `${prefix}-core`),
    text_domain: String(o['text-domain'] ?? o.text_domain ?? theme),
    name: String(o.name ?? cap(prefix)),
  };
}

/** Argument errors (empty = ok). `forbidden` = compiled naming.json rules without `paths`. */
export function validateNames(from, to, forbidden = []) {
  const errors = [];
  if (!/^[a-z][a-z0-9]{1,9}$/.test(to.prefix)) errors.push(`--prefix "${to.prefix}": 2–10 chars, a-z0-9, starts with a letter (CPT names are limited to 20 chars)`);
  if (RESERVED.has(to.prefix)) errors.push(`--prefix "${to.prefix}" is reserved`);
  for (const key of ['theme', 'core', 'text_domain']) {
    if (!/^[a-z][a-z0-9-]*$/.test(to[key])) errors.push(`${key} "${to[key]}": a-z0-9 and hyphens, starts with a letter`);
  }
  if (!to.name.trim()) errors.push('--name is required (display name, e.g. --name="Acme")');
  for (const key of ['prefix', 'theme', 'core', 'text_domain']) {
    if (to[key] !== from[key] && to[key].includes(from.prefix)) errors.push(`${key} "${to[key]}" contains the placeholder "${from.prefix}" — pick a name without it`);
  }
  for (const probe of [`${to.prefix}_x`, `${to.prefix.toUpperCase()}_X`, `${to.core}/`, `${to.theme}-x`]) {
    const hit = forbidden.find((f) => f.re.test(probe));
    if (hit) errors.push(`names derived from "${to.prefix}" hit forbidden rule ${hit.id} (${hit.reason})`);
  }
  return errors;
}

/**
 * Ordered replacement rules from → to. Each: { id, re (global), to (replacement), files? (RegExp on path) }.
 * Specific rules come first so that generic ones never see their targets.
 */
export function buildRules(from, to) {
  const f = { p: esc(from.prefix), P: esc(from.prefix.toUpperCase()), C: esc(cap(from.prefix)), theme: esc(from.theme), core: esc(from.core), td: esc(from.text_domain), name: esc(from.name) };
  const t = { p: to.prefix, P: to.prefix.toUpperCase(), C: cap(to.prefix) };
  const r = (id, source, replacement, files) => ({ id, re: new RegExp(source, 'g'), to: replacement, files });
  const json = /\.json$/;
  return [
    r('json:prefix', String.raw`("prefix"\s*:\s*")${f.p}(")`, `$1${t.p}$2`, json),
    r('json:PREFIX', String.raw`("PREFIX"\s*:\s*")${f.P}(")`, `$1${t.P}$2`, json),
    r('json:theme', String.raw`("theme"\s*:\s*")${f.theme}(")`, `$1${to.theme}$2`, json),
    r('json:core', String.raw`("core"\s*:\s*")${f.core}(")`, `$1${to.core}$2`, json),
    r('json:text_domain', String.raw`("text_domain"\s*:\s*")${f.td}(")`, `$1${to.text_domain}$2`, json),
    r('json:name', String.raw`("(?:name|short_name)"\s*:\s*")${f.name}(")`, `$1${to.name}$2`, /(^project\.config\.json|\.webmanifest)$/),
    r('phpcs:prefixes', String.raw`(name="prefixes"[^>]*>\s*<element value=")${f.p}(")`, `$1${t.p}$2`, /phpcs\.xml/),
    r('phpcs:text_domain', String.raw`(name="text_domain"[^>]*>\s*<element value=")${f.td}(")`, `$1${to.text_domain}$2`, /phpcs\.xml/),
    r('header:Theme Name', String.raw`(Theme Name:[ \t]*)${f.name}${END}`, `$1${to.name}`),
    r('header:Plugin Name', String.raw`(Plugin Name:[ \t]*)${f.name}${END}`, `$1${to.name}`),
    r('header:Text Domain', String.raw`(Text Domain:[ \t]*)${f.td}${END}`, `$1${to.text_domain}`),
    r('path:themes/', String.raw`(themes/)${f.theme}${END}`, `$1${to.theme}`),
    r('cli:add_command', String.raw`(add_command\(\s*['"])${f.p}(['"])`, `$1${t.p}$2`),
    r('cli:wp', String.raw`(?<=\bwp (?:-- )?)${f.p}${END}`, t.p),
    r('core', `${START}${f.core}(?![A-Za-z0-9_])`, to.core),
    r('PREFIX_', `${START}${f.P}_`, `${t.P}_`),
    r('Prefix_', `${START}${f.C}_`, `${t.C}_`),
    r('prefix_', `${START}${f.p}_`, `${t.p}_`),
    r('prefix-', `(?<![A-Za-z0-9])${f.p}-(?=[a-z0-9])`, `${t.p}-`),
    r('prefix:', `(?<![A-Za-z0-9])${f.p}:(?=[a-z])`, `${t.p}:`),
    r('prefixCamel', `(?<![A-Za-z0-9])${f.p}(?=[A-Z])`, t.p),
    r('quoted text domain', String.raw`(['"])${f.td}\1`, `$1${to.text_domain}$1`),
    r('`theme`', `\`${f.theme}\``, `\`${to.theme}\``),
    r('Standalone Name', `(?<![A-Za-z0-9_])${f.C}${END}`, t.C, /^(wp-content\/|seed\/|phpcs\.xml)/),
  ];
}

/** Apply rules to one file's text. Returns { text, counts: { ruleId: n } }. */
export function transformText(text, file, rules) {
  const counts = {};
  let out = text;
  for (const rule of rules) {
    if (rule.files && !rule.files.test(file)) continue;
    const n = out.match(rule.re)?.length ?? 0;
    if (!n) continue;
    counts[rule.id] = n;
    out = out.replace(rule.re, rule.to);
  }
  return { text: out, counts };
}

/** New repo-relative path of a file after the directory / class-file renames. */
export function transformPath(file, from, to) {
  let p = file;
  p = p.replace(new RegExp(`^wp-content/themes/${esc(from.theme)}/`), `wp-content/themes/${to.theme}/`);
  p = p.replace(new RegExp(`^wp-content/mu-plugins/${esc(from.core)}(/|\\.php$)`), `wp-content/mu-plugins/${to.core}$1`);
  p = p.replace(new RegExp(`(^|/)class-${esc(from.prefix)}-([^/]+)$`), `$1class-${to.prefix}-$2`);
  return p;
}

export const shouldSkip = (file) => SKIP.some((re) => re.test(file)) || BINARY_EXT.test(file);

/**
 * Plan the whole init in memory (no I/O): files = [{ path, text }] (text null for binaries).
 * Returns { edits: [{ path, text, counts }], renames: [{ from, to }], deletes: string[], writes: [{ path, text }] }.
 */
export function planInit({ files, from, to, keepDemo = false, seedDir = 'seed', pagesMap = 'pages-map.json' }) {
  const rules = buildRules(from, to);
  const edits = [];
  const renames = [];
  const deletes = [];
  const writes = [];
  const demoFiles = new Set();
  if (!keepDemo) {
    demoFiles.add(pagesMap);
    for (const name of [...Object.keys(SEED_SKELETONS), 'company.json']) demoFiles.add(`${seedDir}/${name}`);
    writes.push({ path: pagesMap, text: `${JSON.stringify({ $schema: './schemas/pages-map.schema.json', pages: [] }, null, 2)}\n` });
    writes.push({ path: `${seedDir}/company.json`, text: `${JSON.stringify({ name: to.name }, null, 2)}\n` });
    for (const [name, data] of Object.entries(SEED_SKELETONS)) writes.push({ path: `${seedDir}/${name}`, text: `${JSON.stringify(data, null, 2)}\n` });
  }

  for (const file of files) {
    if (!keepDemo && new RegExp(`^${esc(seedDir)}/images/demo-[^/]+$`).test(file.path)) {
      deletes.push(file.path);
      continue;
    }
    const target = transformPath(file.path, from, to);
    if (target !== file.path) renames.push({ from: file.path, to: target });
    if (shouldSkip(file.path) || file.text == null || demoFiles.has(file.path)) continue;
    const res = transformText(file.text, file.path, rules);
    if (res.text !== file.text) edits.push({ path: file.path, text: res.text, counts: res.counts });
  }
  return { edits, renames, deletes, writes };
}

/** Lines that still mention the old prefix after the plan (prose, history) — for the summary. */
export function leftovers(files, plan, from) {
  const edited = new Map(plan.edits.map((e) => [e.path, e.text]));
  // Letter boundaries: "starter_x" / "STARTER-PLAN" are reported, "restarter" is not (short prefixes).
  const re = new RegExp(`(?<![A-Za-z])${esc(from.prefix)}(?![A-Za-z])`, 'i');
  const out = [];
  for (const f of files) {
    if (shouldSkip(f.path) || f.text == null || plan.writes.some((w) => w.path === f.path) || plan.deletes.includes(f.path)) continue;
    (edited.get(f.path) ?? f.text).split(/\r?\n/).forEach((line, i) => {
      if (re.test(line.replace(/стартер/gi, ''))) out.push(`${f.path}:${i + 1}: ${line.trim().slice(0, 140)}`);
    });
  }
  return out;
}

function removeEmptyDirs(dir) {
  if (!existsSync(dir)) return;
  for (const e of readdirSync(dir, { withFileTypes: true })) if (e.isDirectory()) removeEmptyDirs(path.join(dir, e.name));
  if (!readdirSync(dir).length) rmdirSync(dir);
}

/** Error text when the tree has uncommitted changes (init must be revertable with git), else null. */
export function dirtyTreeError(porcelain, { allowDirty = false, dry = false } = {}) {
  if (dry || allowDirty || !String(porcelain).trim()) return null;
  const n = String(porcelain).trim().split(/\r?\n/).length;
  return `init: the working tree has ${n} uncommitted change(s). Commit or stash them first (init is undone with git checkout -- . && git clean -fd), or pass --allow-dirty.`;
}

export class RenameError extends Error {}

/**
 * Run rename steps [from, to] (repo-relative) in order. On failure throws RenameError listing what was
 * already moved and how to recover. `renameFn` is injectable for tests.
 */
export function runRenames(steps, renameFn = (a, b) => renameSync(path.join(ROOT, a), path.join(ROOT, b))) {
  const done = [];
  for (const [a, b] of steps) {
    try {
      renameFn(a, b);
      done.push(`${a} → ${b}`);
    } catch (err) {
      throw new RenameError(
        [
          `init: cannot rename ${a} → ${b} (${err.code ?? err.message}).`,
          'On Windows a running wp-env bind-mounts the theme and mu-plugins (EPERM / EBUSY): npm run env:stop, then retry.',
          done.length ? `Already renamed:\n${done.map((d) => `  ${d}`).join('\n')}` : 'Nothing was renamed yet.',
          'File contents were already rewritten. Recover a clean tree with: git checkout -- . && git clean -fd',
        ].join('\n'),
      );
    }
  }
  return done;
}

function applyPlan(plan, from, to) {
  for (const e of plan.edits) writeFileSync(path.join(ROOT, e.path), e.text);
  for (const w of plan.writes) writeFileSync(path.join(ROOT, w.path), w.text);
  for (const d of plan.deletes) rmSync(path.join(ROOT, d), { force: true });
  // Whole directories first (keeps ignored working files such as assets/images/source-photos), then files.
  const steps = [
    [`wp-content/themes/${from.theme}`, `wp-content/themes/${to.theme}`],
    [`wp-content/mu-plugins/${from.core}`, `wp-content/mu-plugins/${to.core}`],
    [`wp-content/mu-plugins/${from.core}.php`, `wp-content/mu-plugins/${to.core}.php`],
  ].filter(([a, b]) => a !== b && existsSync(path.join(ROOT, a)));
  runRenames(steps);
  const fileSteps = [];
  for (const r of plan.renames) {
    const current = transformPath(r.from, from, { ...to, prefix: from.prefix }); // after the dir moves
    if (current === r.to || !existsSync(path.join(ROOT, current))) continue;
    mkdirSync(path.dirname(path.join(ROOT, r.to)), { recursive: true });
    fileSteps.push([current, r.to]);
  }
  runRenames(fileSteps);
  removeEmptyDirs(path.join(ROOT, 'seed', 'images'));
}

function sumCounts(edits) {
  const total = {};
  for (const e of edits) for (const [k, v] of Object.entries(e.counts)) total[k] = (total[k] ?? 0) + v;
  return total;
}

function main() {
  const { opts } = parseArgs(process.argv.slice(2));
  if (opts.help || !opts.prefix) {
    console.log('usage: npm run init -- --prefix=acme --name="Acme" [--theme=acme] [--core=acme-core] [--text-domain=acme] [--keep-demo] [--dry-run] [--force] [--allow-dirty]');
    console.log('Stop the environment first (npm run env:stop): on Windows wp-env bind mounts block directory renames.');
    process.exit(opts.help ? 0 : 2);
  }
  const cfg = loadConfig();
  const from = { ...cfg.slug, name: cfg.project.name };
  const to = resolveNames(opts);
  if (from.prefix !== STARTER_PREFIX && !opts.force) {
    console.error(`init: already initialised (prefix is "${from.prefix}", not "${STARTER_PREFIX}"). Re-run with --force to rename "${from.prefix}" → "${to.prefix}".`);
    process.exit(2);
  }
  const naming = readJson('docs/contracts/naming.json');
  const forbidden = naming.forbidden.filter((x) => !x.paths).map((x) => ({ ...x, re: x.name ? new RegExp(`(?<![\\w-])${esc(x.name)}(?![\\w-])`) : new RegExp(x.pattern, x.flags ?? '') }));
  const errors = validateNames(from, to, forbidden);
  if (errors.length) {
    console.error(errors.map((e) => `  ✗ ${e}`).join('\n'));
    process.exit(2);
  }

  const files = listRepoFiles().map((p) => ({ path: p, text: shouldSkip(p) ? null : readFileSync(path.join(ROOT, p), 'utf8') }));
  const plan = planInit({ files, from, to, keepDemo: Boolean(opts['keep-demo']), seedDir: cfg.paths.seed, pagesMap: cfg.paths.pages_map });
  const dry = Boolean(opts['dry-run']);

  console.log(`init${dry ? ' (dry run — nothing is written)' : ''}: ${from.prefix}/${from.theme}/${from.core}/${from.text_domain} "${from.name}" → ${to.prefix}/${to.theme}/${to.core}/${to.text_domain} "${to.name}"`);
  console.log(`  files edited: ${plan.edits.length}`);
  for (const [id, n] of Object.entries(sumCounts(plan.edits)).sort((a, b) => b[1] - a[1])) console.log(`    ${String(n).padStart(5)}  ${id}`);
  console.log(`  renames: ${plan.renames.length}`);
  for (const r of plan.renames.filter((x) => path.posix.basename(x.from) !== path.posix.basename(x.to))) {
    console.log(`    ${r.from} → ${r.to}`);
  }
  console.log(`    (+ directories wp-content/themes/${from.theme}/ → ${to.theme}/, wp-content/mu-plugins/${from.core}{.php,/} → ${to.core}{.php,/})`);
  console.log(opts['keep-demo'] ? '  demo data: kept (--keep-demo)' : `  demo data: pages-map emptied, ${plan.writes.length - 1} seed skeletons, ${plan.deletes.length} demo image(s) deleted`);
  const left = leftovers(files, plan, from);
  console.log(`  "${from.prefix}" left as is (prose / history): ${left.length} line(s)${left.length ? ' — first ones:' : ''}`);
  for (const l of left.slice(0, 12)) console.log(`    ${l}`);

  if (dry) return;
  const status = spawnSync('git', ['status', '--porcelain'], { cwd: ROOT, encoding: 'utf8' });
  const dirty = dirtyTreeError(status.stdout ?? '', { allowDirty: Boolean(opts['allow-dirty']) });
  if (dirty) {
    console.error(dirty);
    process.exit(2);
  }
  console.log('  hint: run npm run env:stop before init — a running wp-env can lock the theme / mu-plugin directories (EPERM / EBUSY).');
  try {
    applyPlan(plan, from, to);
  } catch (err) {
    if (!(err instanceof RenameError)) throw err;
    console.error(err.message);
    process.exit(2);
  }
  let code = 0;
  for (const script of ['tools/build-config.mjs', 'tools/naming.mjs']) {
    code ||= run(process.execPath, script.endsWith('naming.mjs') ? [script, 'build'] : [script], { shell: false });
  }
  if (code) process.exit(1);
  console.log(
    '\ninit: done. Next: review `git status`; restart the environment — npm run env:stop && npm run env:start ' +
      '(the theme path and seed mapping changed; env:start regenerates .wp-env.override.json); then npm run gate:0.',
  );
}

if (isMain(import.meta.url)) main();
