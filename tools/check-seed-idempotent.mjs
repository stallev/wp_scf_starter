/**
 * Seed idempotency (phase 3 gate): run `wp {prefix} seed` twice in the running wp-env and require
 * the second run to change nothing (created:0 updated:0 errors:0 for every target).
 *
 * Goes through tools/wp-env.mjs (docker exec into the running cli container, D20). The first run
 * brings the database to the seed state; it may create/update records.
 *
 * Usage: node tools/check-seed-idempotent.mjs   (part of npm run gate:3)
 * Exit: 0 idempotent, 1 second run changed something or seed failed, 2 environment not running.
 */
import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { ROOT, hasCommand, isMain, loadConfig, report } from './lib.mjs';

const LINE = /^(\S+)\s+created:(\d+)\s+updated:(\d+)\s+unchanged:(\d+)\s+skipped:(\d+)\s+errors:(\d+)/;

/** Parse starter_seed_format_report() lines → { target: { created, updated, unchanged, skipped, errors } }. */
export function parseSeedReport(output) {
  const out = {};
  for (const line of String(output).split(/\r?\n/)) {
    const m = LINE.exec(line.trim());
    if (m) out[m[1]] = { created: +m[2], updated: +m[3], unchanged: +m[4], skipped: +m[5], errors: +m[6] };
  }
  return out;
}

/** Problems that make a second run non-idempotent. */
export function nonIdempotent(stats) {
  const problems = [];
  if (!Object.keys(stats).length) problems.push('no seed report lines in the output');
  for (const [target, s] of Object.entries(stats)) {
    if (s.created || s.updated || s.errors) {
      problems.push(`${target}: created:${s.created} updated:${s.updated} errors:${s.errors} on the second run → the importer rewrites unchanged data`);
    }
  }
  return problems;
}

function seed(prefix) {
  const res = spawnSync(process.execPath, [path.join('tools', 'wp-env.mjs'), 'run', 'cli', 'wp', prefix, 'seed'], {
    cwd: ROOT,
    encoding: 'utf8',
    maxBuffer: 16 * 1024 * 1024,
  });
  return { status: res.status ?? 1, output: `${res.stdout ?? ''}${res.stderr ?? ''}` };
}

function main() {
  if (!hasCommand('docker')) {
    console.error('check-seed-idempotent: docker not found — start the environment (npm run env:start)');
    process.exit(2);
  }
  const { prefix } = loadConfig().slug;
  const first = seed(prefix);
  if (first.status !== 0) {
    console.error(first.output.trim());
    const down = /not running|cannot connect|no such container|error during connect|docker daemon/i.test(first.output);
    if (down) {
      console.error('\ncheck-seed-idempotent: wp-env is not running → npm run env:start');
      process.exit(2);
    }
    report('check-seed-idempotent', [`first \`wp ${prefix} seed\` failed (exit ${first.status})`]);
  }
  const second = seed(prefix);
  if (second.status !== 0) {
    console.error(second.output.trim());
    report('check-seed-idempotent', [`second \`wp ${prefix} seed\` failed (exit ${second.status})`]);
  }
  const stats = parseSeedReport(second.output);
  if (nonIdempotent(stats).length) console.error(second.output.trim());
  report('check-seed-idempotent', nonIdempotent(stats), `ok (second run: ${Object.entries(stats).map(([t, s]) => `${t} unchanged:${s.unchanged}`).join(', ')})`);
}

if (isMain(import.meta.url)) main();
