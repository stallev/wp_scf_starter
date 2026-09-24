/**
 * Validate project.config.json and pages-map.json against schemas/ + cross-field rules,
 * and check that versions/slugs declared elsewhere (style.css, phpcs, composer, wp-env) match the config.
 * Exit: 0 ok, 1 validation errors.
 */
import Ajv from 'ajv';
import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { ROOT, readJson } from './lib.mjs';

const ajv = new Ajv({ allErrors: true, strict: true, allowUnionTypes: true });
const errors = [];

function finish() {
  if (errors.length) {
    console.error(errors.map((e) => `  ✗ ${e}`).join('\n'));
    console.error(`\nvalidate-config: ${errors.length} error(s)`);
    process.exit(1);
  }
}

function load(file) {
  try {
    return readJson(file);
  } catch (err) {
    errors.push(`${file}: cannot read/parse JSON (${err.message})`);
    return null;
  }
}

function validate(file, schemaFile) {
  const data = load(file);
  const schema = load(schemaFile);
  if (!data || !schema) return data;
  const check = ajv.compile(schema);
  if (!check(data)) {
    for (const e of check.errors) errors.push(`${file}${e.instancePath || ''}: ${e.message}`);
  }
  return data;
}

// 1. Schemas. Cross-field rules below assume valid shapes, so stop here on any schema error.
const cfg = validate('project.config.json', 'schemas/project.config.schema.json');
finish();
const map = validate(cfg.paths.pages_map, 'schemas/pages-map.schema.json');
finish();

// 2. pages-map rules the schema cannot express.
const pages = map.pages;
const seen = new Set();
for (const p of pages) {
  if (seen.has(p.url)) errors.push(`pages-map: duplicate url ${p.url}`);
  seen.add(p.url);

  if (p.prototype && !existsSync(path.join(ROOT, cfg.paths.prototype, p.prototype))) {
    errors.push(`pages-map ${p.url}: prototype file not found: ${cfg.paths.prototype}/${p.prototype}`);
  }
  if (p.noindex && p.psi) errors.push(`pages-map ${p.url}: noindex pages must not be in the PSI suite`);
  if (p.noindex && p.lead_form) errors.push(`pages-map ${p.url}: noindex utility pages have no lead form`);
}
if (pages.length && !pages.some((p) => p.type === 'front')) errors.push('pages-map: no page with type "front"');

const psiByTemplate = new Map();
for (const p of pages.filter((x) => x.psi)) {
  if (psiByTemplate.has(p.template)) {
    errors.push(`pages-map: template ${p.template} has several PSI pages (${psiByTemplate.get(p.template)}, ${p.url}); keep one representative`);
  }
  psiByTemplate.set(p.template, p.url);
}

// 3. Values declared outside the config must match it.
function expectIn(file, re, expected, label) {
  const full = path.join(ROOT, file);
  if (!existsSync(full)) {
    errors.push(`${file}: missing (expected ${label} "${expected}")`);
    return;
  }
  const actual = re.exec(readFileSync(full, 'utf8'))?.[1];
  if (actual !== expected) errors.push(`${file}: ${label} is "${actual ?? 'missing'}", expected "${expected}" (project.config.json)`);
}

const themeCss = `wp-content/themes/${cfg.slug.theme}/style.css`;
expectIn(themeCss, /Requires at least:\s*(\S+)/, cfg.wordpress.min, 'Requires at least');
expectIn(themeCss, /Requires PHP:\s*(\S+)/, cfg.php.min, 'Requires PHP');
expectIn(themeCss, /Text Domain:\s*(\S+)/, cfg.slug.text_domain, 'Text Domain');
expectIn(`wp-content/mu-plugins/${cfg.slug.core}.php`, /Requires PHP:\s*(\S+)/, cfg.php.min, 'Requires PHP');
expectIn('phpcs.xml.dist', /name="minimum_wp_version" value="([^"]+)"/, cfg.wordpress.min, 'minimum_wp_version');
expectIn('phpcs.xml.dist', /name="testVersion" value="([^"]+)-"/, cfg.php.min, 'testVersion');
expectIn('phpcs.xml.dist', /name="prefixes"[^>]*>\s*<element value="([^"]+)"/, cfg.slug.prefix, 'PrefixAllGlobals prefix');
expectIn('phpcs.xml.dist', /name="text_domain"[^>]*>\s*<element value="([^"]+)"/, cfg.slug.text_domain, 'I18n text_domain');
expectIn('composer.json', /"php":\s*">=([^"]+)"/, cfg.php.min, 'require.php');
expectIn('composer.json', /"platform":\s*\{\s*"php":\s*"(\d+\.\d+)\.\d+"/, cfg.php.min, 'config.platform.php');

// Local environment may run a newer PHP than the minimum (it mirrors hosting), never an older one.
const wpEnv = load('.wp-env.json');
if (wpEnv) {
  const envPhp = String(wpEnv.phpVersion ?? '');
  const [emaj, emin] = envPhp.split('.').map(Number);
  const [mmaj, mmin] = cfg.php.min.split('.').map(Number);
  if (!envPhp || emaj < mmaj || (emaj === mmaj && emin < mmin)) {
    errors.push(`.wp-env.json: phpVersion "${envPhp}" is below php.min ${cfg.php.min}`);
  }
  if (!(wpEnv.themes ?? []).includes(`./wp-content/themes/${cfg.slug.theme}`)) {
    errors.push(`.wp-env.json: themes must include ./wp-content/themes/${cfg.slug.theme}`);
  }
}

finish();
console.log(`validate-config: ok (${pages.length} page(s) in pages-map)`);
