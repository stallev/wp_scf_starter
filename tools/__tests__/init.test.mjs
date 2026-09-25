/**
 * tools/init.mjs in memory: no files are written. This file is excluded from init's own rewrite,
 * so the literal starter names below stay valid after a project is initialised.
 */
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { buildRules, planInit, resolveNames, transformPath, transformText, validateNames } from '../init.mjs';

const from = { prefix: 'starter', theme: 'starter', core: 'starter-core', text_domain: 'starter', name: 'Starter' };
const to = resolveNames({ prefix: 'acme', name: 'Acme Corp' });
const rules = buildRules(from, to);
const tx = (text, file = 'wp-content/themes/starter/x.php') => transformText(text, file, rules).text;

test('resolveNames defaults', () => {
  assert.deepEqual(to, { prefix: 'acme', theme: 'acme', core: 'acme-core', text_domain: 'acme', name: 'Acme Corp' });
});

test('PHP identifiers, hooks, meta, constants, classes', () => {
  assert.equal(tx('function starter_get_company() {'), 'function acme_get_company() {');
  assert.equal(tx("apply_filters( 'starter_company', $c )"), "apply_filters( 'acme_company', $c )");
  assert.equal(tx("'_starter_seed_source' 'group_starter_company' 'options_starter_company_x' 'wp_ajax_starter_x'"), "'_acme_seed_source' 'group_acme_company' 'options_acme_company_x' 'wp_ajax_acme_x'");
  assert.equal(tx("define( 'STARTER_CORE_VERSION', '1' ); window.STARTER_LEAD"), "define( 'ACME_CORE_VERSION', '1' ); window.ACME_LEAD");
  assert.equal(tx('class Starter_CLI_Command {} @package Starter_Core'), 'class Acme_CLI_Command {} @package Acme_Core');
  assert.equal(tx(' * @package Starter'), ' * @package Acme');
  assert.equal(tx("WP_CLI::add_command( 'starter', 'Starter_CLI_Command' );"), "WP_CLI::add_command( 'acme', 'Acme_CLI_Command' );");
});

test('text domain, handles, CSS classes, JS events and globals', () => {
  assert.equal(tx("__( 'Имя', 'starter' ) load_theme_textdomain( 'starter', $dir )"), "__( 'Имя', 'acme' ) load_theme_textdomain( 'acme', $dir )");
  assert.equal(tx("wp_enqueue_script( 'starter-main' ) .starter-faq-row__answer toplevel_page_starter-company"), "wp_enqueue_script( 'acme-main' ) .acme-faq-row__answer toplevel_page_acme-company");
  assert.equal(tx("new CustomEvent('starter:lead:success') window.starterLeadAdmin"), "new CustomEvent('acme:lead:success') window.acmeLeadAdmin");
  assert.equal(tx("require STARTER_CORE_PATH . '/class-starter-cli-command.php';"), "require ACME_CORE_PATH . '/class-acme-cli-command.php';");
});

test('paths, config values, headers, CLI mentions', () => {
  assert.equal(tx('"themes": ["./wp-content/themes/starter"], "wp-content/starter-seed": "./seed"', '.wp-env.json'), '"themes": ["./wp-content/themes/acme"], "wp-content/acme-seed": "./seed"');
  assert.equal(tx('wp-content/mu-plugins/starter-core.php and starter-core/boot.php', 'AGENTS.md'), 'wp-content/mu-plugins/acme-core.php and acme-core/boot.php');
  assert.equal(tx('"prefix": "starter", "theme": "starter", "core": "starter-core", "text_domain": "starter"', 'project.config.json'), '"prefix": "acme", "theme": "acme", "core": "acme-core", "text_domain": "acme"');
  assert.equal(tx('"name": "Starter",', 'project.config.json'), '"name": "Acme Corp",');
  assert.equal(tx('"PREFIX": "STARTER"', 'docs/contracts/naming.json'), '"PREFIX": "ACME"');
  assert.equal(tx('Theme Name: Starter\nText Domain: starter', 'wp-content/themes/starter/style.css'), 'Theme Name: Acme Corp\nText Domain: acme');
  assert.equal(tx(' * Plugin Name: Starter Core', 'wp-content/mu-plugins/starter-core.php'), ' * Plugin Name: Acme Corp Core');
  const phpcs = '<property name="prefixes" type="array">\n\t<element value="starter"/>\n</property>\n<property name="text_domain" type="array">\n\t<element value="starter"/>';
  assert.equal(tx(phpcs, 'phpcs.xml.dist'), phpcs.replace(/"starter"/g, '"acme"'));
  assert.equal(tx('npm run wp -- starter seed; `wp starter seed --dry-run`', 'AGENTS.md'), 'npm run wp -- acme seed; `wp acme seed --dry-run`');
  assert.equal(tx('"pattern": "\\\\bstarter_(?:blog)\\\\b", "register_post_type\\\\(\\\\s*[\'\\"](?!starter_)"', 'docs/contracts/naming.json'), '"pattern": "\\\\bacme_(?:blog)\\\\b", "register_post_type\\\\(\\\\s*[\'\\"](?!acme_)"');
});

test('prose, product names and history markers are left alone', () => {
  for (const s of ['noise for the starter', '"name": "wp-scf-starter"', 'docs/STARTER-PLAN.md', 'стартер', 'mystarter_x', 'Starter theme in README']) {
    assert.equal(tx(s, 'README.md'), s);
  }
});

test('separate theme / text domain names map independently', () => {
  const names = resolveNames({ prefix: 'acme', theme: 'acme-theme', core: 'acme-data', 'text-domain': 'acme-td', name: 'Acme' });
  const r = buildRules(from, names);
  const t = (s, f = 'wp-content/x.php') => transformText(s, f, r).text;
  assert.equal(t("__( 'x', 'starter' ) wp-content/themes/starter/ starter-core/ starter_x"), "__( 'x', 'acme-td' ) wp-content/themes/acme-theme/ acme-data/ acme_x");
  assert.equal(transformPath('wp-content/mu-plugins/starter-core/seo/class-starter-schema-service.php', from, names), 'wp-content/mu-plugins/acme-data/seo/class-acme-schema-service.php');
});

test('transformPath renames theme dir, core loader/dir and class files', () => {
  assert.equal(transformPath('wp-content/themes/starter/inc/class-starter-walker-nav-primary.php', from, to), 'wp-content/themes/acme/inc/class-acme-walker-nav-primary.php');
  assert.equal(transformPath('wp-content/mu-plugins/starter-core.php', from, to), 'wp-content/mu-plugins/acme-core.php');
  assert.equal(transformPath('wp-content/mu-plugins/starter-core/boot.php', from, to), 'wp-content/mu-plugins/acme-core/boot.php');
  assert.equal(transformPath('tools/lib.mjs', from, to), 'tools/lib.mjs');
});

test('planInit: demo reset, skips, renames; --keep-demo keeps data', () => {
  const files = [
    { path: 'pages-map.json', text: '{"pages":[{"url":"/"}]}' },
    { path: 'seed/faq.json', text: '{"items":[{"slug":"a"}]}' },
    { path: 'seed/images/demo-1.png', text: null },
    { path: 'docs/decisions/0001-example-migration.md', text: 'starter_ history' },
    { path: 'package-lock.json', text: '"starter_"' },
    { path: 'wp-content/themes/starter/functions.php', text: 'starter_x();' },
  ];
  const plan = planInit({ files, from, to });
  assert.deepEqual(plan.edits.map((e) => e.path), ['wp-content/themes/starter/functions.php']);
  assert.equal(plan.edits[0].text, 'acme_x();');
  assert.deepEqual(plan.deletes, ['seed/images/demo-1.png']);
  assert.deepEqual(JSON.parse(plan.writes.find((w) => w.path === 'pages-map.json').text).pages, []);
  assert.deepEqual(JSON.parse(plan.writes.find((w) => w.path === 'seed/faq.json').text), { items: [] });
  assert.deepEqual(JSON.parse(plan.writes.find((w) => w.path === 'seed/company.json').text), { name: 'Acme Corp' });
  assert.deepEqual(plan.renames, [{ from: 'wp-content/themes/starter/functions.php', to: 'wp-content/themes/acme/functions.php' }]);

  const keep = planInit({ files, from, to, keepDemo: true });
  assert.deepEqual(keep.writes, []);
  assert.deepEqual(keep.deletes, []);
});

test('validateNames rejects bad and colliding names', () => {
  const legacy = { id: 'legacy-prefix', reason: 'legacy', re: /\b(?:t[b]|T[B])[_-][A-Za-z]/ };
  assert.deepEqual(validateNames(from, to, [legacy]), []);
  assert.match(validateNames(from, resolveNames({ prefix: 'Acme', name: 'A' })).join(), /--prefix "Acme"/);
  assert.match(validateNames(from, resolveNames({ prefix: 'wp', name: 'A' })).join(), /reserved/);
  assert.match(validateNames(from, resolveNames({ prefix: 'starterx', name: 'A' })).join(), /contains the placeholder/);
  assert.match(validateNames(from, resolveNames({ prefix: 'tb', name: 'A' }), [legacy]).join(), /forbidden rule legacy-prefix/);
});
