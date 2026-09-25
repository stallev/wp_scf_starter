import assert from 'node:assert/strict';
import { test } from 'node:test';
import { isCustomPropertyStyle, lintDir, lintHtml } from '../lint-prototype.mjs';

const cfg = { paths: { prototype: 'prototype', pages_map: 'pages-map.json' } };

test('demo prototype fixture passes', () => {
  const res = lintDir('fixtures/demo-prototype', { cfg, pages: [] });
  assert.ok(res.files.length >= 2);
  assert.deepEqual(res.errors, []);
  assert.deepEqual(res.warnings, []);
});

test('bad prototype fixture fails on every rule', () => {
  const { errors, warnings } = lintDir('fixtures/bad-prototype', { cfg, pages: [] });
  const all = errors.join('\n');
  for (const re of [
    /inline style="color: red/,
    /inline event handler onclick=/,
    /external font CDN \(https:\/\/fonts\.googleapis\.com/,
    /stylesheet "https:\/\/cdn\.example\.org\/theme\.css" is not a local/,
    /<style> imports remote CSS/,
    /<img src="photo\.jpg"> without width, height, alt/,
    /2 <h1> on the page/,
    /<h1> is inside \.reveal/,
    /2 form\.js-lead on the page/,
  ]) {
    assert.match(all, re);
  }
  assert.match(warnings.join('\n'), /non-BEM class names: Second_Heading/);
});

test('pages-map mapping: lead_form, LCP and above_fold', () => {
  const html = `<!doctype html><html><body>
    <section class="hero"><div class="reveal"><h2 class="hero__title">x</h2></div></section>
    <h1 class="page-head__title">T</h1>
    <section class="promo reveal"></section>
  </body></html>`;
  const page = { url: '/x/', lead_form: true, lcp: { selector: 'h2.hero__title', kind: 'text' }, above_fold: ['.promo'] };
  const all = lintHtml(html, { file: 'x.html', page }).errors.join('\n');
  assert.match(all, /lead_form=true, found 0 form\.js-lead/);
  assert.match(all, /LCP "h2\.hero__title" is inside \.reveal/);
  assert.match(all, /first-screen block "\.promo" is \(inside\) \.reveal/);

  const missing = lintHtml('<h1>t</h1>', { page: { url: '/', lead_form: false, lcp: { selector: '.nope', kind: 'text' } } }).errors;
  assert.match(missing.join('\n'), /lcp selector "\.nope" not found/);
});

test('custom-property-only inline style is allowed', () => {
  assert.equal(isCustomPropertyStyle('--d: 120ms'), true);
  assert.equal(isCustomPropertyStyle('--d:120ms; --x: calc(1px + 2px);'), true);
  assert.equal(isCustomPropertyStyle('--d: 1ms; color: red'), false);
  assert.equal(isCustomPropertyStyle('display:none'), false);
});

test('img alt="" is fine, missing width is not', () => {
  assert.deepEqual(lintHtml('<h1>t</h1><img src="a.svg" width="10" height="10" alt="">').errors, []);
  assert.match(lintHtml('<h1>t</h1><img src="a.svg" width="" height="10" alt="x">').errors[0], /without width/);
});
