/**
 * Naming contract: docs/contracts/naming.json is the single source of canonical and forbidden names.
 *
 *   node tools/naming.mjs build   regenerate docs/contracts/naming-dictionary.md
 *   node tools/naming.mjs check   validate naming.json, verify the .md is fresh, grep sources for `forbidden`
 *
 * A source line that must quote a forbidden name (rare: e.g. a doc explaining a trap) can carry the
 * marker `naming:allow` — that single line is skipped.
 * Exit: 0 ok, 1 errors.
 */
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { minimatch } from 'minimatch';
import { BINARY_EXT, CONFIG_FILES, ROOT, isMain, listRepoFiles, readJson, report } from './lib.mjs';

export const NAMING_JSON = 'docs/contracts/naming.json';
export const NAMING_MD = 'docs/contracts/naming-dictionary.md';

/** Where forbidden names are searched (POSIX globs relative to the repo root). */
const SCAN_INCLUDE = [
  'wp-content/**',
  'tools/**',
  'seed/**',
  'docs/**',
  '.cursor/**',
  '.claude/**',
  'AGENTS.md',
  'CLAUDE.md',
  'README.md',
  ...CONFIG_FILES,
];
/**
 * History legitimately names the source project: ADRs and the starter plan (it cites the source
 * project's anti-examples). The contract itself lists the forbidden names.
 */
const SCAN_EXCLUDE = ['docs/decisions/**', 'docs/STARTER-PLAN.md', NAMING_JSON, NAMING_MD];
const ALLOW_MARKER = 'naming:allow';

/**
 * Canonical rows with `kind` are verified against wp-content PHP, so the dictionary cannot drift
 * from the code: every listed name must be declared there.
 */
const KINDS = ['function', 'constant', 'class', 'hook'];
const DECLARATION = {
  function: (n) => new RegExp(String.raw`\bfunction\s+${escapeRe(n)}\s*\(`),
  constant: (n) => new RegExp(String.raw`\bdefine\(\s*['"]${escapeRe(n)}['"]|\bconst\s+${escapeRe(n)}\b`),
  class: (n) => new RegExp(String.raw`\bclass\s+${escapeRe(n)}\b`),
  hook: (n) => new RegExp(String.raw`\b(?:apply_filters|do_action)\(\s*['"]${escapeRe(n)}['"]`),
};

/** Names of kind-tagged canonical rows that are not declared in PHP under wp-content/. */
export function findUndeclared(data, files = listRepoFiles()) {
  const code = files
    .filter((f) => mm(f, 'wp-content/**/*.php'))
    .map((f) => readFileSync(path.join(ROOT, f), 'utf8'))
    .join('\n');
  const errors = [];
  for (const row of data.canonical.filter((r) => r.kind)) {
    for (const raw of row.names) {
      const name = raw.replace(/\(\)$/, '');
      if (!DECLARATION[row.kind](name).test(code)) {
        errors.push(`${NAMING_JSON}: canonical ${row.kind} "${raw}" (${row.role}) is not declared in wp-content — fix the name or the code`);
      }
    }
  }
  return errors;
}

const mm = (file, glob) => minimatch(file, glob, { dot: true });
const escapeRe = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const isStr = (v) => typeof v === 'string' && v.trim() !== '';

/** Regex for a forbidden entry: literal `name` (not part of a longer identifier) or `pattern`. */
function entryRegex(entry) {
  return entry.name ? new RegExp(`(?<![\\w-])${escapeRe(entry.name)}(?![\\w-])`) : new RegExp(entry.pattern, entry.flags ?? '');
}

export function validateNaming(data) {
  const errors = [];
  if (!data || typeof data !== 'object') return ['root must be an object'];
  if (!isStr(data.version)) errors.push('version: non-empty string required');
  if (!/^\d{4}-\d{2}-\d{2}$/.test(data.updated ?? '')) errors.push('updated: YYYY-MM-DD required');

  if (!Array.isArray(data.canonical) || !data.canonical.length) errors.push('canonical: non-empty array required');
  (data.canonical ?? []).forEach((row, i) => {
    const at = `canonical[${i}]`;
    if (!isStr(row.role)) errors.push(`${at}.role: string required`);
    if (!Array.isArray(row.names) || !row.names.length || !row.names.every(isStr)) errors.push(`${at}.names: non-empty string[] required`);
    if (row.source !== undefined && (!isStr(row.source) || !existsSync(path.join(ROOT, row.source)))) {
      errors.push(`${at}.source: file not found: ${row.source}`);
    }
    if (row.note !== undefined && !isStr(row.note)) errors.push(`${at}.note: string expected`);
    if (row.kind !== undefined && !KINDS.includes(row.kind)) errors.push(`${at}.kind: one of ${KINDS.join(', ')}`);
  });

  if (!Array.isArray(data.forbidden) || !data.forbidden.length) errors.push('forbidden: non-empty array required');
  const ids = new Set();
  (data.forbidden ?? []).forEach((f, i) => {
    const at = `forbidden[${i}]${f.id ? ` (${f.id})` : ''}`;
    if (!isStr(f.id)) errors.push(`${at}.id: string required`);
    else if (ids.has(f.id)) errors.push(`${at}.id: duplicate`);
    ids.add(f.id);
    if (isStr(f.name) === isStr(f.pattern)) errors.push(`${at}: exactly one of name / pattern required`);
    if (f.pattern !== undefined) {
      try {
        new RegExp(f.pattern, f.flags ?? '');
      } catch (err) {
        errors.push(`${at}.pattern: invalid RegExp (${err.message})`);
      }
    }
    if (f.flags !== undefined && !/^[imsu]*$/.test(f.flags)) errors.push(`${at}.flags: only i, m, s, u allowed`);
    if (!isStr(f.reason)) errors.push(`${at}.reason: string required`);
    if (!isStr(f.replace)) errors.push(`${at}.replace: string required`);
    if (f.paths !== undefined && (!Array.isArray(f.paths) || !f.paths.length || !f.paths.every(isStr))) {
      errors.push(`${at}.paths: non-empty string[] expected`);
    }
  });

  if (!Array.isArray(data.legacy)) errors.push('legacy: array required');
  (data.legacy ?? []).forEach((l, i) => {
    if (!isStr(l.from) || !isStr(l.to)) errors.push(`legacy[${i}]: from / to strings required`);
  });

  // Canonical names must not trip the forbidden list.
  if (!errors.length) {
    for (const f of data.forbidden) {
      const re = entryRegex(f);
      for (const row of data.canonical) {
        for (const n of row.names) if (re.test(n)) errors.push(`canonical "${n}" matches forbidden ${f.id}`);
      }
    }
  }
  return errors;
}

const cell = (s) => String(s).replace(/\|/g, '\\|').replace(/\n/g, ' ');
const code = (s) => `\`${cell(s)}\``;

export function renderNamingMd(data) {
  const out = [
    '---',
    'status: canonical',
    `version: ${data.version}`,
    `updated: ${data.updated}`,
    '---',
    '',
    '<!-- GENERATED by tools/naming.mjs from naming.json — do not edit; run `npm run build:naming`. -->',
    '',
    '# Словарь имён (generated — do not edit)',
    '',
    'Источник — [`naming.json`](naming.json). Правка: `naming.json` → `npm run build:naming`; проверка — `npm run check:naming` (схема, свежесть этого файла, поиск запрещённых имён в коде и документации).',
    '',
    `Плейсхолдеры: префикс ${code(data.placeholders?.prefix ?? 'starter')}, mu-plugin ${code(data.placeholders?.core ?? 'starter-core')}, тема ${code(data.placeholders?.theme ?? 'starter')} — на проекте их один раз заменяет \`npm run init\`.`,
    '',
    '## Канон',
    '',
    '| Роль | Имена | Где | Примечание |',
    '|---|---|---|---|',
    ...data.canonical.map((r) => `| ${cell(r.role)} | ${r.names.map(code).join(', ')} | ${r.source ? code(r.source) : '—'} | ${r.note ? cell(r.note) : '—'} |`),
    '',
    '## Запрещено',
    '',
    '| ID | Имя / шаблон | Где ищется | Почему | Замена |',
    '|---|---|---|---|---|',
    ...data.forbidden.map((f) => {
      const what = f.name ? code(f.name) : `regex ${code(f.pattern)}`;
      const where = f.paths ? f.paths.map(code).join(', ') : 'все проверяемые файлы';
      return `| ${cell(f.id)} | ${what} | ${where} | ${cell(f.reason)} | ${cell(f.replace)} |`;
    }),
    '',
    '## Устаревшее → канон',
    '',
    '| Было | Стало |',
    '|---|---|',
    ...data.legacy.map((l) => `| ${cell(l.from)} | ${cell(l.to)} |`),
    '',
  ];
  return out.join('\n');
}

/**
 * Forbidden rules that apply to a repo-relative file (global rules + rules whose `paths` match),
 * as { id, reason, replace, re }. Shared with tools/validate-seeds.mjs.
 */
export function forbiddenRulesFor(data, file) {
  return data.forbidden
    .filter((f) => !f.paths || f.paths.some((g) => mm(file, g)))
    .map((f) => ({ ...f, re: entryRegex(f) }));
}

function scanSources(data) {
  const errors = [];
  const rules = data.forbidden.map((f) => ({ ...f, re: entryRegex(f) }));
  const files = listRepoFiles().filter(
    (f) => SCAN_INCLUDE.some((g) => mm(f, g)) && !SCAN_EXCLUDE.some((g) => mm(f, g)) && !BINARY_EXT.test(f),
  );
  for (const file of files) {
    const lines = readFileSync(path.join(ROOT, file), 'utf8').split(/\r?\n/);
    const active = rules.filter((r) => !r.paths || r.paths.some((g) => mm(file, g)));
    if (!active.length) continue;
    lines.forEach((line, i) => {
      if (line.includes(ALLOW_MARKER)) return;
      for (const r of active) {
        const m = r.re.exec(line);
        if (m) errors.push(`${file}:${i + 1}: forbidden "${m[0]}" [${r.id}] — ${r.reason}; use: ${r.replace}`);
      }
    });
  }
  return { errors, count: files.length };
}

function main(mode) {
  let data;
  try {
    data = readJson(NAMING_JSON);
  } catch (err) {
    report('naming', [`${NAMING_JSON}: cannot read/parse JSON (${err.message})`]);
  }
  const shapeErrors = validateNaming(data);
  if (shapeErrors.length) report('naming', shapeErrors.map((e) => `${NAMING_JSON}: ${e}`));

  const md = renderNamingMd(data);
  const mdPath = path.join(ROOT, NAMING_MD);

  if (mode === 'build') {
    writeFileSync(mdPath, md);
    console.log(`naming: wrote ${NAMING_MD}`);
    return;
  }
  if (mode !== 'check') {
    console.error('usage: node tools/naming.mjs build|check');
    process.exit(1);
  }

  const errors = [];
  const current = existsSync(mdPath) ? readFileSync(mdPath, 'utf8').replace(/\r\n/g, '\n') : null;
  if (current !== md) errors.push(`${NAMING_MD} is stale or missing — run \`npm run build:naming\``);
  errors.push(...findUndeclared(data));
  const scan = scanSources(data);
  errors.push(...scan.errors);
  report('check-naming', errors, `ok (${data.forbidden.length} forbidden rules, ${scan.count} files scanned)`);
}

if (isMain(import.meta.url)) main(process.argv[2]);
