/**
 * a11y: axe-core (WCAG 2.x A/AA) on every pages-map URL. Serious and critical violations fail;
 * moderate/minor ones are listed as test annotations. Reduced motion (project) shows .reveal blocks.
 */
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { pagesUnderTest } from './helpers/config';

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

test.describe('a11y', { tag: '@a11y' }, () => {
  for (const entry of pagesUnderTest()) {
    test(`${entry.url}: no serious/critical axe violations`, async ({ page }) => {
      await page.goto(entry.url, { waitUntil: 'load' });
      await page.evaluate(() => document.fonts.ready);
      const { violations } = await new AxeBuilder({ page }).withTags(TAGS).analyze();

      const describe = (v: (typeof violations)[number]) =>
        `${v.id} (${v.impact}): ${v.help} — ${v.nodes.slice(0, 3).map((n) => n.target.join(' ')).join(' | ')}`;
      for (const v of violations.filter((x) => x.impact !== 'serious' && x.impact !== 'critical')) {
        test.info().annotations.push({ type: `a11y-${v.impact ?? 'unknown'}`, description: describe(v) });
      }
      const blocking = violations.filter((v) => v.impact === 'serious' || v.impact === 'critical').map(describe);
      expect(blocking, `serious/critical axe violations on ${entry.url}`).toEqual([]);
    });
  }
});
