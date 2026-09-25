import assert from 'node:assert/strict';
import { test } from 'node:test';
import { loadConfig, readJson } from '../lib.mjs';
import { NAMING_JSON } from '../naming.mjs';
import { loadSchemas, readSeedDir, validateSeeds } from '../validate-seeds.mjs';

const cfg = loadConfig();
const schemas = loadSchemas(cfg.paths.seed);
const naming = readJson(NAMING_JSON);
const pages = [
  { url: '/', type: 'front' },
  { url: '/services/', type: 'page' },
  { url: '/services/cleaning/', type: 'service' },
  { url: '/contacts/', type: 'page' },
];

test('repository seed passes', () => {
  const { data, errors: parseErrors } = readSeedDir(cfg.paths.seed);
  assert.deepEqual(parseErrors, []);
  const res = validateSeeds({ data, schemas, pages: readJson(cfg.paths.pages_map).pages, naming, seedDir: cfg.paths.seed });
  assert.deepEqual(res.errors, []);
});

test('bad sample: schema, slugs, images, references, secrets, forbidden names', () => {
  const legacy = 't' + 'b_product'; // assembled so that check:naming does not flag this test file
  const data = {
    company: { name: 'X', phones: [{ number: '+1 000' }], default_image: 'images/missing.png' },
    faq: { items: [{ slug: 'q', title: 'Q?', location: 'nowhere' }, { slug: 'q', title: 'Q2?' }] },
    reviews: { items: [{ slug: 'r', title: 'R', rating: 7, token: 'abc' }] },
    projects: { items: [{ slug: 'p', title: 'P', service: 'unknown-service', image: '../../wp-config.php' }] },
    posts: { items: [{ slug: 'contacts', title: 'Clash', content: `uses ${legacy}` }] },
    'service-cards': { items: [{ page: '/services/missing/' }] },
    menus: { menus: [{ name: 'Main', items: [{ title: 'A', page: '/nope/' }, { title: 'B', post: 'no-such-post' }, { title: 'C' }] }] },
  };
  const { errors, warnings } = validateSeeds({ data, schemas, pages, naming, imageExists: () => false });
  const all = errors.join('\n');
  assert.match(all, /reviews\.json: secret-like key "token" at reviews\.items\[0\]\.token/);
  assert.match(all, /company\.json: default_image "images\/missing\.png" not found/);
  assert.match(all, /faq\.json: items\[1\]\.slug "q" duplicates items\[0\]/);
  assert.match(all, /reviews\.json\/items\/0\/rating: must be <= 5/);
  assert.match(all, /projects\.json\/items\/0\/image: must match pattern/);
  assert.match(all, /posts\.json: items\[0\]\.slug "contacts" collides with the page \/contacts\//);
  assert.match(all, new RegExp(`posts\\.json: forbidden "${legacy.slice(0, 4)}" \\[legacy-prefix\\]`));
  assert.match(all, /service-cards\.json: items\[0\]\.page "\/services\/missing\/" is not in pages-map/);
  assert.match(all, /menus\.json\/menus\/0\/items\/2: must match exactly one schema in oneOf/);
  // menus failed the schema (item C has no target), so its references are not cross-checked.
  assert.doesNotMatch(all, /menus\.json: menus\[0\]/);
  assert.match(warnings.join('\n'), /faq\.json: items\[0\]\.location "nowhere"/);
});

test('menu references are checked against pages-map and posts', () => {
  const data = {
    posts: { items: [{ slug: 'hello', title: 'Hello' }] },
    menus: { menus: [{ name: 'Main', items: [{ title: 'A', page: '/nope/', children: [{ title: 'B', post: 'missing' }, { title: 'C', post: 'hello' }] }] }] },
  };
  const { errors, warnings } = validateSeeds({ data, schemas, pages, naming });
  assert.deepEqual(errors, [
    'seed/menus.json: menus[0].items[0].page "/nope/" is not in pages-map.json → add the page or use url',
    'seed/menus.json: menus[0].items[0].children[0].post "missing" is not a slug in posts.json',
  ]);
  assert.equal(warnings.filter((w) => w.includes('missing — target skipped')).length, 5);
});
