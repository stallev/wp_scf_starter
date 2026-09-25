/** Exit-code contract of the CLIs (0 ok / 1 violations / 2 cannot run), run as real processes. */
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { test } from 'node:test';
import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { PAGE_SUITES, PHASE_SUITES, normalizePageArg, playwrightArgs } from '../gate.mjs';
import { ROOT } from '../lib.mjs';

const cli = (...args) => spawnSync(process.execPath, args, { cwd: ROOT, encoding: 'utf8', env: { ...process.env, PAGESPEED_API_KEY: '' } });

test('lint:prototype: demo fixture 0, bad fixture 1, missing dir 2', () => {
  assert.equal(cli('tools/lint-prototype.mjs', '--dir=fixtures/demo-prototype').status, 0);
  const bad = cli('tools/lint-prototype.mjs', '--dir=fixtures/bad-prototype');
  assert.equal(bad.status, 1);
  assert.match(bad.stderr, /lint-prototype: \d+ error\(s\)/);
  assert.equal(cli('tools/lint-prototype.mjs', '--dir=fixtures/no-such-dir').status, 2);
});

test('psi: --help 0, localhost 2, missing key 2 — no network', () => {
  assert.equal(cli('tools/psi.mjs', '--help').status, 0);
  const local = cli('tools/psi.mjs', '--base=http://localhost:8888');
  assert.equal(local.status, 2);
  assert.match(local.stderr, /localhost/);
  const nokey = cli('tools/psi.mjs', '--base=https://example.org', '--paths=/');
  assert.equal(nokey.status, 2);
  assert.match(nokey.stderr, /PAGESPEED_API_KEY/);
});

test('init: usage without --prefix is exit 2; dry run writes nothing', () => {
  assert.equal(cli('tools/init.mjs').status, 2);
  const git = (...a) => spawnSync('git', a, { cwd: ROOT, encoding: 'utf8' }).stdout;
  const before = git('status', '--porcelain');
  const dry = cli('tools/init.mjs', '--prefix=acme', '--name=Acme', '--dry-run', '--force');
  assert.equal(dry.status, 0, dry.stderr);
  assert.match(dry.stdout, /dry run — nothing is written/);
  assert.equal(git('status', '--porcelain'), before);
});

test('gate:page argument normalisation (Git Bash path rewriting undone)', () => {
  assert.equal(normalizePageArg('/contacts/'), '/contacts/');
  assert.equal(normalizePageArg('contacts'), '/contacts/');
  assert.equal(normalizePageArg('services/demo'), '/services/demo/');
  assert.equal(normalizePageArg('home'), '/');
  assert.equal(normalizePageArg('C:/Program Files/Git/contacts/'), '/contacts/');
  assert.equal(normalizePageArg('C:/Program Files/Git/'), '/');
  assert.equal(cli('tools/gate.mjs', 'page', '/no-such-page/').status, 2);
  assert.equal(cli('tools/gate.mjs', 'e2e', '9').status, 2);
});

test('gate e2e: every suite has a spec file and a Playwright project; args are built per project', () => {
  const config = readFileSync(path.join(ROOT, 'playwright.config.ts'), 'utf8');
  const names = new Set([...PAGE_SUITES, ...Object.values(PHASE_SUITES).flatMap((s) => s.projects)]);
  for (const name of names) {
    const base = name.replace(/-\*$|-(desktop|mobile)$/, '');
    if (base === 'smoke') {
      assert.match(config, /smoke-firefox/);
      continue;
    }
    assert.ok(existsSync(path.join(ROOT, 'tests', 'e2e', `${base}.spec.ts`)), `tests/e2e/${base}.spec.ts`);
    assert.ok(config.includes(`suite('${base}'`), `project for ${base} in playwright.config.ts`);
  }
  assert.deepEqual(playwrightArgs({ projects: ['static', 'navigation-*'], grep: '@fonts' }), ['test', '--project=static', '--project=navigation-*', '--grep=@fonts']);
});
