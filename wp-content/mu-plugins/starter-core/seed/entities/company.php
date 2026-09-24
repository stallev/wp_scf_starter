<?php
/**
 * Seed entity: company options (seed/company.json → starter-company options page).
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Import company.json.
 *
 * @return array{created: int, updated: int, unchanged: int, skipped: int, errors: string[], warnings: string[]}
 */
function starter_seed_import_company(): array {
	$stats = starter_seed_stats();
	$data  = starter_seed_load_for( 'company.json', $stats );
	if ( null === $data ) {
		return $stats;
	}

	$fields = starter_seed_map_company( $data );

	if ( ! empty( $data['default_image'] ) && is_string( $data['default_image'] ) ) {
		$attachment_id = starter_seed_attachment( $data['default_image'], 0, (string) ( $data['name'] ?? '' ) );
		if ( is_wp_error( $attachment_id ) ) {
			$stats['warnings'][] = 'default_image: ' . $attachment_id->get_error_message();
		} elseif ( $attachment_id > 0 ) {
			$fields['starter_company_default_image'] = $attachment_id;
		}
	}

	$changed = 0;
	foreach ( $fields as $name => $value ) {
		if ( starter_seed_option_equals( $name, $value ) ) {
			continue;
		}
		++$changed;
		starter_seed_update_field( $name, $value, 'option' );
	}

	++$stats[ $changed > 0 ? 'updated' : 'unchanged' ];

	return $stats;
}

/**
 * Whether the stored option field already has this value (keeps re-runs write-free).
 *
 * @param string $name  Field name.
 * @param mixed  $value Seed value.
 * @return bool
 */
function starter_seed_option_equals( string $name, $value ): bool {
	$current = function_exists( 'get_field' ) ? get_field( $name, 'option', false ) : get_option( 'options_' . $name );

	if ( is_array( $value ) ) {
		return wp_json_encode( starter_seed_normalize_rows( $value ) ) === wp_json_encode( starter_seed_normalize_rows( is_array( $current ) ? $current : array() ) );
	}

	return (string) $current === (string) $value;
}

/**
 * Normalize repeater rows for comparison: raw SCF values are keyed by field key, seed rows by name.
 *
 * @param array<mixed> $rows Rows.
 * @return array<int, array<int, mixed>>
 */
function starter_seed_normalize_rows( array $rows ): array {
	$result = array();

	foreach ( $rows as $row ) {
		$result[] = is_array( $row ) ? array_map( static fn( $v ) => is_array( $v ) ? array_values( $v ) : (string) $v, array_values( $row ) ) : array();
	}

	return $result;
}
