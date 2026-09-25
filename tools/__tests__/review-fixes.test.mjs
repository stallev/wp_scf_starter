/**
 * Regression tests for the M6a review fixes: init safety, font weights/metrics, hardcode boundaries
 * and e-mail literals, prototype style parsing / <template> / invalid selectors, secret-shaped seed values.
 * Excluded from init's rewrite (literal starter names below must stay).
 */
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { buildNeedles, needleRegex, scanText } from '../check-hardcode.mjs';
import { assignWeights, fontWeights, parseFontArg, verticalMetrics } from '../font-fallback-metrics.mjs';
import { RenameError, dirtyTreeError, leftovers, runRenames } from '../init.mjs';
import { loadConfig, readJson } from '../lib.mjs';
import { isCustomPropertyStyle, lintHtml, splitDeclarations } from '../lint-prototype.mjs';
import { NAMING_JSON } from '../naming.mjs';
import { loadSchemas, secretShape, validateSeeds } from '../validate-seeds.mjs';

// ---- init ----

test('init: dirty tree is refused unless --allow-dirty (dry run never checks)', () => {
  assert.equal(dirtyTreeError(''), null);
  assert.match(dirtyTreeError(' M AGENTS.md\n?? x.txt\n'), /2 uncommitted change\(s\).*--allow-dirty/);
  assert.equal(dirtyTreeError(' M AGENTS.md', { allowDirty: true }), null);
  assert.equal(dirtyTreeError(' M AGENTS.md', { dry: true }), null);
});

test('init: a failed rename reports what moved and how to recover', () => {
  const moved = [];
  const fn = (a, b) => {
    if (a.includes('mu-plugins')) throw Object.assign(new Error('busy'), { code: 'EBUSY' });
    moved.push([a, b]);
  };
  const steps = [
    ['wp-content/themes/starter', 'wp-content/themes/acme'],
    ['wp-content/mu-plugins/starter-core', 'wp-content/mu-plugins/acme-core'],
  ];
  assert.throws(
    () => runRenames(steps, fn),
    (err) =>
      err instanceof RenameError &&
      err.message.includes('cannot rename wp-content/mu-plugins/starter-core → wp-content/mu-plugins/acme-core (EBUSY)') &&
      err.message.includes('npm run env:stop') &&
      err.message.includes('Already renamed:\n  wp-content/themes/starter → wp-content/themes/acme') &&
      err.message.includes('git checkout -- . && git clean -fd'),
  );
  assert.equal(moved.length, 1);
  assert.deepEqual(runRenames([['a', 'b']], () => {}), ['a → b']);
});

test('init: leftovers summary uses letter boundaries', () => {
  const from = { prefix: 'starter' };
  const files = [{ path: 'README.md', text: 'restarter\nthe starter kit\nSTARTER-PLAN.md\nstartergy' }];
  const plan = { edits: [], writes: [], deletes: [] };
  assert.deepEqual(leftovers(files, plan, from), ['README.md:2: the starter kit', 'README.md:3: STARTER-PLAN.md']);
});

// ---- fonts:fallback ----

test('fonts: vertical metrics are OS/2 typo only with USE_TYPO_METRICS, else hhea', () => {
  const os2 = { typoAscender: 800, typoDescender: -200, typoLineGap: 100, fsSelection: { useTypoMetrics: false } };
  const base = { unitsPerEm: 1000, ascent: 900, descent: -250, lineGap: 10, 'OS/2': os2 };
  assert.deepEqual(verticalMetrics(base), { source: 'hhea', ascent: 900, descent: -250, lineGap: 10, unitsPerEm: 1000 });
  const typo = { ...base, 'OS/2': { ...os2, fsSelection: { useTypoMetrics: true } } };
  assert.deepEqual(verticalMetrics(typo), { source: 'OS/2 typo', ascent: 800, descent: -200, lineGap: 100, unitsPerEm: 1000 });
});

test('fonts: static file = its usWeightClass, variable = wght range; a missing weight is refused', () => {
  const stat = (w) => ({ 'OS/2': { usWeightClass: w }, variationAxes: {}, directory: { tables: {} } });
  const vf = { 'OS/2': { usWeightClass: 400 }, variationAxes: { wght: { min: 100, max: 900 } }, directory: { tables: { fvar: {} } } };
  assert.deepEqual(fontWeights(stat(700)), { variable: false, min: 700, max: 700, descriptor: '700' });
  assert.deepEqual(fontWeights(stat(400), 500), { variable: false, min: 500, max: 500, descriptor: '500' });
  assert.equal(fontWeights(vf).descriptor, '100 900');
  assert.deepEqual(parseFontArg('a.woff2@400, b.woff2@700'), [
    { file: 'a.woff2', weight: 400 },
    { file: 'b.woff2', weight: 700 },
  ]);
  assert.deepEqual(parseFontArg('fonts/x.woff2'), [{ file: 'fonts/x.woff2', weight: null }]);

  const regular = { file: 'r.woff2', coverage: fontWeights(stat(400)) };
  const bold = { file: 'b.woff2', coverage: fontWeights(stat(700)) };
  assert.deepEqual(
    assignWeights([regular, bold], null).map((p) => [p.weight, p.face.file]),
    [
      [400, 'r.woff2'],
      [700, 'b.woff2'],
    ],
  );
  assert.throws(() => assignWeights([regular], [400, 700]), /weight 700 is not in the font file\(s\) \(r\.woff2: static 400\).*@700/);
  const variable = { file: 'v.woff2', coverage: fontWeights(vf) };
  assert.deepEqual(assignWeights([variable], [300, 800]).map((p) => p.weight), [300, 800]);
  assert.deepEqual(assignWeights([variable], null).map((p) => p.weight), [400]);
  assert.throws(() => assignWeights([variable], [950]), /weight 950/);
});

// ---- check:hardcode ----

const needles = buildNeedles({
  company: { email: 'info@example.com', address: { street: 'Demo Street, 1', postal_code: '220000' } },
  serviceCards: { items: [{ page: '/x/', price: 'от 100' }] },
  urls: {},
});
const scan = (line) => scanText(line, 'x.php', needles);

test('hardcode: text needles respect word boundaries', () => {
  assert.equal(needleRegex('220000').test('x 1220000 y'), false);
  assert.equal(needleRegex('Demo Street, 1').test('<p>Demo Street, 12</p>'), false);
  assert.equal(needleRegex('Demo Street, 1').test('<p>Demo Street, 1</p>'), true);
  assert.equal(needleRegex('от 100').test('от 1000'), false);
  assert.equal(needleRegex('https://t.me/example').test('"https://t.me/example"'), true);
  assert.deepEqual(scan('<b>от 1000</b>'), []);
});

test('hardcode: any e-mail literal fails; reserved sample domains do not', () => {
  assert.match(scan("echo 'sales@client-company.org';")[0], /e-mail literal "sales@client-company\.org"/);
  assert.deepEqual(scan('// e.g. user@example.org or a@b.test'), []);
  assert.equal(scan('<a>info@example.com</a>').length, 1, 'company email reported once, as company data');
  assert.deepEqual(scan(' * @package Demo @param string $x'), []);
});

// ---- lint:prototype ----

test('prototype: style declarations split only on top-level ";"', () => {
  assert.deepEqual(splitDeclarations('--bg: url("a;b.png"); --x: calc(1px + (2px))'), ['--bg: url("a;b.png")', '--x: calc(1px + (2px))']);
  assert.equal(isCustomPropertyStyle("--bg: url('x;y.svg')"), true);
  assert.equal(isCustomPropertyStyle('--bg: url(x;y.svg); color: red'), false);
});

test('prototype: <h1> inside <template> does not count; invalid selectors are errors, not crashes', () => {
  assert.deepEqual(lintHtml('<template><h1>tpl</h1></template><h1>real</h1>').errors, []);
  const page = { url: '/', lead_form: false, lcp: { selector: 'h1::before', kind: 'text' }, above_fold: ['div['] };
  const all = lintHtml('<h1>x</h1>', { page }).errors.join('\n');
  assert.match(all, /invalid lcp selector "h1::before"/);
  assert.match(all, /invalid above_fold selector "div\["/);
});

// ---- check:seeds ----

test('seeds: secret-shaped values are flagged under any key; ordinary text is not', () => {
  const cfg = loadConfig();
  const schemas = loadSchemas(cfg.paths.seed);
  const naming = readJson(NAMING_JSON);
  const botToken = `${'1234567'}:${'Ab1_'.repeat(9)}`;
  const data = {
    reviews: { items: [{ slug: 'r', title: 'R', source_url: 'https://example.org/review/1', author: botToken }] },
    faq: { items: [{ slug: 'very-long-demo-faq-slug-with-many-words-2026', title: 'Q?', content: 'd41d8cd98f00b204e9800998ecf8427e' }] },
  };
  const all = validateSeeds({ data, schemas, pages: [{ url: '/', type: 'front' }], naming }).errors.join('\n');
  assert.match(all, /reviews\.json: value at reviews\.items\[0\]\.author looks like a telegram bot token/);
  assert.match(all, /faq\.json: value at faq\.items\[0\]\.content looks like a long hex key/);
  assert.doesNotMatch(all, /slug|source_url/);
  assert.equal(secretShape('Обычный текст отзыва 2026 года'), null);
  assert.equal(secretShape(`AIza${'x'.repeat(35)}`), 'Google API key');
});
