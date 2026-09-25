/**
 * perf-markup (P2, plan §2.4): performance rules on the final server HTML of every pages-map URL.
 * APIRequest + node-html-parser, no browser. Tags: @scripts, @fonts (gate:4 runs only those).
 *
 *  - every <script src> is defer / async / module (K2); no gtag.js in the HTML (GA4 is delayed);
 *  - at most one img[fetchpriority=high]; an image LCP (pages-map lcp.kind) is that one, eager;
 *  - every <img> has width, height and loading;
 *  - lcp.selector and above_fold blocks exist and are not in / do not contain .reveal (K1);
 *  - font preloads == project.config.json fonts.preload, each with crossorigin (K5);
 *  - no external origins in <head> (allowlist: E2E_HEAD_ORIGINS, comma-separated origins);
 *  - srcset: no non-WebP originals when WebP sub-sizes exist.
 */
import { expect, test, type APIRequestContext } from '@playwright/test';
import type { HTMLElement } from 'node-html-parser';
import { baseOrigin, config, names, pagesUnderTest, type PageEntry } from './helpers/config';
import { classes, fetchHtml, insideReveal, tagSnippet } from './helpers/html';

const HEAD_ORIGIN_ALLOW = new Set([baseOrigin, ...(process.env.E2E_HEAD_ORIGINS ?? '').split(',').map((s) => s.trim()).filter(Boolean)]);
/** <link rel> values that do not fetch anything. */
const NON_FETCH_RELS = new Set(['alternate', 'canonical', 'shortlink', 'edituri', 'https://api.w.org/', 'profile', 'author', 'next', 'prev', 'pingback']);

const cache = new Map<string, Promise<HTMLElement>>();
const load = (request: APIRequestContext, url: string) => {
  if (!cache.has(url)) cache.set(url, fetchHtml(request, url).then((r) => r.root));
  return cache.get(url)!;
};

const originOf = (href: string) => new URL(href, `${baseOrigin}/`).origin;

function checkPage(entry: PageEntry) {
  test.describe(entry.url, () => {
    test('scripts are defer/async, no gtag.js in HTML', { tag: '@scripts' }, async ({ request }) => {
      const root = await load(request, entry.url);
      const blocking = root
        .querySelectorAll('script[src]')
        .filter((s) => !s.hasAttribute('defer') && !s.hasAttribute('async') && s.getAttribute('type') !== 'module')
        .map(tagSnippet);
      expect(blocking, 'parser-blocking <script src> (give it strategy defer/async — K2)').toEqual([]);
      expect(root.toString(), 'gtag.js must be loaded by the delayed loader, not printed').not.toMatch(/googletagmanager\.com\/gtag\/js/);
    });

    test('font preloads match fonts.preload, with crossorigin', { tag: '@fonts' }, async ({ request }) => {
      const root = await load(request, entry.url);
      const preloads = root.querySelectorAll('link[rel="preload"][as="font"]');
      for (const l of preloads) expect(l.hasAttribute('crossorigin'), `crossorigin on ${tagSnippet(l)}`).toBe(true);
      const themeBase = `/wp-content/themes/${names.theme}/`;
      const got = preloads.map((l) => decodeURI(new URL(l.getAttribute('href') ?? '', `${baseOrigin}/`).pathname)).sort();
      const want = config.fonts.preload.map((f) => `${themeBase}${f.replace(/^\/+/, '')}`).sort();
      expect(got, 'preloaded fonts vs project.config.json fonts.preload').toEqual(want);
    });

    test('no external origins in <head>', { tag: '@scripts' }, async ({ request }) => {
      const root = await load(request, entry.url);
      const head = root.querySelector('head');
      expect(head, '<head>').not.toBeNull();
      const external: string[] = [];
      for (const s of head!.querySelectorAll('script[src]')) if (!HEAD_ORIGIN_ALLOW.has(originOf(s.getAttribute('src')!))) external.push(tagSnippet(s));
      for (const l of head!.querySelectorAll('link[href]')) {
        const rels = (l.getAttribute('rel') ?? '').toLowerCase().split(/\s+/);
        if (rels.every((r) => NON_FETCH_RELS.has(r))) continue;
        if (!HEAD_ORIGIN_ALLOW.has(originOf(l.getAttribute('href')!))) external.push(tagSnippet(l));
      }
      expect(external, 'external origins in <head> (self-host, or allow via E2E_HEAD_ORIGINS)').toEqual([]);
    });

    test('images: one high priority at most, LCP image eager, dimensions + loading, WebP srcset', async ({ request }) => {
      const root = await load(request, entry.url);
      const body = root.querySelector('body')!;
      const imgs = body.querySelectorAll('img');

      const high = imgs.filter((i) => i.getAttribute('fetchpriority') === 'high');
      expect(high.length, `img[fetchpriority=high] count: ${high.map(tagSnippet).join(' | ')}`).toBeLessThanOrEqual(1);

      if (entry.lcp.kind === 'image') {
        const node = body.querySelector(entry.lcp.selector);
        expect(node, `lcp.selector ${entry.lcp.selector}`).not.toBeNull();
        const img = node!.tagName === 'IMG' ? node! : node!.querySelector('img');
        expect(img, `<img> of lcp.selector ${entry.lcp.selector}`).not.toBeNull();
        expect(high[0] === img, `the fetchpriority=high image is the LCP image ${tagSnippet(img!)}`).toBe(true);
        expect(img!.getAttribute('loading'), 'LCP image loading').toBe('eager');
      }

      const bad = imgs
        .filter((i) => !i.getAttribute('width') || !i.getAttribute('height') || !i.getAttribute('loading'))
        .map(tagSnippet);
      expect(bad, '<img> without width/height/loading (use the theme image helper)').toEqual([]);

      const mixed = imgs
        .filter((i) => {
          const cands = (i.getAttribute('srcset') ?? '').split(',').map((c) => c.trim().split(/\s+/)[0]).filter(Boolean);
          return cands.some((c) => /\.webp(\?|$)/i.test(c)) && cands.some((c) => /\.(png|jpe?g)(\?|$)/i.test(c));
        })
        .map(tagSnippet);
      expect(mixed, 'srcset mixes WebP sub-sizes with non-WebP originals').toEqual([]);
    });

    test('LCP node and first-screen blocks are outside .reveal (K1)', async ({ request }) => {
      const root = await load(request, entry.url);
      const body = root.querySelector('body')!;
      const lcp = body.querySelectorAll(entry.lcp.selector);
      expect(lcp.length, `lcp.selector ${entry.lcp.selector} matches`).toBeGreaterThan(0);
      for (const el of lcp) expect(insideReveal(el), `${entry.lcp.selector} inside .reveal: ${tagSnippet(el)}`).toBe(false);

      for (const sel of entry.above_fold ?? []) {
        const blocks = body.querySelectorAll(sel);
        expect(blocks.length, `above_fold ${sel} matches`).toBeGreaterThan(0);
        for (const el of blocks) {
          expect(insideReveal(el), `${sel} inside .reveal`).toBe(false);
          const inner = el.querySelectorAll('.reveal').filter((n) => classes(n).includes('reveal'));
          expect(inner.map(tagSnippet), `.reveal inside first-screen block ${sel}`).toEqual([]);
        }
      }
    });
  });
}

test.describe('perf-markup', { tag: '@perf-markup' }, () => {
  for (const entry of pagesUnderTest()) checkPage(entry);
});
