<?php
/**
 * Load seed JSON files and reject secrets.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Decode a seed JSON file.
 *
 * @param string $filename File name relative to the seed directory.
 * @return array<string, mixed>|WP_Error Error codes: starter_seed_missing, starter_seed_invalid_json, starter_seed_forbidden.
 */
function starter_seed_load_json( string $filename ) {
	$path = starter_seed_file( $filename );

	if ( ! is_readable( $path ) ) {
		return new WP_Error( 'starter_seed_missing', sprintf( 'Seed file not found: %s', $filename ) );
	}

	$raw  = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
	$data = json_decode( $raw, true );

	if ( ! is_array( $data ) ) {
		return new WP_Error( 'starter_seed_invalid_json', sprintf( 'Invalid JSON in %s: %s', $filename, json_last_error_msg() ) );
	}

	$forbidden = starter_seed_find_forbidden( $data );
	if ( $forbidden ) {
		return new WP_Error( 'starter_seed_forbidden', sprintf( 'Forbidden content in %s: %s', $filename, implode( ', ', $forbidden ) ) );
	}

	return $data;
}

/**
 * Keys that must never appear in seed data (secrets belong in .env / protected options). Separate
 * from naming.json — these are secret-shaped key names, not naming/legacy identifiers.
 *
 * @return string[]
 */
function starter_seed_forbidden_keys(): array {
	$keys = array( 'token', 'bot_token', 'password', 'pass', 'secret', 'api_key', 'apikey', 'chat_id', 'private_key' );

	/**
	 * Filter forbidden seed keys (lower-case).
	 *
	 * @param string[] $keys Keys.
	 */
	return array_map( 'strtolower', (array) apply_filters( 'starter_seed_forbidden_keys', $keys ) );
}

/**
 * `naming.json` `forbidden` entries applicable to seed files, from the config snapshot
 * (`config.generated.php`, built by `npm run build:config`) — `docs/contracts/naming.json` itself is
 * not deployed (repo root). `npm run check:naming` scans naming.json and the whole repo directly and
 * stays the source of truth; this is the runtime backstop for `wp starter seed` / Tools → Starter Seed.
 *
 * @return array<int, array{id: string, pattern: string, flags: string, reason: string, replace: string}>
 */
function starter_seed_naming_forbidden_rules(): array {
	$rules = starter_core_config( 'naming.forbidden_seed' );

	return is_array( $rules ) ? $rules : array();
}

/**
 * Whether a string matches a naming-forbidden rule's pattern (PCRE; same pattern naming.mjs builds).
 *
 * @param array{pattern: string, flags: string} $rule  Rule.
 * @param string                                $value Key or scalar value to test.
 * @return bool
 */
function starter_seed_naming_rule_matches( array $rule, string $value ): bool {
	$delimiter = '#';
	$pattern   = $delimiter . str_replace( $delimiter, '\\' . $delimiter, (string) $rule['pattern'] ) . $delimiter . (string) $rule['flags'];

	return 1 === preg_match( $pattern, $value );
}

/**
 * Recursively find forbidden keys (secret-shaped names) and naming.json forbidden names/patterns, in
 * both keys and string values.
 *
 * @param array<mixed> $data   Decoded JSON.
 * @param string       $prefix Path prefix for messages.
 * @return string[] Paths of hits, each suffixed with the rule id in brackets for naming.json matches.
 */
function starter_seed_find_forbidden( array $data, string $prefix = '' ): array {
	$forbidden_keys = starter_seed_forbidden_keys();
	$naming_rules   = starter_seed_naming_forbidden_rules();
	$hits           = array();

	foreach ( $data as $key => $value ) {
		$path = '' === $prefix ? (string) $key : $prefix . '.' . $key;

		if ( is_string( $key ) && in_array( strtolower( $key ), $forbidden_keys, true ) ) {
			$hits[] = $path;
		}

		foreach ( $naming_rules as $rule ) {
			if ( is_string( $key ) && starter_seed_naming_rule_matches( $rule, $key ) ) {
				$hits[] = sprintf( '%s [%s]', $path, $rule['id'] );
			}
			if ( is_string( $value ) && starter_seed_naming_rule_matches( $rule, $value ) ) {
				$hits[] = sprintf( '%s [%s]', $path, $rule['id'] );
			}
		}

		if ( is_array( $value ) ) {
			$hits = array_merge( $hits, starter_seed_find_forbidden( $value, $path ) );
		}
	}

	return $hits;
}

/**
 * `items` list of a seed file.
 *
 * @param array<string, mixed> $data Decoded JSON.
 * @return array<int, array<string, mixed>>
 */
function starter_seed_items( array $data ): array {
	$items = $data['items'] ?? array();

	return is_array( $items ) ? array_values( array_filter( $items, 'is_array' ) ) : array();
}
