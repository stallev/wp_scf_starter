/**
 * forms: the lead form contract (core forms.php + theme main.js) in a real browser.
 * happy (stored lead + success state + CustomEvent), negative (client and server validation),
 * security (bad nonce, honeypot, rate limit).
 *
 * Setup / verification / cleanup through WP-CLI (helpers/wp.ts, batched `wp eval`); skipped when
 * WP-CLI does not reach the site under test. Isolation: every test sends its own X-Forwarded-For
 * address from TEST-NET-3 (wp-env's Apache trusts it from the Docker network), so rate-limit buckets
 * never collide. Telegram: the local-only option `<prefix>_e2e_mode` makes the core skip it.
 * Cleanup: test leads and rate-limit transients are deleted; afterAll checks the counts are back.
 */
import { expect, test, type Locator, type Page, type Response } from '@playwright/test';
import { allPages, names } from './helpers/config';
import { WP_UNAVAILABLE, jsonOf, php, testIps, wpAvailable, wpJson } from './helpers/wp';

const formPage = allPages.find((p) => p.lead_form);
const IPS = testIps(8);
const markers: string[] = [];
let keys: Record<string, string> = {};
let rate = { limit: 5, window: 600 };
let baseline = { leads: 0, transients: 0 };
let previousOption: string | null = null;
let telegramSkipped = false;
let nextIp = 0;

const marker = (tag: string) => {
  const m = `e2e-${tag}-${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;
  markers.push(m);
  return m;
};

async function openForm(page: Page): Promise<{ form: Locator; key: string }> {
  const ip = IPS[nextIp++ % IPS.length];
  await page.setExtraHTTPHeaders({ 'X-Forwarded-For': ip });
  await page.route('**/api.telegram.org/**', (route) => route.abort()); // never from the browser either
  await page.goto(formPage!.url, { waitUntil: 'load' });
  const form = page.locator('form.js-lead');
  await expect(form).toHaveCount(1);
  await form.scrollIntoViewIfNeeded();
  return { form, key: keys[ip] };
}

async function submit(page: Page, form: Locator): Promise<Response> {
  const res = page.waitForResponse((r) => r.url().includes('admin-ajax.php') && r.request().method() === 'POST');
  await form.locator('[type="submit"]').click();
  return res;
}

/** Count admin-ajax POSTs fired while `fn` runs (client-side validation must fire none). */
async function countPosts(page: Page, fn: () => Promise<void>): Promise<number> {
  let n = 0;
  const on = (req: { url: () => string; method: () => string }) => {
    if (req.url().includes('admin-ajax.php') && req.method() === 'POST') n++;
  };
  page.on('request', on);
  await fn();
  await page.waitForTimeout(500);
  page.off('request', on);
  return n;
}

/** Leads stored for `contacts` and the rate-limit counter of `key` (one WP-CLI call). */
function stored(contacts: string[], key: string) {
  return wpJson<{ ids: Record<string, number[]>; counter: Record<string, string | false> }>(
    jsonOf({ ids: php.leadIdsByContacts(contacts), counter: php.transients([key]) }),
  );
}

test.describe('forms', { tag: '@forms' }, () => {
  test.describe.configure({ mode: 'serial', timeout: 120_000 });
  test.skip(!formPage, 'no pages-map page with lead_form: true');

  test.beforeAll(() => {
    test.skip(!wpAvailable(), WP_UNAVAILABLE);
    const setup = wpJson<{ leads: number; transients: number; keys: Record<string, string>; rate: { limit: number; window: number }; skip: boolean; previous: string | null }>(
      jsonOf(
        {
          previous: '$prev',
          leads: php.leadCount(),
          transients: php.rateLimitTransientCount(),
          keys: php.rateLimitKeys(IPS),
          rate: `${names.fn('lead_rate_limit_settings')}()`,
          skip: `(bool) apply_filters( '${names.fn('lead_skip_telegram')}', false, 0 )`,
        },
        `$prev = get_option( '${names.e2eOption}', null ); update_option( '${names.e2eOption}', '1', false );`,
      ),
    );
    baseline = { leads: setup.leads, transients: setup.transients };
    keys = setup.keys;
    rate = setup.rate;
    telegramSkipped = setup.skip;
    previousOption = setup.previous;
  });

  test.afterAll(() => {
    if (!wpAvailable()) return;
    const restore = previousOption === null ? `delete_option( '${names.e2eOption}' );` : `update_option( '${names.e2eOption}', ${JSON.stringify(previousOption)}, false );`;
    const after = wpJson<{ deleted: number; leads: number; transients: number }>(
      jsonOf(
        { deleted: php.deleteLeadsByContacts(markers.length ? markers : ['-']), leads: php.leadCount(), transients: php.rateLimitTransientCount() },
        `${php.deleteTransients(Object.values(keys))}; ${restore}`,
      ),
    );
    expect(after.leads, 'leads after the forms suite').toBe(baseline.leads);
    expect(after.transients, 'rate-limit transients after the forms suite').toBe(baseline.transients);
  });

  test('Telegram is skipped in e2e mode (no external calls)', () => {
    expect(telegramSkipped, `${names.fn('lead_skip_telegram')} with option ${names.e2eOption}`).toBe(true);
  });

  test('happy: submit → success state, CustomEvent, stored lead', async ({ page }) => {
    const { form, key } = await openForm(page);

    const lead = await page.evaluate((k) => (window as unknown as Record<string, { action?: string }>)[k], names.leadGlobal);
    expect(lead?.action, `window.${names.leadGlobal}.action`).toBe(names.leadAction);

    await page.evaluate((event) => {
      const w = window as unknown as { e2eLeadEvent: unknown };
      w.e2eLeadEvent = null;
      document.addEventListener(event, (e) => { w.e2eLeadEvent = (e as CustomEvent).detail; }, { once: true });
    }, names.leadEvent);

    const contact = marker('happy');
    const nameField = form.locator('[name="name"]');
    if (await nameField.count()) await nameField.fill('E2E');
    await form.locator('[name="contact"]').fill(contact);
    await form.locator('[name="consent"]').check();

    const res = await submit(page, form);
    expect(res.status()).toBe(200);
    const json = await res.json();
    expect(json.success).toBe(true);
    expect(json.data?.accepted).toBe(true);

    await expect(form).toHaveClass(/\bis-sent\b/);
    await expect(form.locator('.lead-form__done')).toBeVisible();

    const source = await form.locator('[name="source"]').inputValue();
    const detail = await page.evaluate(() => (window as unknown as { e2eLeadEvent: unknown }).e2eLeadEvent);
    expect(detail, `${names.leadEvent} CustomEvent`).toEqual({ formId: (await form.getAttribute('id')) ?? '', source });

    const s = stored([contact], key);
    expect(s.ids[contact], 'stored lead with the submitted contact').toHaveLength(1);
    expect(String(s.counter[key]), 'rate-limit counter of this client').toBe('1');
  });

  test('negative (client): empty required field → error, no request', async ({ page }) => {
    const { form } = await openForm(page);
    await form.locator('[name="consent"]').check();
    const posts = await countPosts(page, () => form.locator('[type="submit"]').click());
    expect(posts).toBe(0);
    await expect(form).not.toHaveClass(/\bis-sent\b/);
    await expect(form).toHaveClass(/\bis-error\b/);
    await expect(form.locator('.lead-form__error')).not.toBeEmpty();
    await expect(form.locator('[name="contact"]')).toHaveAttribute('aria-invalid', 'true');
  });

  test('negative (client): consent unchecked → error, no request', async ({ page }) => {
    const { form } = await openForm(page);
    await form.locator('[name="contact"]').fill(marker('noconsent'));
    const posts = await countPosts(page, () => form.locator('[type="submit"]').click());
    expect(posts).toBe(0);
    await expect(form.locator('.lead-form__error')).not.toBeEmpty();
    await expect(form.locator('[name="consent"]')).toHaveAttribute('aria-invalid', 'true');
  });

  test('negative (server): consent / contact missing → 400 with the server message shown', async ({ page }) => {
    const { form, key } = await openForm(page);
    // Bypass client validation to reach the server rules.
    await form.evaluate((f) => f.querySelectorAll('[required]').forEach((el) => el.removeAttribute('required')));

    const contact = marker('srv-consent');
    await form.locator('[name="contact"]').fill(contact);
    let res = await submit(page, form);
    expect(res.status()).toBe(400);
    let json = await res.json();
    expect(json.data?.code).toBe('consent');
    await expect(form.locator('.lead-form__error')).toHaveText(json.data.message);

    await form.locator('[name="contact"]').fill('');
    await form.locator('[name="consent"]').check();
    res = await submit(page, form);
    expect(res.status()).toBe(400);
    json = await res.json();
    expect(json.data?.code).toBe('contact');
    await expect(form.locator('.lead-form__error')).toHaveText(json.data.message);

    const s = stored([contact], key);
    expect(s.ids[contact]).toEqual([]);
    expect(s.counter[key]).toBe(false);
  });

  test('security: tampered nonce → 403, error shown, nothing stored', async ({ page }) => {
    const { form, key } = await openForm(page);
    const contact = marker('nonce');
    await form.locator(`[name="${names.leadNonceField}"]`).evaluate((el) => { (el as HTMLInputElement).value = 'tampered'; });
    await form.locator('[name="contact"]').fill(contact);
    await form.locator('[name="consent"]').check();
    const res = await submit(page, form);
    expect(res.status()).toBe(403);
    expect((await res.json()).data?.code).toBe('nonce');
    await expect(form).not.toHaveClass(/\bis-sent\b/);
    await expect(form.locator('.lead-form__error')).not.toBeEmpty();
    expect(stored([contact], key).ids[contact]).toEqual([]);
  });

  test('security: honeypot filled → looks like success, nothing stored', async ({ page }) => {
    const { form, key } = await openForm(page);
    const contact = marker('honeypot');
    await form.locator(`[name="${names.honeypot}"]`).evaluate((el) => { (el as HTMLInputElement).value = 'Bot Inc'; });
    await form.locator('[name="contact"]').fill(contact);
    await form.locator('[name="consent"]').check();
    const res = await submit(page, form);
    expect(res.status()).toBe(200);
    const json = await res.json();
    expect(json.success).toBe(true);
    expect(json.data?.accepted).toBe(true);
    await expect(form).toHaveClass(/\bis-sent\b/);
    const s = stored([contact], key);
    expect(s.ids[contact], 'honeypot lead must not be stored').toEqual([]);
    expect(s.counter[key], 'honeypot does not count against the limit').toBe(false);
  });

  test('security: rate limit reached → 429 message, nothing stored', async ({ page }) => {
    const { form, key } = await openForm(page);
    // This client already sent `limit` leads.
    wpJson(jsonOf({ set: `set_transient( ${JSON.stringify(key)}, ${rate.limit}, ${rate.window} )` }));
    const contact = marker('ratelimit');
    await form.locator('[name="contact"]').fill(contact);
    await form.locator('[name="consent"]').check();
    const res = await submit(page, form);
    expect(res.status()).toBe(429);
    const json = await res.json();
    expect(json.data?.code).toBe('rate_limit');
    await expect(form.locator('.lead-form__error')).toHaveText(json.data.message);
    const s = stored([contact], key);
    expect(s.ids[contact]).toEqual([]);
    expect(String(s.counter[key]), 'counter unchanged by the rejected submit').toBe(String(rate.limit));
  });
});
