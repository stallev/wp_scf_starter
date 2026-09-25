/**
 * Every source file is covered by at least one Cursor rule, and every rule is well-formed.
 *
 * Rules: .cursor/rules/*.mdc with frontmatter (the subset Cursor parses reliably):
 *   description: <text>                required
 *   globs: "a/**, b/*.php"             one line, comma-separated; required unless alwaysApply: true.
 *                                      Cursor splits on every comma, so {a,b} braces are rejected and
 *                                      YAML lists are reported as an unsupported format.
 *   alwaysApply: true|false            required (explicit)
 * and at most MAX_LINES lines (rules are pointers, not manuals).
 * Coverage: repo files (tracked + untracked, not ignored) under COVERED_DIRS plus root CONFIG_FILES,
 * except binaries.
 * Exit: 0 ok, 1 errors.
 */
import { readFileSync, readdirSync } from 'node:fs';
import path from 'node:path';
import { minimatch } from 'minimatch';
import { BINARY_EXT, CONFIG_FILES, ROOT, isMain, listRepoFiles, report } from './lib.mjs';

const RULES_DIR = '.cursor/rules';
const COVERED_DIRS = ['wp-content/', 'tools/', 'tests/', 'docs/', 'prototype/', 'seed/'];
const MAX_LINES = 60;

const mm = (file, glob) => minimatch(file, glob, { dot: true });

/** Split a globs value the way Cursor does (every comma). Returns { globs, error }. */
export function splitGlobs(value) {
  if (/\{[^}]*,[^}]*\}/.test(value)) {
    return { globs: [], error: `brace expression with a comma in globs (${value}): Cursor splits on every comma — list each glob separately` };
  }
  const globs = value
    .split(',')
    .map((g) => g.trim().replace(/^["']|["']$/g, '').trim())
    .filter(Boolean);
  return { globs, error: null };
}

/** Parse frontmatter. Returns null without frontmatter, otherwise { description, globs, alwaysApply, errors }. */
export function parseRule(text) {
  const m = /^---\r?\n([\s\S]*?)\r?\n---/.exec(text);
  if (!m) return null;
  const errors = [];
  const fm = {};
  const lines = m[1].split(/\r?\n/);
  lines.forEach((line, i) => {
    const kv = /^([A-Za-z]+):\s*(.*)$/.exec(line);
    if (!kv) return;
    fm[kv[1]] = kv[2].trim();
    if (kv[1] === 'globs' && (kv[2].trim() === '' || kv[2].trim().startsWith('[')) && /^\s*-\s/.test(lines[i + 1] ?? '')) {
      errors.push('globs: YAML list is an unsupported format — use one line: globs: "a/**, b/*.php"');
    } else if (kv[1] === 'globs' && kv[2].trim().startsWith('[')) {
      errors.push('globs: YAML/JSON array is an unsupported format — use one line: globs: "a/**, b/*.php"');
    }
  });
  const unquote = (s) => (s ?? '').replace(/^["']|["']$/g, '').trim();
  let globs = [];
  if (fm.globs && !errors.length) {
    const split = splitGlobs(unquote(fm.globs));
    if (split.error) errors.push(split.error);
    globs = split.globs;
  }
  if (!('alwaysApply' in fm)) errors.push('alwaysApply is required (true or false)');
  else if (!['true', 'false'].includes(fm.alwaysApply)) errors.push(`alwaysApply must be true or false, got "${fm.alwaysApply}"`);
  return { description: unquote(fm.description), globs, alwaysApply: fm.alwaysApply === 'true', errors };
}

function main() {
  const errors = [];
  const warnings = [];
  const rules = [];
  for (const name of readdirSync(path.join(ROOT, RULES_DIR)).filter((f) => f.endsWith('.mdc')).sort()) {
    const file = `${RULES_DIR}/${name}`;
    const text = readFileSync(path.join(ROOT, file), 'utf8');
    const rule = parseRule(text);
    if (!rule) {
      errors.push(`${file}: missing frontmatter (--- description / globs / alwaysApply ---)`);
      continue;
    }
    rule.errors.forEach((e) => errors.push(`${file}: ${e}`));
    if (!rule.description) errors.push(`${file}: description is required`);
    if (!rule.globs.length && !rule.alwaysApply && !rule.errors.length) errors.push(`${file}: globs are required unless alwaysApply: true`);
    const lines = text.replace(/\r?\n$/, '').split(/\r?\n/).length;
    if (lines > MAX_LINES) errors.push(`${file}: ${lines} lines > ${MAX_LINES} (rules are pointers; move HOW to docs/playbooks or a command)`);
    rules.push({ file, ...rule });
  }
  if (!rules.length) errors.push(`${RULES_DIR}: no .mdc rules found`);

  const files = listRepoFiles().filter(
    (f) => (COVERED_DIRS.some((d) => f.startsWith(d)) || CONFIG_FILES.some((g) => mm(f, g))) && !BINARY_EXT.test(f),
  );
  const always = rules.some((r) => r.alwaysApply);
  const hits = new Map(rules.map((r) => [r.file, 0]));
  for (const f of files) {
    const matching = rules.filter((r) => r.globs.some((g) => mm(f, g)));
    matching.forEach((r) => hits.set(r.file, hits.get(r.file) + 1));
    if (!matching.length && !always) errors.push(`${f}: not covered by any ${RULES_DIR}/*.mdc glob`);
  }
  for (const r of rules) if (r.globs.length && !hits.get(r.file)) warnings.push(`${r.file}: globs match no file yet (${r.globs.join(', ')})`);

  if (warnings.length) console.warn(warnings.map((w) => `  ! ${w}`).join('\n'));
  report('check-rules', errors, `ok (${rules.length} rules, ${files.length} files covered)`);
}

if (isMain(import.meta.url)) main();
