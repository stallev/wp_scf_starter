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
		return new WP_Error( 'starter_seed_forbidden', sprintf( 'Forbidden keys in %s: %s', $filename, implode( ', ', $forbidden ) ) );
	}

	return $data;
}

/**
 * Keys that must never appear in seed data (secrets belong in .env / protected options).
 *
 * TODO(M5/M6): also read forbidden names from docs/contracts/naming.json via tools/validate-seeds.
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
 * Recursively find forbidden keys.
 *
 * @param array<mixed> $data   Decoded JSON.
 * @param string       $prefix Path prefix for messages.
 * @return string[] Paths of forbidden keys.
 */
function starter_seed_find_forbidden( array $data, string $prefix = '' ): array {
	$forbidden = starter_seed_forbidden_keys();
	$hits      = array();

	foreach ( $data as $key => $value ) {
		$path = '' === $prefix ? (string) $key : $prefix . '.' . $key;
		if ( is_string( $key ) && in_array( strtolower( $key ), $forbidden, true ) ) {
			$hits[] = $path;
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
