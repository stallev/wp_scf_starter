/**
 * Validate seed/*.json before `wp starter seed` sees it.
 *
 *   1. JSON Schema per file (seed/schema/<name>.schema.json, ajv strict) — the shape the mu-plugin
 *      seed code reads (starter-core/seed/entities/*.php).
 *   2. Stable slugs: unique per file; post slugs do not collide with top-level pages-map pages.
 *   3. Local images (image, default_image) exist inside the seed directory (no ../ escapes).
 *   4. References: menu `page` → pages-map URL, menu `post` → posts.json slug, service card `page`
 *      → pages-map URL (errors); project `service` / FAQ `location` → a pages-map page (warnings).
 *   5. No secrets (same key list as the PHP loader, plus secret-shaped values: bot tokens, API keys,
 *      long hex / random strings) and no forbidden names from naming.json.
 * A missing file is a warning: the seeder skips that target.
 *
 * Usage: node tools/validate-seeds.mjs   (npm run check:seeds)
 * Exit: 0 ok, 1 errors, 2 cannot read config / schemas.
 */
import Ajv from 'ajv';
import { existsSync, readFileSync, statSync } from 'node:fs';
import path from 'node:path';
import { ROOT, isMain, loadConfig, readJson, report } from './lib.mjs';
import { NAMING_JSON, forbiddenRulesFor } from './naming.mjs';

/** Seed files the core imports (file → schema name). */
export const SEED_FILES = ['company', 'faq', 'reviews', 'projects', 'posts', 'service-cards', 'menus'];

/** Mirrors starter_seed_forbidden_keys() in starter-core/seed/loader.php. */
export const SECRET_KEYS = ['token', 'bot_token', 'password', 'pass', 'secret', 'api_key', 'apikey', 'chat_id', 'private_key'];

/**
 * Secret-shaped values under innocent keys. Whole-value matches only, so text, URLs and slugs pass:
 * slugs are lower-case (the mixed-case rule needs upper + lower + digit).
 */
export const SECRET_VALUES = [
  { id: 'telegram bot token', re: /^\d{6,}:[A-Za-z0-9_-]{30,}$/ },
  { id: 'Google API key', re: /^AIza[0-9A-Za-z_-]{35}$/ },
  { id: 'vendor token prefix', re: /^(?:sk|pk|rk)_(?:live|test)_[A-Za-z0-9]{16,}$|^gh[pousr]_[A-Za-z0-9]{30,}$|^xox[abprs]-[A-Za-z0-9-]{10,}$/ },
  { id: 'long hex key', re: /^[a-f0-9]{32,}$/i },
  { id: 'long base64 / random key', re: /^(?=[^\s]*[A-Z])(?=[^\s]*[a-z])(?=[^\s]*\d)[A-Za-z0-9+/_=-]{32,}$/ },
];

/** Id of the secret shape a string value has, or null. */
export function secretShape(value) {
  const v = String(value).trim();
  return SECRET_VALUES.find((s) => s.re.test(v))?.id ?? null;
}

const isRemote = (s) => /^https?:\/\//i.test(s);
const lastSegment = (url) => url.replace(/\/+$/, '').split('/').pop();

function walk(value, at, visit) {
  visit(value, at);
  if (Array.isArray(value)) value.forEach((v, i) => walk(v, `${at}[${i}]`, visit));
  else if (value && typeof value === 'object') {
    for (const [k, v] of Object.entries(value)) {
      visit(k, `${at}.${k}`, true);
      walk(v, `${at}.${k}`, visit);
    }
  }
}

function menuItems(items, at, out = []) {
  (items ?? []).forEach((item, i) => {
    const here = `${at}[${i}]`;
    out.push({ item, at: here });
    menuItems(item?.children, `${here}.children`, out);
  });
  return out;
}

/**
 * Validate parsed seed data.
 * @param {{ data: Record<string, any>, schemas: Record<string, object>, pages: object[], naming?: object,
 *           seedDir?: string, imageExists?: (rel: string) => boolean }} input
 *   data: file name (without .json) → parsed JSON; missing keys are treated as missing files.
 * @returns {{ errors: string[], warnings: string[] }}
 */
export function validateSeeds({ data, schemas, pages, naming = null, seedDir = 'seed', imageExists }) {
  const errors = [];
  const warnings = [];
  const ajv = new Ajv({ allErrors: true, strict: true, allowUnionTypes: true });
  const pageUrls = new Set(pages.map((p) => p.url));
  const pageSlugs = new Set(pages.map((p) => (p.url === '/' ? 'home' : lastSegment(p.url))));
  // Pages the seeder creates as WP pages (starter_seed_import_pages); `post` entries are posts themselves.
  const PAGE_TYPES = ['front', 'page', 'service', 'blog-index', 'utility'];
  const topLevel = new Set(
    pages.filter((p) => PAGE_TYPES.includes(p.type) && p.url.split('/').filter(Boolean).length === 1).map((p) => lastSegment(p.url)),
  );
  const exists = imageExists ?? ((rel) => {
    const root = path.resolve(ROOT, seedDir);
    const full = path.resolve(root, rel);
    return full.startsWith(root + path.sep) && existsSync(full) && statSync(full).isFile();
  });

  for (const name of SEED_FILES) {
    const file = `${seedDir}/${name}.json`;
    const json = data[name];
    if (json === undefined) {
      warnings.push(`${file}: missing — target skipped by the seeder`);
      continue;
    }

    // 5. Secrets and forbidden names — independent of the shape, so checked first.
    const rules = naming ? forbiddenRulesFor(naming, file) : [];
    walk(json, name, (value, at, isKey) => {
      if (isKey && SECRET_KEYS.includes(String(value).toLowerCase())) {
        errors.push(`${file}: secret-like key "${value}" at ${at} → secrets live in .env / protected options, never in seed`);
      }
      if (typeof value !== 'string') return;
      const shape = isKey ? null : secretShape(value);
      if (shape) errors.push(`${file}: value at ${at} looks like a ${shape} → secrets live in .env / protected options, never in seed`);
      for (const r of rules) {
        const m = r.re.exec(value);
        if (m) errors.push(`${file}: forbidden "${m[0]}" [${r.id}] at ${at} — ${r.reason}; use: ${r.replace}`);
      }
    });

    // 1. Schema.
    const check = ajv.compile(schemas[name]);
    if (!check(json)) {
      for (const e of check.errors) errors.push(`${file}${e.instancePath || ''}: ${e.message}${e.params?.additionalProperty ? ` (${e.params.additionalProperty})` : ''}`);
      continue; // Cross-checks below assume a valid shape.
    }

    // 2. Unique slugs.
    const seen = new Map();
    (json.items ?? []).forEach((item, i) => {
      if (!item.slug) return;
      if (seen.has(item.slug)) errors.push(`${file}: items[${i}].slug "${item.slug}" duplicates items[${seen.get(item.slug)}] → slugs are record identities, keep them unique`);
      seen.set(item.slug, i);
    });
    if (name === 'service-cards') {
      const pagesSeen = new Map();
      json.items.forEach((item, i) => {
        if (pagesSeen.has(item.page)) errors.push(`${file}: items[${i}].page "${item.page}" duplicates items[${pagesSeen.get(item.page)}]`);
        pagesSeen.set(item.page, i);
      });
    }

    // 3. Local images.
    const images = [];
    if (name === 'company' && json.default_image) images.push(['default_image', json.default_image]);
    (json.items ?? []).forEach((item, i) => item.image && images.push([`items[${i}].image`, item.image]));
    for (const [at, img] of images) {
      if (!isRemote(img) && !exists(img)) errors.push(`${file}: ${at} "${img}" not found in ${seedDir}/ → add the file or fix the path (relative to ${seedDir}/)`);
    }

  }

  // 4. Cross references (only between files that passed the schema).
  const valid = (name) => data[name] !== undefined && ajv.getSchema(`${name}.schema.json`)?.(data[name]);
  const postSlugs = new Set(valid('posts') ? data.posts.items.map((p) => p.slug) : []);

  if (valid('posts')) {
    data.posts.items.forEach((p, i) => {
      if (topLevel.has(p.slug)) errors.push(`${seedDir}/posts.json: items[${i}].slug "${p.slug}" collides with the page /${p.slug}/ (permalink /%postname%/)`);
    });
  }
  if (valid('menus')) {
    data.menus.menus.forEach((menu, m) => {
      for (const { item, at } of menuItems(menu.items, `menus[${m}].items`)) {
        if (item.page && !pageUrls.has(item.page)) errors.push(`${seedDir}/menus.json: ${at}.page "${item.page}" is not in pages-map.json → add the page or use url`);
        if (item.post && !postSlugs.has(item.post)) errors.push(`${seedDir}/menus.json: ${at}.post "${item.post}" is not a slug in posts.json`);
      }
    });
  }
  if (valid('service-cards')) {
    data['service-cards'].items.forEach((c, i) => {
      if (!pageUrls.has(c.page)) errors.push(`${seedDir}/service-cards.json: items[${i}].page "${c.page}" is not in pages-map.json → the card would be skipped`);
    });
  }
  if (valid('projects')) {
    const services = new Set(pages.filter((p) => p.type === 'service').map((p) => lastSegment(p.url)));
    data.projects.items.forEach((p, i) => {
      if (p.service && !services.has(p.service)) warnings.push(`${seedDir}/projects.json: items[${i}].service "${p.service}" is not a service page slug in pages-map.json`);
    });
  }
  if (valid('faq')) {
    data.faq.items.forEach((f, i) => {
      if (f.location && !pageSlugs.has(f.location)) warnings.push(`${seedDir}/faq.json: items[${i}].location "${f.location}" matches no pages-map page ("home" = front page)`);
    });
  }

  return { errors, warnings };
}

/** Read seed files; unparsable JSON is reported as an error. */
export function readSeedDir(seedDir) {
  const data = {};
  const errors = [];
  for (const name of SEED_FILES) {
    const full = path.join(ROOT, seedDir, `${name}.json`);
    if (!existsSync(full)) continue;
    try {
      data[name] = JSON.parse(readFileSync(full, 'utf8'));
    } catch (err) {
      errors.push(`${seedDir}/${name}.json: invalid JSON (${err.message})`);
    }
  }
  return { data, errors };
}

export function loadSchemas(seedDir) {
  return Object.fromEntries(SEED_FILES.map((n) => [n, readJson(`${seedDir}/schema/${n}.schema.json`)]));
}

function main() {
  let cfg;
  let schemas;
  let pages;
  let naming;
  try {
    cfg = loadConfig();
    schemas = loadSchemas(cfg.paths.seed);
    pages = readJson(cfg.paths.pages_map).pages ?? [];
    naming = readJson(NAMING_JSON);
  } catch (err) {
    console.error(`validate-seeds: cannot read config / schemas (${err.message})`);
    process.exit(2);
  }
  const { data, errors: parseErrors } = readSeedDir(cfg.paths.seed);
  let res;
  try {
    res = validateSeeds({ data, schemas, pages, naming, seedDir: cfg.paths.seed });
  } catch (err) {
    console.error(`validate-seeds: schema error (${err.message}) → fix ${cfg.paths.seed}/schema/*.schema.json`);
    process.exit(2);
  }
  const { errors, warnings } = res;
  if (warnings.length) console.warn(warnings.map((w) => `  ! ${w}`).join('\n'));
  const counts = SEED_FILES.filter((n) => data[n]).map((n) => `${n}:${data[n].items?.length ?? data[n].menus?.length ?? 1}`);
  report('validate-seeds', [...parseErrors, ...errors], `ok (${counts.join(' ') || 'no seed files'})`);
}

if (isMain(import.meta.url)) main();
