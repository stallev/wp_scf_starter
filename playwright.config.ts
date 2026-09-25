/**
 * E2E (Playwright): one project per suite, data from pages-map.json and project.config.json.
 *
 *   npm run test:e2e                 all primary suites (Chromium)
 *   npm run test:e2e:<suite>         one suite (static, navigation, forms, dynamic, seo, perf-markup, console, a11y, visual)
 *   npm run test:e2e:smoke           Firefox + WebKit smoke (static, dynamic); projects exist only with E2E_SMOKE=1
 *   npm run gate:page -- <url>       page suites filtered to one pages-map URL (E2E_PAGE_URL)
 *
 * Base URL: PLAYWRIGHT_BASE_URL (.env or shell) or project.config.json → urls.local.
 * Strategy and suite contracts: docs/contracts/testing.md (M7), rule .cursor/rules/tests.mdc.
 */
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, devices, type Project } from '@playwright/test';

const root = path.dirname(fileURLToPath(import.meta.url));
const envFile = path.join(root, '.env');
if (existsSync(envFile)) process.loadEnvFile(envFile); // does not override variables already set

const { baseURL } = await import('./tests/e2e/helpers/config');

const desktop = { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 } };
const suite = (name: string, use: Project['use'] = desktop, extra: Partial<Project> = {}): Project => ({
  name,
  testMatch: new RegExp(`${name}\\.spec\\.ts$`),
  use,
  ...extra,
});

const projects: Project[] = [
  suite('static'),
  suite('navigation', desktop, { name: 'navigation-desktop', grep: /@desktop/ }),
  suite('navigation', { ...devices['Pixel 5'] }, { name: 'navigation-mobile', grep: /@mobile/ }),
  suite('forms'),
  suite('dynamic'),
  suite('seo'),
  suite('perf-markup'),
  suite('console'),
  suite('a11y', { ...desktop, contextOptions: { reducedMotion: 'reduce' } }),
  suite('visual', { ...desktop, contextOptions: { reducedMotion: 'reduce' } }),
];

if (process.env.E2E_SMOKE === '1') {
  const smokeMatch = /(static|dynamic)\.spec\.ts$/;
  projects.push(
    { name: 'smoke-firefox', testMatch: smokeMatch, use: { ...devices['Desktop Firefox'], viewport: { width: 1280, height: 800 } } },
    { name: 'smoke-webkit', testMatch: smokeMatch, use: { ...devices['Desktop Safari'], viewport: { width: 1280, height: 800 } } },
  );
}

export default defineConfig({
  testDir: './tests/e2e',
  outputDir: './test-results',
  globalSetup: './tests/e2e/global-setup.ts',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 2 : undefined,
  timeout: Number(process.env.E2E_TIMEOUT ?? 90_000), // wp-env on Docker Desktop (Windows) can answer in seconds per page
  expect: { timeout: 20_000 },
  reporter: [['list'], ['html', { outputFolder: 'playwright-report', open: 'never' }]],
  use: {
    baseURL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects,
});
