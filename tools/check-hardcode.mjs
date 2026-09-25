/**
 * Company data must reach theme templates only through the core API (starter_get_company(),
 * starter_company_value(), starter_phone_href(), starter_url() …), never as literals (K17).
 *
 * Scans wp-content/themes/** (*.php, *.js) for:
 *   - literal company data from seed/company.json: phones (compared by digits, so any formatting
 *     matches), email, legal name, street / address text / postal code, social profile URLs
 *     (the short brand name is not a needle: it usually equals the prefix, e.g. @package Acme);
 *   - price literals from seed/service-cards.json (`price`) — the only prices the starter knows;
 *   - the site's own origin from project.config.json → urls (production, staging, local);
 *   - generic patterns: `tel:` with a literal number, `mailto:` with a literal address, any e-mail
 *     literal (reserved sample domains example.com / .test / .invalid are ignored).
 * Text needles match on word boundaries (a postal code does not match inside a longer number).
 * A line that must keep a literal (rare: e.g. a doc comment with a sample) carries `hardcode:allow`.
 *
 * Usage: node tools/check-hardcode.mjs [--dir=<repo-relative dir>]   (npm run check:hardcode)
 * Exit: 0 ok, 1 hardcoded data found, 2 cannot read inputs.
 */
import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { ROOT, isMain, listRepoFiles, loadConfig, parseArgs, report } from './lib.mjs';

export const ALLOW_MARKER = 'hardcode:allow';
const SCAN_EXT = /\.(php|js)$/i;
const MIN_TEXT = 4;
const MIN_PHONE_DIGITS = 7;

const escapeRe = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const digits = (s) => String(s).replace(/\D/g, '');

function readSeed(cfg, file) {
  const full = path.join(ROOT, cfg.paths.seed, file);
  return existsSync(full) ? JSON.parse(readFileSync(full, 'utf8')) : null;
}

/**
 * Build the needle set from company data, service cards and config URLs.
 * @returns {{ phones: {label: string, digits: string}[], texts: {label: string, value: string}[], origins: string[] }}
 */
export function buildNeedles({ company = {}, serviceCards = null, urls = {} } = {}) {
  const phones = [];
  for (const p of company.phones ?? []) {
    const d = digits(p?.number ?? '');
    if (d.length >= MIN_PHONE_DIGITS) phones.push({ label: `phone ${p.number}`, digits: d });
  }

  const texts = [];
  const addText = (label, value) => {
    const v = typeof value === 'string' ? value.trim() : '';
    if (v.length >= MIN_TEXT && !texts.some((t) => t.value.toLowerCase() === v.toLowerCase())) texts.push({ label, value: v });
  };
  addText('email', company.email);
  addText('legal name', company.legal_name);
  addText('street', company.address?.street);
  addText('address text', company.address?.text);
  addText('postal code', company.address?.postal_code);
  for (const s of company.socials ?? []) addText(`social ${s?.network ?? ''}`.trim(), s?.url);
  for (const c of serviceCards?.items ?? []) {
    if (/\d/.test(c?.price ?? '')) addText(`price of ${c.page}`, c.price);
  }

  const origins = [];
  for (const key of ['production', 'staging', 'local']) {
    if (!urls[key]) continue;
    try {
      const host = new URL(urls[key]).host.replace(/^www\./, '');
      if (!origins.includes(host)) origins.push(host);
    } catch {
      // Schema-invalid URL: check:config reports it.
    }
  }
  return { phones, texts, origins };
}

/**
 * Case-insensitive literal with word boundaries on word-character ends: "220000" does not match inside
 * "1220000", "Demo Street" not inside "DemoStreets"; values ending in punctuation keep an open end.
 */
export function needleRegex(value) {
  const before = /^\w/.test(value) ? '(?<!\\w)' : '';
  const after = /\w$/.test(value) ? '(?!\\w)' : '';
  return new RegExp(`${before}${escapeRe(value)}${after}`, 'i');
}

/** Any e-mail literal; reserved example domains (RFC 2606) are samples, not data. */
const EMAIL = /[\w.+-]+@[\w-]+(?:\.[\w-]+)*\.[A-Za-z]{2,}/g;
const SAMPLE_EMAIL = /@(?:[\w-]+\.)*(?:example\.(?:com|org|net)|example|test|invalid|localhost)$/i;

/** Phone-like digit runs in a line: +7 (000) 000-00-00, 80000000000, 000 00 00 … */
const PHONE_RUN = /\+?\d[\d\s().-]{5,}\d/g;

/**
 * Findings in one file's text. Lines with ALLOW_MARKER are skipped.
 * @returns {string[]} `file:line: …` messages.
 */
export function scanText(text, file, needles) {
  const out = [];
  const textRes = needles.texts.map((t) => ({ ...t, re: needleRegex(t.value) }));
  const originRes = needles.origins.map((h) => ({ host: h, re: new RegExp(`(?:https?:)?//(?:www\\.)?${escapeRe(h)}(?![\\w.-])`, 'i') }));

  text.split(/\r?\n/).forEach((line, i) => {
    if (line.includes(ALLOW_MARKER)) return;
    const at = `${file}:${i + 1}`;
    const hits = new Set();

    const tel = /tel:\s*\+?[\d(]/i.exec(line);
    if (tel) hits.add(`literal tel: link "${tel[0]}…" → starter_tel_href() / starter_phone_href( starter_company_value( 'phone' ) )`);
    const mail = /mailto:\s*[^\s'"<>)]+@/i.exec(line);
    if (mail) hits.add(`literal mailto: link "${mail[0]}…" → 'mailto:' . starter_company_value( 'email' )`);

    for (const m of line.matchAll(PHONE_RUN)) {
      const d = digits(m[0]);
      if (d.length < MIN_PHONE_DIGITS) continue;
      const phone = needles.phones.find((p) => p.digits === d || (d.length >= 9 && p.digits.endsWith(d)) || (p.digits.length >= 9 && d.endsWith(p.digits)));
      if (phone) hits.add(`company ${phone.label} hardcoded ("${m[0].trim()}") → starter_company_value( 'phone' ) / starter_get_company()`);
    }
    for (const m of line.matchAll(EMAIL)) {
      if (!SAMPLE_EMAIL.test(m[0]) && !needles.texts.some((t) => t.value.toLowerCase() === m[0].toLowerCase())) {
        hits.add(`e-mail literal "${m[0]}" → starter_company_value( 'email' ) (or a field / option)`);
      }
    }
    for (const t of textRes) {
      if (t.re.test(line)) hits.add(`company ${t.label} hardcoded ("${t.value}") → starter_company_value() / starter_get_company() / service card fields`);
    }
    for (const o of originRes) {
      if (o.re.test(line)) hits.add(`absolute link to the site's own origin ${o.host} → home_url() / starter_url() / get_permalink()`);
    }
    for (const h of hits) out.push(`${at}: ${h} (or mark the line ${ALLOW_MARKER})`);
  });
  return out;
}

function main() {
  const { opts } = parseArgs(process.argv.slice(2));
  if (opts.help) {
    console.log('usage: node tools/check-hardcode.mjs [--dir=wp-content/themes]');
    return;
  }
  let cfg;
  let needles;
  try {
    cfg = loadConfig();
    needles = buildNeedles({
      company: readSeed(cfg, 'company.json') ?? {},
      serviceCards: readSeed(cfg, 'service-cards.json'),
      urls: cfg.urls,
    });
  } catch (err) {
    console.error(`check-hardcode: cannot read inputs (${err.message})`);
    process.exit(2);
  }

  const dir = String(opts.dir ?? 'wp-content/themes').replace(/\\/g, '/').replace(/\/+$/, '');
  const files = listRepoFiles().filter((f) => f.startsWith(`${dir}/`) && SCAN_EXT.test(f));
  const errors = [];
  for (const f of files) errors.push(...scanText(readFileSync(path.join(ROOT, f), 'utf8'), f, needles));

  const n = needles.phones.length + needles.texts.length + needles.origins.length;
  report('check-hardcode', errors, `ok (${files.length} files in ${dir}/, ${n} company/url needles + tel:/mailto: patterns)`);
}

if (isMain(import.meta.url)) main();
