/** Pure helpers of font-fallback-metrics and check-seed-idempotent (no browser, no Docker). */
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { nonIdempotent, parseSeedReport } from '../check-seed-idempotent.mjs';
import { computeOverrides, groupByWeight, renderFontFace } from '../font-fallback-metrics.mjs';

test('computeOverrides: size-adjust = measured width ratio, vertical metrics scaled by it', () => {
  // Synthetic measurement: the web font is 1.3% wider than Arial on the sample text.
  const o = computeOverrides({ widthWeb: 1013, widthFallback: 1000, ascent: 1000, descent: -250, lineGap: 0, unitsPerEm: 1000 });
  assert.equal(o.sizeAdjust, 1.013);
  assert.ok(Math.abs(o.ascent - 1 / 1.013) < 1e-12);
  assert.ok(Math.abs(o.descent - 0.25 / 1.013) < 1e-12);
  assert.equal(o.lineGap, 0);
  assert.throws(() => computeOverrides({ widthWeb: 0, widthFallback: 1, ascent: 1, descent: 1, unitsPerEm: 1 }));
});

test('groupByWeight merges near-equal weights; renderFontFace prints a ready block', () => {
  const ov = (sizeAdjust) => ({ sizeAdjust, ascent: 0.9, descent: 0.2, lineGap: 0 });
  const groups = groupByWeight([
    { weight: 500, overrides: ov(1.0131) },
    { weight: 400, overrides: ov(1.013) },
    { weight: 700, overrides: ov(1.05) },
  ]);
  assert.deepEqual(groups.map((g) => g.weights), [[400, 500], [700]]);
  const css = renderFontFace({ family: 'Inter', fallback: 'Arial', groups });
  assert.match(css, /font-family: "Inter Fallback";\n {2}src: local\("Arial"\);\n {2}font-weight: 400 500;\n {2}size-adjust: 101\.30%;/);
  assert.match(css, /font-weight: 700;\n {2}size-adjust: 105\.00%;\n {2}ascent-override: 90\.00%;\n {2}descent-override: 20\.00%;\n {2}line-gap-override: 0\.00%;/);
  const single = renderFontFace({ family: 'Inter', fallback: 'Arial', groups: [groups[0]] });
  assert.doesNotMatch(single, /font-weight/);
});

test('seed report parsing and idempotency verdict', () => {
  const out = [
    'Seed directory: /var/www/html/wp-content/starter-seed',
    'company        created:0 updated:0 unchanged:1 skipped:0 errors:0',
    'pages          created:0 updated:0 unchanged:8 skipped:0 errors:0',
    'menus          created:0 updated:1 unchanged:2 skipped:0 errors:0',
    'Success: Seed completed.',
  ].join('\n');
  const stats = parseSeedReport(out);
  assert.deepEqual(Object.keys(stats), ['company', 'pages', 'menus']);
  assert.deepEqual(stats.pages, { created: 0, updated: 0, unchanged: 8, skipped: 0, errors: 0 });
  assert.equal(nonIdempotent(stats).length, 1);
  assert.match(nonIdempotent(stats)[0], /^menus: created:0 updated:1/);
  assert.deepEqual(nonIdempotent({}), ['no seed report lines in the output']);
});
