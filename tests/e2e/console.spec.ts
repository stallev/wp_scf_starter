/**
 * console (K9): every pages-map URL loads in Chromium without console errors, uncaught page
 * errors, 4xx/5xx or failed same-origin requests (e.g. prototype `fetch('data/*.json')` → 404).
 * The page is scrolled to the bottom so lazy images and below-the-fold features load too.
 */
import { expect, test } from '@playwright/test';
import { baseOrigin, pagesUnderTest } from './helpers/config';

const sameOrigin = (url: string) => {
  try {
    return new URL(url).origin === baseOrigin;
  } catch {
    return false;
  }
};

test.describe('console', { tag: '@console' }, () => {
  for (const entry of pagesUnderTest()) {
    test(`${entry.url}: no console errors, page errors or failed same-origin requests`, async ({ page }) => {
      const problems: string[] = [];
      page.on('console', (msg) => {
        if (msg.type() === 'error') problems.push(`console.error: ${msg.text()} (${msg.location().url})`);
      });
      page.on('pageerror', (err) => problems.push(`pageerror: ${err.message}`));
      page.on('response', (res) => {
        if (res.status() >= 400 && sameOrigin(res.url())) problems.push(`HTTP ${res.status()}: ${res.url()}`);
      });
      page.on('requestfailed', (req) => {
        const failure = req.failure()?.errorText ?? '';
        if (sameOrigin(req.url()) && !/ERR_ABORTED/.test(failure)) problems.push(`request failed (${failure}): ${req.url()}`);
      });

      await page.goto(entry.url, { waitUntil: 'load' });
      await page.evaluate(async () => {
        for (let y = 0; y < document.body.scrollHeight; y += window.innerHeight / 2) {
          window.scrollTo(0, y);
          await new Promise((r) => setTimeout(r, 60));
        }
      });
      await page.waitForLoadState('networkidle');
      expect(problems).toEqual([]);
    });
  }
});
