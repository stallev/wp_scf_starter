/**
 * Typed access to project.config.json and pages-map.json for the e2e suites.
 *
 * - Every page list comes from pages-map (never hardcode URLs in specs).
 * - Every WordPress name comes from `slug.prefix` (`names`), so the suites survive `npm run init`.
 * - E2E_PAGE_URL (set by `npm run gate:page -- <url>`) narrows page-driven tests to one entry.
 */
import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..', '..');

export type SchemaPiece = 'LocalBusiness' | 'Service' | 'FAQPage' | 'Product';

export interface PageEntry {
  url: string;
  title: string;
  prototype: string | null;
  template: string;
  type: 'front' | 'page' | 'service' | 'blog-index' | 'post' | 'archive' | 'taxonomy' | 'single' | 'utility';
  lead_form: boolean;
  noindex: boolean;
  schema?: SchemaPiece[];
  lcp: { selector: string; kind: 'text' | 'image' };
  above_fold?: string[];
  psi: boolean;
  specs?: string[];
}

export interface ProjectConfig {
  project: { name: string; description?: string };
  slug: { prefix: string; theme: string; core: string; text_domain: string };
  urls: { local: string; staging: string | null; production: string | null };
  fonts: { preload: string[] };
  paths: { prototype: string; seed: string; pages_map: string };
  [key: string]: unknown;
}

export function readRepoJson<T>(rel: string): T {
  return JSON.parse(readFileSync(path.join(ROOT, rel), 'utf8')) as T;
}

export const config = readRepoJson<ProjectConfig>('project.config.json');
export const allPages: PageEntry[] = readRepoJson<{ pages: PageEntry[] }>(config.paths.pages_map).pages;

/** Base URL without a trailing slash: PLAYWRIGHT_BASE_URL (.env / shell) or urls.local. */
export const baseURL = (process.env.PLAYWRIGHT_BASE_URL || config.urls.local).replace(/\/+$/, '');
export const baseOrigin = new URL(baseURL).origin;

/** Normalise "/contacts/", "contacts", "/" to a pages-map URL. */
export function normalizeUrl(arg: string): string {
  let a = arg.trim().replace(/\\/g, '/');
  const msys = /^[A-Za-z]:\/.*?\/Git(\/.*)?$/i.exec(a); // Git Bash rewrites "/x/" into "C:/Program Files/Git/x/"
  if (msys) a = msys[1] ?? '/';
  if (a === '' || a === '/' || a === 'home') return '/';
  return `/${a.replace(/^\/+|\/+$/g, '')}/`;
}

/** The page filter of gate:page (null = all pages). */
export const pageFilter = process.env.E2E_PAGE_URL ? normalizeUrl(process.env.E2E_PAGE_URL) : null;

/** Pages under test: all of pages-map, or only E2E_PAGE_URL. */
export function pagesUnderTest(predicate: (p: PageEntry) => boolean = () => true): PageEntry[] {
  return allPages.filter((p) => (pageFilter === null || p.url === pageFilter) && predicate(p));
}

/** Prefix-derived WordPress names (forms contract in the core's forms.php, naming.json). */
const prefix = config.slug.prefix;
const PREFIX = prefix.toUpperCase();
export const names = {
  prefix,
  PREFIX,
  theme: config.slug.theme,
  /** Global printed by the theme for main.js: window[names.leadGlobal]. */
  leadGlobal: `${PREFIX}_LEAD`,
  leadAction: `${prefix}_submit_lead`,
  leadNonceField: `${prefix}_lead_nonce`,
  honeypot: `${prefix}_hp_company`,
  leadEvent: `${prefix}:lead:success`,
  leadPostType: `${prefix}_lead`,
  leadContactMeta: `${prefix}_lead_contact`,
  rateLimitTransientPrefix: `${prefix}_lead_rl_`,
  /** Local-only option that makes the core skip Telegram (forms.php). */
  e2eOption: `${prefix}_e2e_mode`,
  fn: (name: string) => `${prefix}_${name}`,
};

/** Seed JSON (items count etc.); null when the file is absent. */
export function readSeed<T = { items?: unknown[] }>(file: string): T | null {
  const rel = path.join(config.paths.seed, file);
  return existsSync(path.join(ROOT, rel)) ? readRepoJson<T>(rel) : null;
}

/** Absolute URL of a site path. */
export const siteUrl = (p: string) => `${baseURL}${p.startsWith('/') ? p : `/${p}`}`;
