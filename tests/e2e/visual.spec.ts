/**
 * visual: prototype vs WordPress screenshot diff for pages-map entries with a `prototype` file.
 * Prototype files are served by the static server of global-setup.ts from VISUAL_PROTOTYPE_DIR or
 * project.config.json paths.prototype (/prototype/). Threshold: VISUAL_MAX_DIFF (share of pixels,
 * default 0.05). Entries with prototype null are skipped with an annotation.
 *
 * Self-check (@self-check, no WordPress needed): a fixture page equals itself, and a visibly
 * modified copy of it exceeds the threshold — proves the diff can fail.
 */
import { existsSync } from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';
import { ROOT, config, pagesUnderTest } from './helpers/config';
import { attachDiff, diffPngs, maxDiffRatio, stableScreenshot } from './helpers/visual';

const staticOrigin = () => process.env.E2E_STATIC_ORIGIN ?? '';
const prototypeDir = path.resolve(ROOT, process.env.VISUAL_PROTOTYPE_DIR || config.paths.prototype);
const FIXTURE_PAGE = 'index.html';

test.describe('visual', { tag: '@visual' }, () => {
  for (const entry of pagesUnderTest()) {
    test(`${entry.url}: WordPress matches prototype ${entry.prototype ?? '(none)'}`, async ({ page }, testInfo) => {
      test.skip(entry.prototype === null, 'pages-map prototype is null — nothing to compare');
      test.skip(!existsSync(path.join(prototypeDir, entry.prototype!)), `prototype file missing: ${path.relative(ROOT, path.join(prototypeDir, entry.prototype!))}`);

      const proto = await stableScreenshot(page, `${staticOrigin()}/prototype/${entry.prototype}`);
      const wp = await stableScreenshot(page, entry.url);
      const r = diffPngs(proto, wp);
      await attachDiff(testInfo, proto, wp, r, ['prototype', 'wordpress']);
      testInfo.annotations.push({ type: 'visual-diff', description: `${(r.ratio * 100).toFixed(2)}% of ${r.width}x${r.height}; heights ${r.heightA} vs ${r.heightB}` });
      expect(r.ratio, `diff ratio (max ${maxDiffRatio}, VISUAL_MAX_DIFF)`).toBeLessThanOrEqual(maxDiffRatio);
    });
  }

  test.describe('self-check', { tag: '@self-check' }, () => {
    test.skip(!existsSync(path.join(ROOT, 'fixtures', 'demo-prototype', FIXTURE_PAGE)), 'fixtures/demo-prototype is absent');

    test('fixture page vs itself → no difference', async ({ page }, testInfo) => {
      const url = `${staticOrigin()}/fixture/${FIXTURE_PAGE}`;
      const a = await stableScreenshot(page, url);
      const b = await stableScreenshot(page, url);
      const r = diffPngs(a, b);
      await attachDiff(testInfo, a, b, r, ['fixture', 'fixture-again']);
      expect(r.ratio).toBeLessThanOrEqual(maxDiffRatio);
      expect(r.diffPixels).toBe(0);
    });

    test('fixture page vs a modified copy → difference above the threshold', async ({ page }, testInfo) => {
      const url = `${staticOrigin()}/fixture/${FIXTURE_PAGE}`;
      const a = await stableScreenshot(page, url);
      const b = await stableScreenshot(page, url, 'body { background: #d0021b !important; } main { transform: translateY(40px); }');
      const r = diffPngs(a, b);
      await attachDiff(testInfo, a, b, r, ['fixture', 'modified']);
      expect(r.ratio, 'modified copy must fail the visual threshold').toBeGreaterThan(maxDiffRatio);
    });
  });
});
