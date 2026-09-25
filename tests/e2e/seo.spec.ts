/**
 * seo: Yoast output per pages-map (title, one JSON-LD graph with the expected custom pieces),
 * robots meta vs blog_public, XML sitemap without noindex pages and leads, /llms.txt, robots.txt.
 * The site is not flipped to public: expectations follow the current blog_public value.
 */
import { expect, test, type APIRequestContext } from '@playwright/test';
import { allPages, names, pagesUnderTest, readSeed, siteUrl } from './helpers/config';
import { fetchHtml } from './helpers/html';
import { getOption, wpAvailable } from './helpers/wp';

type GraphNode = { '@type'?: string | string[]; '@id'?: string; mainEntity?: unknown[] };

const typesOf = (n: GraphNode) => ([] as string[]).concat(n['@type'] ?? []);
const businessType = readSeed<{ business_type?: string }>('company.json')?.business_type;

/** Graph pieces for pages-map `schema` names. */
const PIECES: Record<string, (n: GraphNode) => boolean> = {
  LocalBusiness: (n) =>
    typesOf(n).some((t) => t === 'LocalBusiness' || t === businessType) || /\/schema\/localbusiness$/.test(n['@id'] ?? ''),
  Service: (n) => typesOf(n).includes('Service'),
  FAQPage: (n) => typesOf(n).includes('FAQPage') && Array.isArray(n.mainEntity) && n.mainEntity.length > 0,
  Product: (n) => typesOf(n).includes('Product'),
};
/** Pieces that must be absent where pages-map does not list them (LocalBusiness is site-wide). */
const EXCLUSIVE = ['Service', 'FAQPage', 'Product'];

/** Whether the site is public (blog_public = 1): WP-CLI, or the front page robots meta as fallback. */
let isPublic: boolean | null = null;
async function sitePublic(request: APIRequestContext): Promise<boolean> {
  if (isPublic === null) {
    if (wpAvailable()) isPublic = getOption('blog_public') === '1';
    else {
      const front = allPages.find((p) => p.type === 'front' && !p.noindex) ?? allPages[0];
      const { root } = await fetchHtml(request, front.url);
      isPublic = !/noindex/i.test(root.querySelector('meta[name="robots"]')?.getAttribute('content') ?? '');
    }
  }
  return isPublic;
}

async function sitemapUrls(request: APIRequestContext): Promise<string[]> {
  const index = await request.get('/sitemap_index.xml');
  expect(index.status(), 'sitemap_index.xml').toBe(200);
  const maps = [...(await index.text()).matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1]);
  expect(maps.length, 'sitemaps in the index').toBeGreaterThan(0);
  const urls: string[] = [];
  for (const map of maps) {
    expect(map, 'no leads sitemap').not.toContain(names.leadPostType);
    const res = await request.get(new URL(map).pathname);
    expect(res.status(), map).toBe(200);
    urls.push(...[...(await res.text()).matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => new URL(m[1]).pathname));
  }
  return urls;
}

test.describe('seo', { tag: '@seo' }, () => {
  for (const entry of pagesUnderTest()) {
    test(`${entry.url}: title, one JSON-LD graph with ${JSON.stringify(entry.schema ?? [])}, robots`, async ({ request }) => {
      const { root } = await fetchHtml(request, entry.url);

      expect(root.querySelectorAll('title'), 'one <title>').toHaveLength(1);
      expect(root.querySelector('title')!.text.trim(), 'title text').not.toBe('');

      const scripts = root.querySelectorAll('script[type="application/ld+json"]');
      expect(scripts, 'exactly one application/ld+json (Yoast graph)').toHaveLength(1);
      const graph = (JSON.parse(scripts[0].text)['@graph'] ?? []) as GraphNode[];
      expect(graph.length, '@graph nodes').toBeGreaterThan(0);

      const expected = entry.schema ?? [];
      for (const piece of expected) {
        expect(graph.some(PIECES[piece]), `${piece} piece in the graph (types: ${graph.flatMap(typesOf).join(', ')})`).toBe(true);
      }
      for (const piece of EXCLUSIVE.filter((p) => !expected.includes(p as never))) {
        expect(graph.some(PIECES[piece]), `${piece} must not be in the graph (not listed in pages-map)`).toBe(false);
      }

      const robots = root.querySelector('meta[name="robots"]')?.getAttribute('content') ?? '';
      if (entry.noindex || !(await sitePublic(request))) expect(robots, 'robots meta').toMatch(/\bnoindex\b/);
      else expect(robots, 'robots meta').not.toMatch(/\bnoindex\b/);
    });
  }

  test('titles are unique across pages-map', async ({ request }) => {
    test.slow();
    const pages = pagesUnderTest();
    test.skip(pages.length < 2, 'fewer than two pages under test');
    const seen = new Map<string, string>();
    for (const entry of pages) {
      const { root } = await fetchHtml(request, entry.url);
      const title = root.querySelector('title')?.text.trim() ?? '';
      expect(seen.get(title), `title "${title}" of ${entry.url} duplicates`).toBeUndefined();
      seen.set(title, entry.url);
    }
  });

  test('sitemap: 200, lists indexable pages, excludes noindex pages and leads', async ({ request }) => {
    test.slow();
    const urls = await sitemapUrls(request);
    for (const entry of allPages) {
      if (entry.noindex) expect(urls, `noindex ${entry.url} in sitemap`).not.toContain(entry.url);
      else if (entry.type !== 'front') expect(urls, `${entry.url} missing from sitemap`).toContain(entry.url);
    }
  });

  test('/llms.txt: 200 text/plain, lists no noindex page', async ({ request }) => {
    const res = await request.get('/llms.txt');
    expect(res.status()).toBe(200);
    expect(res.headers()['content-type']).toMatch(/^text\/plain/);
    const body = await res.text();
    expect(body.trim()).not.toBe('');
    for (const entry of allPages.filter((p) => p.noindex)) expect(body, `noindex ${entry.url} in llms.txt`).not.toContain(`(${siteUrl(entry.url)})`);
  });

  test('robots.txt: reachable, sitemap line; AI allow group only when the site is public', async ({ request }) => {
    const res = await request.get('/robots.txt');
    expect(res.status()).toBe(200);
    const body = await res.text();
    expect(body).toMatch(/^Sitemap:\s*\S+sitemap_index\.xml/m);
    const aiGroup = /^User-agent:\s*GPTBot\s*$/im.test(body);
    expect(aiGroup, `AI crawler group present (blog_public ${(await sitePublic(request)) ? 1 : 0})`).toBe(await sitePublic(request));
  });
});
