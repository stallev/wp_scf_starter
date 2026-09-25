import assert from 'node:assert/strict';
import { test } from 'node:test';
import { ALLOW_MARKER, buildNeedles, scanText } from '../check-hardcode.mjs';

const needles = buildNeedles({
  company: {
    name: 'Demo',
    legal_name: 'Demo Company LLC',
    phones: [{ number: '+000 00 000-00-00' }, { number: '+375 (29) 123-45-67' }],
    email: 'info@example.com',
    address: { street: 'Demo Street, 1', postal_code: '220000', text: '220000, Demo City, Demo Street, 1' },
    socials: [{ network: 'telegram', url: 'https://t.me/example' }],
  },
  serviceCards: { items: [{ page: '/services/x/', price: 'от 100' }, { page: '/services/y/', price: 'по запросу' }] },
  urls: { local: 'http://localhost:8888', staging: null, production: 'https://www.client-site.test' },
});

const scan = (line) => scanText(line, 'x.php', needles);

test('clean template code passes', () => {
  assert.deepEqual(scan(`<a href="<?php echo esc_url( starter_tel_href() ); ?>"><?php echo esc_html( $phone ); ?></a>`), []);
  assert.deepEqual(scan(`<a href="<?php echo esc_url( 'mailto:' . $email ); ?>">`), []);
  assert.deepEqual(scan(`if ( /^tel:/i.test( href ) ) {`), []);
  assert.deepEqual(scan('@package Demo'), [], 'short brand name is not a needle');
});

test('company phone is found in any formatting', () => {
  for (const line of ['+375 29 123 45 67', '375291234567', '(29) 123-45-67', '+375-29-1234567']) {
    assert.equal(scan(`<span>${line}</span>`).length, 1, line);
  }
  assert.match(scan('<span>+000 00 000-00-00</span>')[0], /company phone/);
});

test('literal tel: / mailto: links fail even with unknown data', () => {
  assert.match(scan('<a href="tel:+1234567">call</a>')[0], /literal tel:/);
  assert.match(scan("<a href='mailto:someone@else.org'>")[0], /literal mailto:/);
});

test('email, address, legal name, socials and prices are needles', () => {
  assert.match(scan('echo "INFO@example.com";')[0], /email/);
  assert.match(scan('<p>Demo Street, 1</p>')[0], /street/);
  assert.match(scan('<p>Demo Company LLC</p>')[0], /legal name/);
  assert.match(scan('<p>220000</p>')[0], /postal code/);
  assert.deepEqual(scan('<p>order 1220000 5</p>'), [], 'digit-only needles do not match inside longer numbers');
  assert.match(scan('<a href="https://t.me/example">')[0], /social telegram/);
  assert.match(scan('<b>от 100</b>')[0], /price/);
  assert.deepEqual(scan('<b>по запросу</b>'), [], 'price without digits is not a needle');
});

test("absolute links to the site's own origin fail", () => {
  assert.match(scan('<a href="https://client-site.test/contacts/">')[0], /own origin client-site\.test/);
  assert.match(scan('<img src="//www.client-site.test/logo.png">')[0], /own origin/);
  assert.match(scan("fetch('http://localhost:8888/wp-json/')")[0], /own origin localhost:8888/);
  assert.deepEqual(scan('<a href="https://client-site.test.evil.org/">'), []);
});

test(`${ALLOW_MARKER} skips the line; line numbers are reported`, () => {
  assert.deepEqual(scan(`// sample: tel:+1234567 ${ALLOW_MARKER}`), []);
  const res = scanText('ok\n<a href="tel:+1234567">', 'theme/footer.php', needles);
  assert.match(res[0], /^theme\/footer\.php:2: /);
});
