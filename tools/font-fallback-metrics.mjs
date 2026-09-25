/**
 * Fallback @font-face with metrics adjusted to a web font, measured on real text (K4).
 *
 * Why: while a woff2 loads (font-display: swap) the text is drawn with a local font; if its advance
 * widths and vertical metrics differ, the layout jumps on swap (CLS). A "<Name> Fallback" face over
 * local("Arial") with size-adjust / ascent-override / descent-override / line-gap-override makes the
 * fallback occupy the same space.
 *
 * How (the lesson from the source project: file-level averages were off by ~4%, e.g. 105% instead of
 * the measured ~101.3%, and the value depends on the weight):
 *   1. size-adjust — measured, not derived: Chromium (Playwright) renders the sample text with the
 *      web font and with the fallback via canvas measureText, per weight, and size-adjust =
 *      width(web) / width(fallback). The sample is the site's own text: the running site's front page
 *      (project.config.json → urls.local), or --text-file, or a built-in RU/EN pangram set.
 *   2. ascent / descent / line gap — from the font file (fontkit), as browsers pick them for line
 *      boxes: OS/2 typo metrics when fsSelection bit 7 (USE_TYPO_METRICS) is set, otherwise hhea;
 *      divided by unitsPerEm and by size-adjust (the override applies to the adjusted size).
 * Weights: a variable font (fvar table with a wght axis) is registered with its axis range, and any
 * --weights inside it are measured. A static file is registered with its real weight (OS/2
 * usWeightClass) and measures only that weight — so a synthetic bold never skews the numbers. For
 * several static files pass pairs: --font=inter-400.woff2@400,inter-700.woff2@700 (weights come from
 * the pairs; --weights is not needed). A --weights value the files do not have is refused.
 * Weights whose size-adjust differs by more than 0.3% get their own block with font-weight.
 * Re-run after changing the font file, subsets or main weights.
 *
 * Usage: npm run fonts:fallback -- --font=assets/fonts/inter-latin.woff2 --family="Inter"
 *          [--weights=400,700] [--text-file=path.txt] [--url=http://localhost:8888/] [--no-site] [--fallback=Arial]
 *   --font: theme-relative (wp-content/themes/{theme}/…), repo-relative or absolute; woff2/woff/ttf/otf;
 *           or a comma-separated list of path@weight pairs for static files.
 * Exit: 0 ok (CSS on stdout), 2 bad arguments / font not readable / weight not in the files /
 *       Chromium not installed (npx playwright install chromium).
 */
import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { ROOT, isMain, loadConfig, parseArgs } from './lib.mjs';

export const PANGRAMS = [
  'Съешь же ещё этих мягких французских булок, да выпей чаю.',
  'В чащах юга жил бы цитрус? Да, но фальшивый экземпляр!',
  'Широкая электрификация южных губерний даст мощный толчок подъёму сельского хозяйства.',
  'The quick brown fox jumps over the lazy dog.',
  'Pack my box with five dozen liquor jugs. Sphinx of black quartz, judge my vow.',
  'Телефон: +000 00 000-00-00, e-mail: info@example.com — 1234567890 (%) «»',
];
const MERGE_TOLERANCE = 0.003;
const MAX_SAMPLE = 20000;

class UsageError extends Error {}

const pct = (v) => `${(Math.round(v * 10000) / 100).toFixed(2)}%`;

/**
 * Overrides from a width measurement and hhea metrics (font units).
 * @returns {{ sizeAdjust: number, ascent: number, descent: number, lineGap: number }} ratios (1 = 100%)
 */
export function computeOverrides({ widthWeb, widthFallback, ascent, descent, lineGap = 0, unitsPerEm }) {
  if (!(widthWeb > 0) || !(widthFallback > 0)) throw new Error('widths must be positive');
  if (!(unitsPerEm > 0)) throw new Error('unitsPerEm must be positive');
  const sizeAdjust = widthWeb / widthFallback;
  return {
    sizeAdjust,
    ascent: ascent / unitsPerEm / sizeAdjust,
    descent: Math.abs(descent) / unitsPerEm / sizeAdjust,
    lineGap: Math.max(0, lineGap) / unitsPerEm / sizeAdjust,
  };
}

/** Merge weights with near-equal size-adjust; returns [{ weights: number[], overrides }]. */
export function groupByWeight(perWeight, tolerance = MERGE_TOLERANCE) {
  const groups = [];
  for (const { weight, overrides } of [...perWeight].sort((a, b) => a.weight - b.weight)) {
    const last = groups[groups.length - 1];
    if (last && Math.abs(last.overrides.sizeAdjust - overrides.sizeAdjust) / last.overrides.sizeAdjust <= tolerance) {
      last.weights.push(weight);
    } else {
      groups.push({ weights: [weight], overrides });
    }
  }
  return groups;
}

/** Ready-to-paste CSS. */
export function renderFontFace({ family, fallback, groups }) {
  const name = `${family} Fallback`;
  return groups
    .map(({ weights, overrides }) => {
      const lines = [
        '@font-face {',
        `  font-family: "${name}";`,
        `  src: local("${fallback}");`,
        ...(groups.length > 1 ? [`  font-weight: ${weights.length > 1 ? `${weights[0]} ${weights[weights.length - 1]}` : weights[0]};`] : []),
        `  size-adjust: ${pct(overrides.sizeAdjust)};`,
        `  ascent-override: ${pct(overrides.ascent)};`,
        `  descent-override: ${pct(overrides.descent)};`,
        `  line-gap-override: ${pct(overrides.lineGap)};`,
        '}',
      ];
      return `/* ${family} → ${fallback}, weight ${weights.join(', ')}: measured on real text */\n${lines.join('\n')}`;
    })
    .join('\n\n');
}

function resolveFont(arg, cfg) {
  const candidates = [path.resolve(arg), path.join(ROOT, 'wp-content', 'themes', cfg.slug.theme, arg), path.join(ROOT, arg)];
  const found = candidates.find((p) => existsSync(p));
  if (!found) throw new UsageError(`font not found: ${arg} (theme-relative, repo-relative or absolute)`);
  return found;
}

/**
 * Vertical metrics as browsers use them: OS/2 typo when USE_TYPO_METRICS (fsSelection bit 7), else hhea.
 * `font` is a fontkit font (or a test double with the same fields).
 */
export function verticalMetrics(font) {
  const os2 = font['OS/2'];
  const useTypo = Boolean(os2?.fsSelection?.useTypoMetrics);
  return useTypo
    ? { source: 'OS/2 typo', ascent: os2.typoAscender, descent: os2.typoDescender, lineGap: os2.typoLineGap, unitsPerEm: font.unitsPerEm }
    : { source: 'hhea', ascent: font.ascent, descent: font.descent, lineGap: font.lineGap, unitsPerEm: font.unitsPerEm };
}

/** Weight coverage of a font: variable → axis range, static → OS/2 usWeightClass (or the @weight given). */
export function fontWeights(font, declared = null) {
  const wght = font.variationAxes?.wght;
  if (wght && font.directory?.tables?.fvar) return { variable: true, min: wght.min, max: wght.max, descriptor: `${wght.min} ${wght.max}` };
  const w = declared ?? font['OS/2']?.usWeightClass ?? 400;
  return { variable: false, min: w, max: w, descriptor: String(w) };
}

/** Parse --font: "a.woff2" or "a.woff2@400,b.woff2@700" → [{ file, weight|null }]. */
export function parseFontArg(arg) {
  return String(arg)
    .split(',')
    .map((part) => part.trim())
    .filter(Boolean)
    .map((part) => {
      const m = /^(.*)@(\d{1,4})$/.exec(part);
      return m ? { file: m[1], weight: Number(m[2]) } : { file: part, weight: null };
    });
}

/**
 * Which face measures which weight. faces = [{ file, coverage }] (coverage from fontWeights()).
 * requested = weights from --weights or null (then: every static weight, or 400 / the axis default).
 * Throws UsageError for a weight no face covers. Returns [{ weight, face }].
 */
export function assignWeights(faces, requested) {
  const weights = requested ?? (faces.every((f) => !f.coverage.variable) ? faces.map((f) => f.coverage.min) : [Math.min(Math.max(400, faces[0].coverage.min), faces[0].coverage.max)]);
  return weights.map((weight) => {
    const face = faces.find((f) => weight >= f.coverage.min && weight <= f.coverage.max);
    if (!face) {
      const have = faces.map((f) => `${path.basename(f.file)}: ${f.coverage.variable ? `variable ${f.coverage.descriptor}` : `static ${f.coverage.min}`}`).join('; ');
      throw new UsageError(
        `weight ${weight} is not in the font file(s) (${have}). A static file measures only its own weight — pass one file per weight: --font=regular.woff2@400,bold.woff2@700`,
      );
    }
    return { weight, face };
  });
}

/** Visible text of the running site's page, or null. */
async function siteText(url) {
  try {
    const res = await fetch(url, { signal: AbortSignal.timeout(30000), headers: { accept: 'text/html' } });
    if (!res.ok) return null;
    const { parse } = await import('node-html-parser');
    const root = parse(await res.text());
    root.querySelectorAll('script, style, noscript, svg').forEach((n) => n.remove());
    const text = (root.querySelector('body') ?? root).structuredText.replace(/\s+\n/g, '\n').trim();
    return text.length > 200 ? text : null;
  } catch {
    return null;
  }
}

function dataSrc(fontFile) {
  const ext = path.extname(fontFile).slice(1).toLowerCase();
  const format = { woff2: 'woff2', woff: 'woff', ttf: 'truetype', otf: 'opentype' }[ext] ?? 'woff2';
  const mime = { woff2: 'font/woff2', woff: 'font/woff', ttf: 'font/ttf', otf: 'font/otf' }[ext] ?? 'font/woff2';
  return `url(data:${mime};base64,${readFileSync(fontFile).toString('base64')}) format("${format}")`;
}

async function measure({ faces, weights, fallback, text }) {
  const { chromium } = await import('@playwright/test');
  const srcs = faces.map((f) => ({ src: dataSrc(f.file), weight: f.coverage.descriptor }));

  let browser;
  try {
    browser = await chromium.launch();
  } catch (err) {
    throw new UsageError(`cannot start Chromium (${err.message.split('\n')[0]}) → npx playwright install chromium`);
  }
  try {
    const page = await browser.newPage();
    await page.setContent('<!doctype html><html><body></body></html>');
    return await page.evaluate(
      async ({ srcs, weights, fallback, lines }) => {
        // Each file with its real weight (static) or axis range (variable): no synthetic bold.
        for (const { src, weight } of srcs) document.fonts.add(await new FontFace('Probe Web Font', src, { weight }).load());
        const local = new FontFace('Probe Fallback', `local("${fallback}")`);
        try {
          document.fonts.add(await local.load());
        } catch {
          return { error: `local font "${fallback}" is not installed in this system` };
        }
        const ctx = document.createElement('canvas').getContext('2d');
        const width = (family, weight) => {
          ctx.font = `${weight} 100px "${family}"`;
          return lines.reduce((sum, line) => sum + ctx.measureText(line).width, 0);
        };
        return {
          results: weights.map((weight) => ({
            weight,
            widthWeb: width('Probe Web Font', weight),
            widthFallback: width(fallback, weight),
          })),
        };
      },
      { srcs, weights, fallback, lines: text.split(/\n+/).map((l) => l.trim()).filter(Boolean) },
    );
  } finally {
    await browser.close();
  }
}

async function main() {
  const { opts } = parseArgs(process.argv.slice(2));
  if (opts.help || !opts.font || !opts.family) {
    console.log('usage: npm run fonts:fallback -- --font=<theme-relative woff2> --family="Name" [--weights=400,700] [--text-file=…] [--url=…] [--no-site] [--fallback=Arial]');
    return opts.help ? 0 : 2;
  }
  const cfg = loadConfig();
  const { create } = await import('fontkit');
  const faces = parseFontArg(opts.font).map(({ file, weight }) => {
    const abs = resolveFont(file, cfg);
    const font = create(readFileSync(abs));
    return { file: abs, font, coverage: fontWeights(font, weight), metrics: verticalMetrics(font) };
  });
  let requested = null;
  if (opts.weights) {
    requested = String(opts.weights)
      .split(',')
      .map((w) => Number(w.trim()))
      .filter((w) => Number.isInteger(w) && w >= 1 && w <= 1000);
    if (!requested.length) throw new UsageError(`--weights: integers 1–1000, got ${opts.weights}`);
  }
  const plan = assignWeights(faces, requested);
  const weights = plan.map((p) => p.weight);
  const fallback = String(opts.fallback ?? 'Arial');

  let text = null;
  let source = '';
  if (opts['text-file']) {
    text = readFileSync(path.resolve(String(opts['text-file'])), 'utf8');
    source = String(opts['text-file']);
  } else if (!opts['no-site']) {
    const url = String(opts.url ?? `${cfg.urls.local}/`);
    text = await siteText(url);
    source = text ? url : '';
  }
  if (!text) {
    text = PANGRAMS.join('\n');
    source = 'built-in RU/EN pangrams';
  }
  text = text.slice(0, MAX_SAMPLE);

  const measured = await measure({ faces, weights, fallback, text });
  if (measured.error) throw new UsageError(measured.error);

  const perWeight = measured.results.map((r) => {
    const { metrics } = plan.find((p) => p.weight === r.weight).face;
    return { weight: r.weight, overrides: computeOverrides({ ...r, ...metrics }) };
  });
  console.log(`/* Sample: ${source} (${text.length} chars). */`);
  for (const f of faces) {
    const m = f.metrics;
    console.log(`/* ${path.basename(f.file)}: ${f.coverage.variable ? 'variable' : 'static'} ${f.coverage.descriptor}, UPM ${m.unitsPerEm}, ${m.source} ${m.ascent}/${m.descent}/${m.lineGap} */`);
  }
  console.log(renderFontFace({ family: String(opts.family), fallback, groups: groupByWeight(perWeight) }));
  console.log(`/* Stack: font-family: "${opts.family}", "${opts.family} Fallback", sans-serif; */`);
  return 0;
}

if (isMain(import.meta.url)) {
  main().then(
    (code) => (process.exitCode = code),
    (err) => {
      console.error(`fonts:fallback: ${err instanceof UsageError ? err.message : (err?.stack ?? err)}`);
      process.exitCode = 2;
    },
  );
}
