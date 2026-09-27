import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { test } from 'node:test';
import {
  annotateHardcode,
  buildDraft,
  buildLinkMap,
  extractMainHtml,
  findPage,
  flagSrcsetForManualRewrite,
  neutralizeEmbeddedPhp,
  portPage,
  rewriteLinks,
} from '../port-page.mjs';
import { ROOT } from '../lib.mjs';

// A small pages-map-like fixture pointing at fixtures/demo-prototype/*.html. Does not touch the real
// pages-map.json (T3 forbids mutating it for tests).
const pages = [
  { url: '/', prototype: 'index.html', template: 'front-page.php', lead_form: true },
  { url: '/service/', prototype: 'service.html', template: 'page-service.php', lead_form: true },
  { url: '/contacts/', prototype: 'contacts.html', template: 'page-contacts-test.php', lead_form: true },
  { url: '/blog/post/', prototype: 'post.html', template: 'single-test.php', lead_form: true },
  { url: '/no-prototype/', prototype: null, template: 'page-none.php', lead_form: false },
];

const cfg = { paths: { prototype: 'fixtures/demo-prototype', pages_map: 'pages-map.json' }, slug: { theme: 'starter' }, urls: {} };
const needles = { phones: [], texts: [], origins: [] }; // no company data in this fixture cfg

test('findPage', () => {
  assert.equal(findPage(pages, '/service/').template, 'page-service.php');
  assert.equal(findPage(pages, '/nope/'), null);
});

test('extractMainHtml pulls <main> content and throws without one', () => {
  const html = extractMainHtml('<html><body><main id="main"><p>hi</p></main></body></html>');
  assert.match(html, /<p>hi<\/p>/);
  assert.throws(() => extractMainHtml('<html><body><p>no main</p></body></html>'), /no <main>/);
});

test('buildLinkMap maps prototype filename -> pages-map url, ignoring null prototypes', () => {
  const map = buildLinkMap(pages);
  assert.equal(map.get('index.html'), '/');
  assert.equal(map.get('service.html'), '/service/');
  assert.equal(map.size, 4);
});

test('rewriteLinks rewrites mapped *.html links and assets/, leaves unmapped links alone', () => {
  const map = buildLinkMap(pages);
  const out = rewriteLinks(
    '<a href="index.html">home</a><a href="service.html#lead">svc</a><a href="unmapped.html">x</a>' +
      '<img src="assets/images/a.png" width="1" height="1" alt="">',
    map,
  );
  assert.match(out, /<a href="<\?php echo esc_url\( starter_url\( '\/' \) \); \?>">home<\/a>/);
  assert.match(out, /<a href="<\?php echo esc_url\( starter_url\( '\/service\/' \) \); \?>#lead">svc<\/a>/);
  assert.match(out, /<a href="unmapped\.html">x<\/a>/);
  assert.match(out, /<img src="<\?php echo esc_url\( starter_asset_url\( 'assets\/images\/a\.png' \) \); \?>"/);
});

test('rewriteLinks leaves a link with a query string unmapped (intentional — no reliable base file)', () => {
  const map = buildLinkMap(pages);
  const out = rewriteLinks('<a href="service.html?x=1">svc</a>', map);
  assert.match(out, /<a href="service\.html\?x=1">svc<\/a>/);
});

test('srcset is never rewritten by rewriteLinks; flagSrcsetForManualRewrite adds a TODO above it', () => {
  const map = buildLinkMap(pages);
  const html = '<img src="assets/a.jpg" srcset="assets/a.jpg 1x, assets/a@2x.jpg 2x" width="1" height="1" alt="">';
  const rewritten = rewriteLinks(html, map);
  // src is rewritten, srcset is untouched (still the raw comma-separated candidate list).
  assert.match(rewritten, /<img src="<\?php echo esc_url\( starter_asset_url\( 'assets\/a\.jpg' \) \); \?>" srcset="assets\/a\.jpg 1x, assets\/a@2x\.jpg 2x"/);
  const flagged = flagSrcsetForManualRewrite(rewritten);
  const lines = flagged.split('\n');
  assert.match(lines[0], /^<!-- TODO\(port-page\): srcset references assets\//);
  assert.match(lines[1], /srcset="assets\/a\.jpg 1x, assets\/a@2x\.jpg 2x"/);
  // A srcset with no local asset reference is not flagged.
  assert.equal(flagSrcsetForManualRewrite('<img srcset="https://cdn.example.com/a.jpg 1x">'), '<img srcset="https://cdn.example.com/a.jpg 1x">');
});

test('neutralizeEmbeddedPhp escapes literal PHP tags in text but never touches attribute values', () => {
  const out = neutralizeEmbeddedPhp('<p>before <?php echo \'x\'; ?> after <?= 1 ?> end</p><a href="x.html?a=<?php">go</a>');
  assert.match(out, /before &lt;\?php echo 'x'; \?&gt; after &lt;\?= 1 \?&gt; end/);
  // Attribute values are untouched by design (documented gap in the file header) — only text nodes are
  // scanned, so the literal "<?php" inside this href survives verbatim.
  assert.match(out, /href="x\.html\?a=<\?php">/);
  assert.equal(neutralizeEmbeddedPhp('<p>plain text, no php</p>'), '<p>plain text, no php</p>');
});

test('annotateHardcode inserts a TODO comment above every flagged line, none when clean', () => {
  const html = 'line one\n<a href="tel:+0000000000000">call</a>\nline three';
  const out = annotateHardcode(html, { phones: [], texts: [], origins: [] }, 'x.php');
  const lines = out.split('\n');
  assert.equal(lines[0], 'line one');
  assert.match(lines[1], /^<!-- TODO\(hardcode\): literal tel: link/);
  assert.equal(lines[2], '<a href="tel:+0000000000000">call</a>');
  assert.equal(annotateHardcode('clean', { phones: [], texts: [], origins: [] }, 'x.php'), 'clean');
});

test('buildDraft wraps body in get_header()/get_footer() with a TODO banner', () => {
  const draft = buildDraft({ url: '/x/', template: 'page-x.php', body: '<p>body</p>' });
  assert.match(draft, /^<\?php/);
  assert.match(draft, /get_header\(\);/);
  assert.match(draft, /<main id="main" class="site-main">/);
  assert.match(draft, /<p>body<\/p>/);
  assert.match(draft, /get_footer\(\);/);
  assert.match(draft, /gate:page -- \/x\//);
});

test('portPage: missing pages-map entry exits with code 2', () => {
  assert.throws(() => portPage({ url: '/missing/', cfg, pages, needles, write: false }), (err) => {
    assert.match(err.message, /no such URL/);
    assert.equal(err.exitCode, 2);
    return true;
  });
});

test('portPage: prototype: null exits with code 2', () => {
  assert.throws(() => portPage({ url: '/no-prototype/', cfg, pages, needles, write: false }), (err) => {
    assert.match(err.message, /prototype: null/);
    assert.equal(err.exitCode, 2);
    return true;
  });
});

test('portPage: produces a well-formed draft for the demo-prototype fixture (no write)', () => {
  const res = portPage({ url: '/contacts/', cfg, pages, needles, write: false });
  assert.equal(res.page.template, 'page-contacts-test.php');
  assert.match(res.content, /get_header\(\);/);
  assert.match(res.content, /get_footer\(\);/);
  assert.match(res.content, /<h1 class="page-head__title">Контакты<\/h1>/);
  // The fixture's nav links (index.html, service.html, ...) live in <header>, outside <main>, so the
  // draft body — which is <main> only, header/footer come from get_header()/get_footer() — never sees
  // them here; rewriteLinks is covered directly against fragments that do contain such links above.
  // tel:/mailto: literals in the fixture are flagged.
  assert.match(res.content, /TODO\(hardcode\): literal tel: link/);
  assert.match(res.content, /TODO\(hardcode\): literal mailto: link/);
});

test('portPage: writes the draft to disk, refuses to overwrite without --force, --dry-run writes nothing', () => {
  const themeRoot = mkdtempSync(path.join(tmpdir(), 'port-page-theme-'));
  try {
    const res = portPage({ url: '/service/', cfg, pages, needles, themeRoot });
    assert.equal(res.wouldOverwrite, false);
    const onDisk = readFileSync(path.join(themeRoot, 'page-service.php'), 'utf8');
    assert.equal(onDisk, res.content);

    assert.throws(() => portPage({ url: '/service/', cfg, pages, needles, themeRoot }), (err) => {
      assert.match(err.message, /already exists/);
      assert.equal(err.exitCode, 1);
      return true;
    });

    const forced = portPage({ url: '/service/', cfg, pages, needles, themeRoot, force: true });
    assert.equal(forced.wouldOverwrite, true);

    const dryRunFile = path.join(themeRoot, 'front-page.php');
    const before = existsSyncSafe(dryRunFile);
    assert.equal(before, false);
    portPage({ url: '/', cfg, pages, needles, themeRoot, dryRun: true });
    assert.equal(existsSyncSafe(dryRunFile), false, '--dry-run must write nothing');
  } finally {
    rmSync(themeRoot, { recursive: true, force: true });
  }
});

test('portPage: --dry-run still previews (no throw) when the target already exists and --force is not given', () => {
  const themeRoot = mkdtempSync(path.join(tmpdir(), 'port-page-theme-'));
  try {
    const first = portPage({ url: '/service/', cfg, pages, needles, themeRoot });
    const onDiskBefore = readFileSync(first.themeFile, 'utf8');

    // No --force, but --dry-run: must preview, not throw, and must not touch the existing file.
    const preview = portPage({ url: '/service/', cfg, pages, needles, themeRoot, dryRun: true });
    assert.equal(preview.wouldOverwrite, true);
    assert.equal(preview.content, first.content);
    assert.equal(readFileSync(first.themeFile, 'utf8'), onDiskBefore);
  } finally {
    rmSync(themeRoot, { recursive: true, force: true });
  }
});

test('portPage: --prototype-dir may be an absolute path (path.resolve, not path.join)', () => {
  const absoluteProtoDir = path.resolve(ROOT, 'fixtures/demo-prototype');
  const res = portPage({ url: '/service/', cfg, pages, needles, prototypeDir: absoluteProtoDir, write: false });
  assert.equal(res.protoFile, path.resolve(absoluteProtoDir, 'service.html'));
  assert.match(res.content, /Демо-услуга/);
});

function existsSyncSafe(p) {
  try {
    readFileSync(p);
    return true;
  } catch {
    return false;
  }
}
