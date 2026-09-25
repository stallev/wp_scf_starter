/**
 * Generate wp-content/mu-plugins/{core}/config.generated.php from project.config.json + pages-map.json.
 *
 * The repo root is not deployed (only wp-content is), so the mu-plugin reads a PHP snapshot of the
 * parts of the config it needs. The file is committed; `npm run check:config` fails when it is stale.
 *
 * Usage: node tools/build-config.mjs            write the file
 *        import { renderConfigPhp, generatedConfigPath } from './build-config.mjs'   (validator)
 * Output follows WPCS (long arrays, tabs, aligned arrows) so `npm run lint:php` stays green.
 */
import { writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { ROOT, readJson } from './lib.mjs';

/** Repo-relative path of the generated file. */
export function generatedConfigPath(cfg) {
  return `wp-content/mu-plugins/${cfg.slug.core}/config.generated.php`;
}

/** Subset of the config that PHP needs at runtime. */
export function buildRuntimeConfig(cfg, map) {
  return {
    project: { name: cfg.project.name, description: cfg.project.description ?? '' },
    slug: cfg.slug,
    locale: cfg.locale,
    urls: cfg.urls,
    fonts: cfg.fonts,
    images: cfg.images,
    analytics: cfg.analytics,
    modules: cfg.modules,
    pages: (map.pages ?? []).map((p) => ({
      url: p.url,
      title: p.title,
      template: p.template,
      type: p.type,
      noindex: p.noindex,
      lead_form: p.lead_form,
      schema: p.schema ?? [],
    })),
  };
}

function phpString(s) {
  return `'${String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}

function phpScalar(v) {
  if (v === null || v === undefined) return 'null';
  if (typeof v === 'boolean') return v ? 'true' : 'false';
  if (typeof v === 'number') return Number.isInteger(v) ? String(v) : String(v);
  return phpString(v);
}

function phpValue(v, depth) {
  if (Array.isArray(v)) return phpList(v, depth);
  if (v && typeof v === 'object') return phpAssoc(v, depth);
  return phpScalar(v);
}

function phpList(list, depth) {
  if (!list.length) return 'array()';
  const pad = '\t'.repeat(depth + 1);
  const items = list.map((v) => `${pad}${phpValue(v, depth + 1)},`);
  return `array(\n${items.join('\n')}\n${'\t'.repeat(depth)})`;
}

function phpAssoc(obj, depth) {
  const keys = Object.keys(obj);
  if (!keys.length) return 'array()';
  const pad = '\t'.repeat(depth + 1);
  const width = Math.max(...keys.map((k) => phpString(k).length));
  const items = keys.map((k) => {
    const key = phpString(k);
    return `${pad}${key}${' '.repeat(width - key.length)} => ${phpValue(obj[k], depth + 1)},`;
  });
  return `array(\n${items.join('\n')}\n${'\t'.repeat(depth)})`;
}

/** Full PHP source of config.generated.php. */
export function renderConfigPhp(cfg, map) {
  const data = buildRuntimeConfig(cfg, map);
  return [
    '<?php',
    '/**',
    ' * Runtime config snapshot — generated, do not edit.',
    ' *',
    ` * Source: project.config.json + ${cfg.paths.pages_map}. Regenerate: npm run build:config.`,
    ' * Read via starter_core_config().',
    ' *',
    ' * @package Starter_Core',
    ' */',
    '',
    "defined( 'ABSPATH' ) || exit;",
    '',
    `return ${phpValue(data, 0)};`,
    '',
  ].join('\n');
}

// CLI entry.
if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const cfg = readJson('project.config.json');
  const map = readJson(cfg.paths.pages_map);
  const rel = generatedConfigPath(cfg);
  writeFileSync(path.join(ROOT, rel), renderConfigPhp(cfg, map));
  console.log(`build-config: wrote ${rel} (${(map.pages ?? []).length} page(s))`);
}
