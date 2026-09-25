/**
 * Dev tooling: theme photos source-photos/ → webp-photos/ (Sharp), for images shipped with the theme.
 *
 * Paths (both gitignored, see .gitignore "Dev image working dirs"):
 *   wp-content/themes/{theme}/assets/images/source-photos/   originals (jpg, png, webp, tif, gif)
 *   wp-content/themes/{theme}/assets/images/webp-photos/     output: <name>.webp
 * Width ≤ project.config.json → images.card.width (no upscaling), quality → images.webp_quality.
 * Originals are never modified (D24). Up-to-date outputs (newer than the source) are skipped.
 * Media Library uploads get WebP sub-sizes from WordPress itself (theme inc/images.php); this tool
 * is only for static theme images.
 *
 * Usage: node tools/convert-to-webp.mjs [--force] [--width=<px>]   (npm run images:webp)
 * Exit: 0 ok (also when there is nothing to convert), 1 a conversion failed, 2 bad arguments.
 */
import { mkdir, readdir, stat } from 'node:fs/promises';
import path from 'node:path';
import { ROOT, isMain, loadConfig, parseArgs, relPosix } from './lib.mjs';

const INPUT_EXT = new Set(['.jpg', '.jpeg', '.png', '.webp', '.tif', '.tiff', '.gif']);

export function imageDirs(cfg) {
  const base = path.join(ROOT, 'wp-content', 'themes', cfg.slug.theme, 'assets', 'images');
  return { inputDir: path.join(base, 'source-photos'), outputDir: path.join(base, 'webp-photos') };
}

async function mtime(file) {
  try {
    return (await stat(file)).mtimeMs;
  } catch {
    return 0;
  }
}

async function main() {
  const { opts } = parseArgs(process.argv.slice(2));
  if (opts.help) {
    console.log('usage: node tools/convert-to-webp.mjs [--force] [--width=<px>]');
    return 0;
  }
  const cfg = loadConfig();
  const width = opts.width ? Number(opts.width) : cfg.images.card.width;
  if (!Number.isInteger(width) || width < 16) {
    console.error(`images:webp: --width must be an integer ≥ 16, got ${opts.width}`);
    return 2;
  }
  const quality = cfg.images.webp_quality;
  const { inputDir, outputDir } = imageDirs(cfg);

  let names;
  try {
    names = (await readdir(inputDir, { withFileTypes: true }))
      .filter((e) => e.isFile() && INPUT_EXT.has(path.extname(e.name).toLowerCase()))
      .map((e) => e.name)
      .sort();
  } catch (err) {
    if (err.code !== 'ENOENT') throw err;
    names = [];
  }
  if (!names.length) {
    console.log(`images:webp: nothing to convert — put originals into ${relPosix(inputDir)}/`);
    return 0;
  }

  const { default: sharp } = await import('sharp');
  await mkdir(outputDir, { recursive: true });
  let ok = 0;
  let skipped = 0;
  let failed = 0;
  for (const name of names) {
    const src = path.join(inputDir, name);
    const dest = path.join(outputDir, `${path.basename(name, path.extname(name))}.webp`);
    if (!opts.force && (await mtime(dest)) >= (await mtime(src))) {
      skipped++;
      continue;
    }
    try {
      await sharp(src, { autoOrient: true }).resize({ width, withoutEnlargement: true }).webp({ quality }).toFile(dest);
      console.log(`  ok   ${name} → ${relPosix(dest)}`);
      ok++;
    } catch (err) {
      console.error(`  ERR  ${name}: ${err?.message ?? err}`);
      failed++;
    }
  }
  console.log(`images:webp: converted ${ok}, up to date ${skipped}, failed ${failed} (width ≤ ${width}px, quality ${quality})`);
  return failed ? 1 : 0;
}

if (isMain(import.meta.url)) {
  main().then(
    (code) => (process.exitCode = code),
    (err) => {
      console.error(`images:webp: ${err?.stack ?? err}`);
      process.exitCode = 1;
    },
  );
}
