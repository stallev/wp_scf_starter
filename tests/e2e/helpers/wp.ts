/**
 * WP-CLI for test setup / verification / cleanup through tools/wp-env.mjs (`run cli wp …`:
 * `docker exec` into the running wp-env cli container, falls back to `wp-env run`).
 *
 * Every `wp` call boots WordPress (seconds on Docker Desktop for Windows), so helpers batch work
 * into one `wp eval` that prints JSON (`wpJson`). PHP names come from `names` (prefix-aware).
 *
 * Only usable when the WP-CLI site is the site under test (siteurl origin == baseURL origin):
 * otherwise `wpAvailable()` is false and callers skip with a reason.
 */
import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { ROOT, baseOrigin, names } from './config';

export interface WpResult {
  ok: boolean;
  stdout: string;
  stderr: string;
}

/** Run `wp <args…>` (no shell: every arg is passed as is). */
export function wp(args: string[], timeoutMs = 180_000): WpResult {
  const res = spawnSync(process.execPath, [path.join(ROOT, 'tools', 'wp-env.mjs'), 'run', 'cli', 'wp', ...args], {
    cwd: ROOT,
    encoding: 'utf8',
    timeout: timeoutMs,
    env: { ...process.env, MSYS_NO_PATHCONV: '1' },
  });
  return { ok: res.status === 0, stdout: (res.stdout ?? '').trim(), stderr: (res.stderr ?? '').trim() || (res.error?.message ?? '') };
}

/** `wp eval '<php>'` whose PHP ends with `echo wp_json_encode(…)`; returns the parsed JSON. */
export function wpJson<T>(php: string): T {
  const r = wp(['eval', php]);
  const line = r.stdout.split(/\r?\n/).filter(Boolean).pop() ?? '';
  if (!r.ok || !line) throw new Error(`wp eval failed: ${r.stderr || r.stdout}`);
  return JSON.parse(line) as T;
}

let available: boolean | null = null;
/** WP-CLI reaches the site under test. Cached per worker. */
export function wpAvailable(): boolean {
  if (available === null) {
    const r = wp(['option', 'get', 'siteurl'], 120_000);
    available = r.ok && safeOrigin(r.stdout.split(/\r?\n/).pop() ?? '') === baseOrigin;
  }
  return available;
}
export const WP_UNAVAILABLE = `WP-CLI does not reach ${baseOrigin} (wp-env not running or PLAYWRIGHT_BASE_URL points to another site)`;

function safeOrigin(url: string): string | null {
  try {
    return new URL(url.trim()).origin;
  } catch {
    return null;
  }
}

export const getOption = (name: string): string | null => {
  const r = wp(['option', 'get', name]);
  return r.ok ? r.stdout : null;
};

/* ---- PHP snippets for wpJson (compose several into one call) ---- */

const q = (s: string) => JSON.stringify(s); // a JSON string is a valid PHP double-quoted string for our ASCII values
const qList = (list: string[]) => `array(${list.map(q).join(',')})`;

export const php = {
  /** Number of stored leads (any status). */
  leadCount: () =>
    `(int) array_sum( (array) wp_count_posts( ${q(names.leadPostType)} ) )`,
  /** Number of rate-limit transients. */
  rateLimitTransientCount: () =>
    `(int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->options} WHERE option_name LIKE %s", $GLOBALS['wpdb']->esc_like( ${q(`_transient_${names.rateLimitTransientPrefix}`)} ) . '%' ) )`,
  /** Lead IDs by contact value: { contact: ids[] }. */
  leadIdsByContacts: (contacts: string[]) =>
    `array_combine( ${qList(contacts)}, array_map( function ( $c ) { return get_posts( array( 'post_type' => ${q(names.leadPostType)}, 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1, 'meta_key' => ${q(names.leadContactMeta)}, 'meta_value' => $c ) ); }, ${qList(contacts)} ) ?: array() )`,
  /** Delete leads (bypass trash) by contact values; returns the number deleted. */
  deleteLeadsByContacts: (contacts: string[]) =>
    `array_sum( array_map( function ( $c ) { $n = 0; foreach ( get_posts( array( 'post_type' => ${q(names.leadPostType)}, 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1, 'meta_key' => ${q(names.leadContactMeta)}, 'meta_value' => $c ) ) as $id ) { $n += wp_delete_post( $id, true ) ? 1 : 0; } return $n; }, ${qList(contacts)} ) )`,
  /** Core's rate-limit transient key per client IP: { ip: key }. */
  rateLimitKeys: (ips: string[]) =>
    `array_combine( ${qList(ips)}, array_map( function ( $ip ) { $_SERVER['REMOTE_ADDR'] = $ip; return ${names.fn('lead_rate_limit_key')}(); }, ${qList(ips)} ) )`,
  /** Transient values by key: { key: value|false }. */
  transients: (keys: string[]) =>
    `array_combine( ${qList(keys)}, array_map( 'get_transient', ${qList(keys)} ) ?: array() )`,
  /** Delete transients; returns the count deleted. */
  deleteTransients: (keys: string[]) => `count( array_filter( array_map( 'delete_transient', ${qList(keys)} ) ) )`,
};

/** Build `echo wp_json_encode( array( 'k' => <php>, … ) );` preceded by optional statements. */
export function jsonOf(fields: Record<string, string>, statements = ''): string {
  const body = Object.entries(fields).map(([k, v]) => `${q(k)} => ${v}`).join(', ');
  return `${statements} echo wp_json_encode( array( ${body} ) );`;
}

/** Unique TEST-NET-3 addresses (RFC 5737) for isolated rate-limit buckets; wp-env trusts X-Forwarded-For. */
export function testIps(n: number): string[] {
  const start = 1 + Math.floor(Math.random() * (254 - n));
  return Array.from({ length: n }, (_, i) => `203.0.113.${start + i}`);
}
