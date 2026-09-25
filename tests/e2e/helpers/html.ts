/**
 * Server HTML without a browser (APIRequestContext) + node-html-parser.
 */
import { expect, type APIRequestContext } from '@playwright/test';
import { parse, type HTMLElement } from 'node-html-parser';

export interface FetchedHtml {
  status: number;
  html: string;
  root: HTMLElement;
  headers: Record<string, string>;
}

/** GET a site path; asserts 200 unless `expectStatus` says otherwise. */
export async function fetchHtml(request: APIRequestContext, url: string, expectStatus: number | null = 200): Promise<FetchedHtml> {
  const res = await request.get(url, { timeout: 30_000, maxRedirects: 0 });
  if (expectStatus !== null) expect(res.status(), `HTTP status of ${url}`).toBe(expectStatus);
  const html = await res.text();
  return { status: res.status(), html, root: parse(html, { comment: false }), headers: res.headers() };
}

/** Class list of an element. */
export const classes = (el: HTMLElement) => (el.getAttribute('class') ?? '').split(/\s+/).filter(Boolean);

/** True when the element or one of its ancestors has `.reveal`. */
export function insideReveal(el: HTMLElement): boolean {
  for (let n: HTMLElement | null = el; n && n.tagName; n = n.parentNode as HTMLElement | null) {
    if (classes(n).includes('reveal')) return true;
  }
  return false;
}

/** Short printable form of a tag for assertion messages. */
export const tagSnippet = (el: HTMLElement) => el.outerHTML.replace(/\s+/g, ' ').slice(0, 160);
