/**
 * static: every pages-map URL answers 200 with the expected lead-form count (K14), one <h1>,
 * a robots noindex meta where pages-map says noindex; unknown URLs answer 404.
 * Server HTML only (APIRequest) — also runs in the Firefox/WebKit smoke projects.
 */
import { expect, test } from '@playwright/test';
import { allPages, pagesUnderTest } from './helpers/config';
import { fetchHtml } from './helpers/html';

test.describe('static', { tag: '@static' }, () => {
  test('pages-map has pages', () => {
    test.skip(allPages.length === 0, 'pages-map.json has no pages yet (phase 1 fills it)');
    expect(allPages.length).toBeGreaterThan(0);
  });

  for (const entry of pagesUnderTest()) {
    test(`${entry.url} → 200, lead form ${entry.lead_form ? 'x1' : 'none'}, one h1`, async ({ request }) => {
      const { root } = await fetchHtml(request, entry.url);

      const forms = root.querySelectorAll('form.js-lead');
      expect(forms, `form.js-lead count on ${entry.url} (pages-map lead_form: ${entry.lead_form})`).toHaveLength(entry.lead_form ? 1 : 0);

      expect(root.querySelectorAll('h1'), `<h1> count on ${entry.url}`).toHaveLength(1);
    });

    test(`${entry.url} renders in the browser (h1 visible, no page errors)`, async ({ page }) => {
      const errors: string[] = [];
      page.on('pageerror', (err) => errors.push(err.message));
      await page.goto(entry.url, { waitUntil: 'load' });
      await expect(page.locator('h1')).toBeVisible();
      expect(errors, `uncaught errors on ${entry.url}`).toEqual([]);
    });

    if (entry.noindex) {
      test(`${entry.url} → robots noindex (pages-map noindex)`, async ({ request }) => {
        const { root } = await fetchHtml(request, entry.url);
        const robots = root.querySelectorAll('meta[name="robots"]').map((m) => m.getAttribute('content') ?? '');
        expect(robots.some((c) => /\bnoindex\b/i.test(c)), `robots meta on ${entry.url}: ${robots.join(' | ') || 'none'}`).toBe(true);
      });
    }
  }

  test('negative: unknown URL → 404', async ({ request }) => {
    const res = await request.get(`/e2e-missing-${Date.now().toString(36)}/`, { maxRedirects: 0 });
    expect(res.status()).toBe(404);
  });
});
