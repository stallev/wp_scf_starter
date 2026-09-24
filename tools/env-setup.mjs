/**
 * wp-env afterStart: activate the theme, set pretty permalinks, locale, discourage indexing.
 * Idempotent: safe to run on every start.
 */
import { loadConfig, run } from './lib.mjs';

const cfg = loadConfig();
const wp = (...args) => run(process.execPath, ['tools/wp-env.mjs', 'run', 'cli', 'wp', ...args], { shell: false });

const steps = [
  ['theme', 'activate', cfg.slug.theme],
  ['rewrite', 'structure', '/%postname%/', '--hard'],
  ['option', 'update', 'blog_public', '0'],
];

// Bundled demo plugins are noise for the starter; removing an absent plugin only warns.
wp('plugin', 'delete', 'akismet', 'hello');

let failed = 0;
for (const step of steps) {
  if (wp(...step) !== 0) failed++;
}

// Locale needs network access to translate.wordpress.org; not fatal.
if (cfg.locale && cfg.locale !== 'en_US') {
  if (wp('language', 'core', 'install', cfg.locale, '--activate') !== 0) {
    console.warn(`[env-setup] Could not install locale ${cfg.locale}; continuing.`);
  }
}

process.exit(failed ? 1 : 0);
