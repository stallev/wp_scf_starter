/**
 * Shared helpers for tools/*.mjs: repo root, config loading, process spawning.
 */
import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

export const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

export function readJson(relPath) {
  return JSON.parse(readFileSync(path.join(ROOT, relPath), 'utf8'));
}

export function loadConfig() {
  return readJson('project.config.json');
}

/**
 * Run a command, inherit stdio. Returns exit code.
 * shell:true is needed on Windows to resolve npx/.cmd shims.
 */
export function run(cmd, args, opts = {}) {
  const shell = opts.shell ?? process.platform === 'win32';
  // With a shell, cmd.exe would interpret ^ & | < > and split on spaces: quote such arguments.
  const argv = shell ? args.map((a) => (/[\s^&|<>()"]/.test(a) ? `"${a.replace(/"/g, '""')}"` : a)) : args;
  const res = spawnSync(cmd, argv, { stdio: 'inherit', cwd: ROOT, ...opts, shell });
  if (res.error) {
    console.error(`[tools] ${cmd}: ${res.error.message}`);
    return 1;
  }
  return res.status ?? 1;
}

export function hasCommand(cmd) {
  const probe = process.platform === 'win32' ? 'where' : 'which';
  return spawnSync(probe, [cmd], { stdio: 'ignore', shell: process.platform === 'win32' }).status === 0;
}

/**
 * Repo files as POSIX paths relative to ROOT: tracked + untracked but not ignored
 * (new files are checked before their first commit). Deleted-but-tracked files are skipped.
 */
export function listRepoFiles() {
  const res = spawnSync('git', ['ls-files', '--cached', '--others', '--exclude-standard', '-z'], {
    cwd: ROOT,
    encoding: 'utf8',
    maxBuffer: 64 * 1024 * 1024,
  });
  if (res.status !== 0) throw new Error(`git ls-files failed: ${res.stderr || res.error?.message}`);
  const files = [...new Set(res.stdout.split('\0').filter(Boolean))];
  return files.filter((f) => existsSync(path.join(ROOT, f)));
}

/** Extensions treated as binary by tools that read file contents. */
export const BINARY_EXT = /\.(png|jpe?g|gif|webp|avif|ico|woff2?|ttf|otf|eot|zip|gz|pdf|mp4|webm|mp3)$/i;

/** Print collected errors and exit 1, or print the ok line. */
export function report(tool, errors, okMessage) {
  if (errors.length) {
    console.error(errors.map((e) => `  ✗ ${e}`).join('\n'));
    console.error(`\n${tool}: ${errors.length} error(s)`);
    process.exit(1);
  }
  console.log(`${tool}: ${okMessage}`);
}

/**
 * Root config files (globs) edited by hand: covered by .cursor/rules/config.mdc and scanned by check:naming.
 * Lock files are generated and excluded.
 */
export const CONFIG_FILES = [
  'project.config.json',
  'pages-map.json',
  'schemas/**',
  '.wp-env.json',
  'composer.json',
  'package.json',
  'phpcs.xml.dist',
  'phpstan.neon.dist',
];

/** True when the module at `metaUrl` is the entry script (lets tools export helpers without side effects). */
export function isMain(metaUrl) {
  return Boolean(process.argv[1]) && metaUrl === pathToFileURL(path.resolve(process.argv[1])).href;
}
