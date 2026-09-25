/**
 * navigation: header menus (primary on desktop, mobile drawer), dropdown keyboard + ARIA, skip link.
 * Projects: navigation-desktop (@desktop, Desktop Chrome) and navigation-mobile (@mobile, Pixel 5).
 * Selectors follow the theme's header (template-parts/site-header.php): .nav, .burger, .menu.
 */
import { expect, test, type APIRequestContext, type Page } from '@playwright/test';
import { allPages, baseOrigin } from './helpers/config';

const start = allPages.find((p) => p.type === 'front') ?? allPages[0];

/** Same-origin hrefs of the links matched by `selector` (hash stripped, de-duplicated). */
async function sameOriginLinks(page: Page, selector: string): Promise<string[]> {
  const hrefs = await page.locator(selector).evaluateAll((els) => els.map((a) => (a as HTMLAnchorElement).href));
  const urls = hrefs
    .map((h) => new URL(h))
    .filter((u) => u.origin === baseOrigin)
    .map((u) => `${u.pathname}${u.search}`);
  return [...new Set(urls)];
}

async function expectAll200(request: APIRequestContext, urls: string[]): Promise<void> {
  test.slow();
  for (const url of urls) {
    const res = await request.get(url, { maxRedirects: 0 });
    expect(res.status(), `menu link ${url}`).toBe(200);
  }
}

test.describe('navigation', { tag: '@navigation' }, () => {
  test.skip(!start, 'pages-map.json has no pages yet');

  test.beforeEach(async ({ page }) => {
    await page.goto(start.url, { waitUntil: 'load' });
  });

  test.describe('desktop', { tag: '@desktop' }, () => {
    test('primary menu is visible and every link resolves (200)', async ({ page, request }) => {
      const nav = page.locator('.header__nav');
      await expect(nav).toBeVisible();
      const links = await sameOriginLinks(page, '.header__nav a[href]');
      expect(links.length, 'primary menu links').toBeGreaterThan(0);
      await expectAll200(request, links);
    });

    test('dropdown: keyboard toggle keeps aria-expanded in sync, Escape closes', async ({ page }) => {
      const item = page.locator('.header__nav .nav__item--has-sub').first();
      test.skip((await item.count()) === 0, 'primary menu has no submenu');
      const toggle = item.locator('.nav__toggle');
      const sub = page.locator(`#${await toggle.getAttribute('aria-controls')}`);

      await expect(toggle).toHaveAttribute('aria-expanded', 'false');
      await toggle.focus();
      await page.keyboard.press('Enter');
      await expect(toggle).toHaveAttribute('aria-expanded', 'true');
      await expect(sub).toBeVisible();

      await page.keyboard.press('Escape');
      await expect(toggle).toHaveAttribute('aria-expanded', 'false');
      await expect(sub).toBeHidden();
    });

    test('burger is hidden on desktop', async ({ page }) => {
      await expect(page.locator('.burger')).toBeHidden();
    });

    test('skip link is the first tab stop and moves focus into main', async ({ page }) => {
      await page.keyboard.press('Tab');
      const skip = page.locator('.skip-link');
      await expect(skip).toBeFocused();
      await expect(skip).toBeVisible();
      const target = ((await skip.getAttribute('href')) ?? '').replace(/^.*#/, '');
      expect(target, 'skip link target id').not.toBe('');

      await page.keyboard.press('Enter');
      await expect(page).toHaveURL(new RegExp(`#${target}$`));
      await page.keyboard.press('Tab');
      const inside = await page.evaluate((id) => {
        const main = document.getElementById(id);
        return !!main && main.contains(document.activeElement) && document.activeElement !== document.body;
      }, target);
      expect(inside, `next tab stop after the skip link is inside #${target}`).toBe(true);
    });
  });

  test.describe('mobile', { tag: '@mobile' }, () => {
    test('drawer opens with focus inside and closes with Escape (focus returns to the burger)', async ({ page }) => {
      const burger = page.locator('.burger');
      await expect(burger).toBeVisible();
      await expect(page.locator('.header__nav')).toBeHidden();
      const menu = page.locator(`#${await burger.getAttribute('aria-controls')}`);

      await expect(burger).toHaveAttribute('aria-expanded', 'false');
      await burger.click();
      await expect(burger).toHaveAttribute('aria-expanded', 'true');
      await expect(menu).toHaveClass(/\bis-open\b/);
      await expect.poll(() => menu.evaluate((m) => m.contains(document.activeElement))).toBe(true);

      await page.keyboard.press('Escape');
      await expect(burger).toHaveAttribute('aria-expanded', 'false');
      await expect(menu).not.toHaveClass(/\bis-open\b/);
      await expect(burger).toBeFocused();
    });

    test('drawer submenu toggles aria-expanded', async ({ page }) => {
      const burger = page.locator('.burger');
      const menu = page.locator(`#${await burger.getAttribute('aria-controls')}`);
      const btn = menu.locator('.menu__link--btn').first();
      test.skip((await btn.count()) === 0, 'mobile menu has no submenu');

      await burger.click();
      await expect(btn).toHaveAttribute('aria-expanded', 'false');
      await btn.click();
      await expect(btn).toHaveAttribute('aria-expanded', 'true');
      await expect(page.locator(`#${await btn.getAttribute('aria-controls')}`)).toBeVisible();
      await btn.click();
      await expect(btn).toHaveAttribute('aria-expanded', 'false');
    });

    test('every drawer link resolves (200)', async ({ page, request }) => {
      const burger = page.locator('.burger');
      const menuId = await burger.getAttribute('aria-controls');
      const links = await sameOriginLinks(page, `#${menuId} a[href]`);
      expect(links.length, 'mobile menu links').toBeGreaterThan(0);
      await expectAll200(request, links);
    });
  });
});
