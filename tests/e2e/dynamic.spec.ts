/**
 * dynamic: CPT-driven blocks render from seed (FAQ, reviews, projects, service cards, blog), the
 * FAQ accordion works with JS, and every page is readable without JS (answers open, .reveal visible).
 * Expectations: pages-map (schema FAQPage → FAQ block, type front / blog-index / post) + seed/*.json.
 */
import { expect, test } from '@playwright/test';
import { allPages, pagesUnderTest, readSeed } from './helpers/config';

const seedCount = (file: string) => readSeed<{ items?: unknown[] }>(file)?.items?.length ?? 0;
const front = allPages.find((p) => p.type === 'front');
const faqPages = pagesUnderTest((p) => (p.schema ?? []).includes('FAQPage'));

test.describe('dynamic', { tag: '@dynamic' }, () => {
  for (const entry of faqPages) {
    test(`${entry.url}: FAQ accordion toggles aria-expanded (JS)`, async ({ page }) => {
      await page.goto(entry.url, { waitUntil: 'load' });
      const items = page.locator('.faq .faq__item');
      expect(await items.count(), 'FAQ items (pages-map schema has FAQPage)').toBeGreaterThan(0);
      await expect(page.locator('.faq').first()).toHaveClass(/\bis-enhanced\b/);

      const q = items.first().locator('.faq__q');
      const answer = page.locator(`#${await q.getAttribute('aria-controls')}`);
      await expect(q).toHaveAttribute('aria-expanded', 'false');
      await q.click();
      await expect(q).toHaveAttribute('aria-expanded', 'true');
      await expect(answer).toBeVisible();
      await q.click();
      await expect(q).toHaveAttribute('aria-expanded', 'false');
      await expect(answer).toBeHidden();
    });
  }

  test.describe('without JS', () => {
    test.use({ javaScriptEnabled: false });

    for (const entry of pagesUnderTest()) {
      test(`${entry.url}: content visible without JS (.reveal, FAQ answers)`, async ({ page }) => {
        await page.goto(entry.url, { waitUntil: 'load' });
        await expect(page.locator('h1')).toBeVisible();
        const hidden = await page.locator('.reveal').evaluateAll((els) =>
          els
            .filter((el) => {
              const s = getComputedStyle(el);
              return s.visibility === 'hidden' || s.display === 'none' || Number(s.opacity) < 1;
            })
            .map((el) => `${el.tagName.toLowerCase()}.${[...el.classList].join('.')}`),
        );
        expect(hidden, '.reveal blocks hidden without JS').toEqual([]);

        const answers = page.locator('.faq__a');
        for (let i = 0; i < (await answers.count()); i++) await expect(answers.nth(i)).toBeVisible();
      });
    }
  });

  test('front page renders seeded service cards, reviews and projects', async ({ page }) => {
    test.skip(!front || !pagesUnderTest().includes(front), 'no front page under test');
    await page.goto(front!.url, { waitUntil: 'load' });
    const blocks: [string, string, number][] = [
      ['service cards', '.service-card', seedCount('service-cards.json')],
      ['reviews', '.review', seedCount('reviews.json')],
      ['projects', '.folio__item', seedCount('projects.json')],
    ];
    for (const [label, selector, seeded] of blocks) {
      if (seeded === 0) {
        test.info().annotations.push({ type: 'skip-block', description: `${label}: seed is empty` });
        continue;
      }
      const n = await page.locator(selector).count();
      expect(n, `${label} (${selector}) rendered, seed has ${seeded}`).toBeGreaterThan(0);
      expect(n).toBeLessThanOrEqual(seeded);
    }
  });

  for (const entry of pagesUnderTest((p) => p.type === 'blog-index')) {
    test(`${entry.url}: blog index lists posts`, async ({ page }) => {
      const seeded = seedCount('posts.json');
      test.skip(seeded === 0, 'seed/posts.json is empty');
      await page.goto(entry.url, { waitUntil: 'load' });
      const cards = page.locator('.post-card');
      expect(await cards.count()).toBeGreaterThan(0);
      await expect(cards.first().locator('a[href]').first()).toBeVisible();
    });
  }

  for (const entry of pagesUnderTest((p) => p.type === 'post')) {
    test(`${entry.url}: post has a loaded cover and author data when set`, async ({ page }) => {
      await page.goto(entry.url, { waitUntil: 'load' });
      const cover = page.locator('.post-cover img');
      await expect(cover).toBeVisible();
      expect(await cover.evaluate((img) => (img as HTMLImageElement).complete && (img as HTMLImageElement).naturalWidth > 0), 'cover image loaded').toBe(true);

      const author = page.locator('.post-author');
      if ((await author.count()) === 0) {
        test.info().annotations.push({ type: 'no-author', description: 'post has no author data (post_author without a name) — author blocks not rendered' });
      } else {
        await expect(author.first().locator('.post-author__name')).not.toBeEmpty();
      }
    });
  }
});
