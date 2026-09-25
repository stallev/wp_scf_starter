/**
 * Relative links in agent-facing docs must resolve to existing repo files or directories.
 *
 * Scope: docs/**, AGENTS.md, CLAUDE.md, README.md, seed/README.md, .cursor/rules/**, .claude/** (*.md, *.mdc).
 * Checked: inline links/images [text](target) (balanced parentheses allowed), reference definitions
 * [id]: target, CLAUDE.md imports @path.
 * Resolution is case-exact against the repo file list (tracked + untracked, not ignored), so the
 * result is the same on Windows and Linux; backslashes in targets are rejected.
 * Skipped: external schemes, pure #anchors, fenced code (``` / ~~~ of any length), indented code
 * blocks, inline code. Anchors and queries are stripped; anchor targets are not verified.
 * Exit: 0 ok, 1 broken links.
 */
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { minimatch } from 'minimatch';
import { ROOT, isMain, listRepoFiles, report } from './lib.mjs';

const INCLUDE = ['docs/**', 'AGENTS.md', 'CLAUDE.md', 'README.md', 'seed/README.md', '.cursor/rules/**', '.claude/**'];
const EXCLUDE = ['example/**', '**/node_modules/**', '**/vendor/**'];
const EXT = /\.(md|mdc)$/i;

const mm = (file, glob) => minimatch(file, glob, { dot: true });
const LIST_ITEM = /^\s*(?:[-*+]|\d+[.)])\s/;

/**
 * Blank out code: fenced blocks (closing fence = same char, length >= opening), indented code blocks
 * (4+ spaces / tab after a blank line, outside lists) and inline code spans. Line count is kept.
 */
export function stripCode(text) {
  const out = [];
  let fence = null; // { ch, len }
  let prevBlank = true;
  let inList = false;
  let inIndented = false;
  for (const line of text.split(/\r?\n/)) {
    const f = /^ {0,3}(`{3,}|~{3,})/.exec(line);
    if (fence) {
      if (f && f[1][0] === fence.ch && f[1].length >= fence.len && /^ {0,3}[`~]+\s*$/.test(line)) fence = null;
      out.push('');
      continue;
    }
    if (f) {
      fence = { ch: f[1][0], len: f[1].length };
      out.push('');
      continue;
    }
    const blank = line.trim() === '';
    const indented = /^( {4,}|\t)/.test(line);
    if (!blank && !indented) inList = LIST_ITEM.test(line);
    if (indented && !inList && (prevBlank || inIndented)) {
      inIndented = true;
      out.push('');
      prevBlank = false;
      continue;
    }
    if (!blank) inIndented = false;
    prevBlank = blank;
    out.push(line.replace(/(`+)[\s\S]*?\1/g, ''));
  }
  return out;
}

/** Targets of [text](target) with balanced parentheses, stopping at whitespace (optional title). */
function inlineTargets(line) {
  const out = [];
  let i = line.indexOf('](');
  while (i !== -1) {
    let j = i + 2;
    while (line[j] === ' ') j++;
    let target = '';
    if (line[j] === '<') {
      const end = line.indexOf('>', j);
      if (end !== -1) target = line.slice(j + 1, end);
    } else {
      let depth = 0;
      for (; j < line.length; j++) {
        const ch = line[j];
        if (ch === '\\' && line[j + 1] === ')') {
          target += ')';
          j++;
          continue;
        }
        if (ch === '(') depth++;
        if (ch === ')') {
          if (depth === 0) break;
          depth--;
        }
        if (/\s/.test(ch)) break;
        target += ch;
      }
    }
    if (target) out.push(target);
    i = line.indexOf('](', i + 2);
  }
  return out;
}

function targetsOf(line, file) {
  const out = inlineTargets(line);
  const ref = /^\s{0,3}\[[^\]]+\]:\s*(<[^>]+>|\S+)/.exec(line);
  if (ref) out.push(ref[1].replace(/^<|>$/g, ''));
  if (path.basename(file) === 'CLAUDE.md') {
    const imp = /^\s*@(\S+)\s*$/.exec(line);
    if (imp) out.push(imp[1]);
  }
  return out;
}

/** Existence index with exact case: repo files plus every parent directory. */
export function buildIndex(files) {
  const set = new Set();
  for (const f of files) {
    set.add(f);
    let d = path.posix.dirname(f);
    while (d !== '.' && !set.has(d)) {
      set.add(d);
      d = path.posix.dirname(d);
    }
  }
  return set;
}

/** Returns { skip } | { error } | { rel } for a target found in `file`. */
export function resolveTarget(file, target) {
  if (/^[a-z][a-z0-9+.-]*:/i.test(target) || target.startsWith('//')) return { skip: true };
  if (target.includes('\\')) return { error: 'backslash in link target (use /)' };
  let clean;
  try {
    clean = decodeURI(target.split('#')[0].split('?')[0]);
  } catch {
    return { error: 'malformed link (bad percent-encoding)' };
  }
  if (!clean) return { skip: true };
  const rel = clean.startsWith('/') ? path.posix.normalize(clean.slice(1)) : path.posix.join(path.posix.dirname(file), clean);
  const norm = rel.replace(/\/+$/, '');
  if (norm === '' || norm === '.') return { rel: '.' };
  if (norm.startsWith('..')) return { error: 'points outside the repository' };
  return { rel: norm };
}

function main() {
  const repoFiles = listRepoFiles();
  const index = buildIndex(repoFiles);
  const errors = [];
  const files = repoFiles.filter((f) => EXT.test(f) && INCLUDE.some((g) => mm(f, g)) && !EXCLUDE.some((g) => mm(f, g)));
  let links = 0;
  for (const file of files) {
    stripCode(readFileSync(path.join(ROOT, file), 'utf8')).forEach((line, i) => {
      for (const target of targetsOf(line, file)) {
        const r = resolveTarget(file, target);
        if (r.skip) continue;
        links++;
        if (r.error) errors.push(`${file}:${i + 1}: ${r.error} → ${target}`);
        else if (r.rel !== '.' && !index.has(r.rel)) errors.push(`${file}:${i + 1}: broken link (exact case) → ${target}`);
      }
    });
  }
  report('check-links', errors, `ok (${links} relative links in ${files.length} files)`);
}

if (isMain(import.meta.url)) main();
