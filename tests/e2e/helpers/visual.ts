/**
 * Prototype-vs-WordPress screenshot diff (pixelmatch + pngjs). Playwright's toHaveScreenshot
 * compares against a stored baseline; here both sides are rendered live, so we diff two PNGs.
 */
import type { Page, TestInfo } from '@playwright/test';
import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';

/** Max share of differing pixels (0…1): VISUAL_MAX_DIFF or 0.05. */
export const maxDiffRatio = Number(process.env.VISUAL_MAX_DIFF ?? 0.05);
/** Per-pixel colour tolerance of pixelmatch (0…1). */
const PIXEL_THRESHOLD = 0.1;

export interface DiffResult {
  ratio: number;
  diffPixels: number;
  width: number;
  height: number;
  heightA: number;
  heightB: number;
  diffPng: Buffer;
}

/** Crop a PNG to the top-left w×h. */
function crop(png: PNG, w: number, h: number): PNG {
  if (png.width === w && png.height === h) return png;
  const out = new PNG({ width: w, height: h });
  PNG.bitblt(png, out, 0, 0, w, h, 0, 0);
  return out;
}

/** Diff two screenshots over their common area (height differences are reported, not diffed). */
export function diffPngs(a: Buffer, b: Buffer): DiffResult {
  const pa = PNG.sync.read(a);
  const pb = PNG.sync.read(b);
  const width = Math.min(pa.width, pb.width);
  const height = Math.min(pa.height, pb.height);
  const ca = crop(pa, width, height);
  const cb = crop(pb, width, height);
  const diff = new PNG({ width, height });
  const diffPixels = pixelmatch(ca.data, cb.data, diff.data, width, height, { threshold: PIXEL_THRESHOLD });
  return { ratio: diffPixels / (width * height), diffPixels, width, height, heightA: pa.height, heightB: pb.height, diffPng: PNG.sync.write(diff) };
}

/** CSS that removes motion and reveal states so both renders are comparable. */
export const STABILIZE_CSS = `
  *, *::before, *::after { animation: none !important; transition: none !important; caret-color: transparent !important; }
  .reveal { opacity: 1 !important; transform: none !important; visibility: visible !important; }
`;

/** Open a URL and take a stable full-page screenshot. */
export async function stableScreenshot(page: Page, url: string, extraCss = ''): Promise<Buffer> {
  await page.goto(url, { waitUntil: 'load' });
  await page.addStyleTag({ content: STABILIZE_CSS + extraCss });
  await page.evaluate(() => document.fonts.ready);
  return page.screenshot({ fullPage: true, animations: 'disabled' });
}

/** Attach both screenshots and the diff to the report. */
export async function attachDiff(testInfo: TestInfo, a: Buffer, b: Buffer, r: DiffResult, labels: [string, string]): Promise<void> {
  await testInfo.attach(`${labels[0]}.png`, { body: a, contentType: 'image/png' });
  await testInfo.attach(`${labels[1]}.png`, { body: b, contentType: 'image/png' });
  await testInfo.attach('diff.png', { body: r.diffPng, contentType: 'image/png' });
}
