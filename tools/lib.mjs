/**
 * Shared helpers for tools/*.mjs: repo root, config loading, process spawning.
 */
import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

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
