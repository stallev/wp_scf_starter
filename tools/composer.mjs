/**
 * Composer wrapper: local `composer` if installed, otherwise the official composer:2 Docker image.
 * Usage: npm run composer -- install | npm run lint:php
 */
import { ROOT, hasCommand, run } from './lib.mjs';

const args = process.argv.slice(2);

if (hasCommand('composer')) {
  process.exit(run('composer', args));
}

if (!hasCommand('docker')) {
  console.error('[composer] Neither composer nor docker found. Install one of them (see README).');
  process.exit(2);
}

// On Linux/macOS run as the host user so vendor/ and caches are not owned by root.
const user = typeof process.getuid === 'function'
  ? ['--user', `${process.getuid()}:${process.getgid()}`, '-e', 'COMPOSER_HOME=/tmp/composer']
  : [];

// MSYS_NO_PATHCONV stops Git Bash from rewriting /app into a Windows path.
const code = run(
  'docker',
  ['run', '--rm', ...user, '-v', `${ROOT}:/app`, '-w', '/app', 'composer:2', ...args],
  { env: { ...process.env, MSYS_NO_PATHCONV: '1' }, shell: false },
);
process.exit(code);
