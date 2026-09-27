<?php
/**
 * Pricebook example module: options → provider → cache → JS, with a testable "price × qty" example
 * and a WP-CLI check against reference values.
 *
 * Front-end enqueue stays out of this mu-plugin on purpose (invariant 1, `check:naming`
 * frontend-enqueue-in-core): the module only exposes data (starter_get_pricebook()) and its own JS
 * asset URL (starter_pricebook_asset_url()); the theme (inc/assets.php) decides whether and how to
 * enqueue it, exactly like every other front-end asset.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/fields/pricebook.php';

/** Options page slug (fields: fields/pricebook.php). */
const STARTER_PRICEBOOK_OPTIONS_SLUG = 'starter-pricebook';

/** Transient key of the cached pricebook. */
const STARTER_PRICEBOOK_TRANSIENT = 'starter_pricebook_v1';

/**
 * Register the pricebook options page.
 */
function starter_register_pricebook_options_page(): void {
	if ( ! function_exists( 'acf_add_options_page' ) ) {
		return;
	}

	acf_add_options_page(
		array(
			'page_title' => __( 'Прайс-лист', 'starter' ),
			'menu_title' => __( 'Прайс-лист', 'starter' ),
			'menu_slug'  => STARTER_PRICEBOOK_OPTIONS_SLUG,
			'capability' => 'manage_options',
			'redirect'   => false,
			'position'   => 59,
			'icon_url'   => 'dashicons-money-alt',
		)
	);
}
add_action( 'acf/init', 'starter_register_pricebook_options_page' );

/**
 * Baseline demo items, overlaid by the options page repeater. Kept in code (not seed): the demo
 * calculator/etalon checks below assert against these exact numbers, same pattern as a real project
 * hardcoding its own reference prices once and updating them deliberately when prices change.
 *
 * @return array<string, array{label: string, value: float, unit: string, note: string}>
 */
function starter_pricebook_default_items(): array {
	return array(
		'starter_demo_unit' => array(
			'label' => __( 'Демонстрационная позиция', 'starter' ),
			'value' => 100.0,
			'unit'  => __( 'шт', 'starter' ),
			'note'  => '',
		),
	);
}

/**
 * Build the pricebook from defaults, overlaid by the options page repeater (by row key).
 *
 * @return array<string, array{label: string, value: float, unit: string, note: string}>
 */
function starter_pricebook_build(): array {
	$items = starter_pricebook_default_items();

	$rows = function_exists( 'get_field' ) ? get_field( 'starter_pricebook_items', 'option' ) : null;
	if ( is_array( $rows ) ) {
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key = sanitize_key( (string) ( $row['key'] ?? '' ) );
			if ( '' === $key ) {
				continue;
			}
			$items[ $key ] = array(
				'label' => (string) ( $row['label'] ?? '' ),
				'value' => is_numeric( $row['value'] ?? null ) ? (float) $row['value'] : 0.0,
				'unit'  => (string) ( $row['unit'] ?? '' ),
				'note'  => (string) ( $row['note'] ?? '' ),
			);
		}
	}

	/**
	 * Filter the pricebook items (key => {label, value, unit, note}) after loading from options.
	 *
	 * @param array<string, array<string, mixed>> $items Items.
	 */
	return (array) apply_filters( 'starter_pricebook_items', $items );
}

/**
 * Pricebook, transient-cached (invalidated on save of the pricebook options).
 *
 * @return array<string, array{label: string, value: float, unit: string, note: string}>
 */
function starter_get_pricebook(): array {
	$cached = get_transient( STARTER_PRICEBOOK_TRANSIENT );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$items = starter_pricebook_build();
	set_transient( STARTER_PRICEBOOK_TRANSIENT, $items, HOUR_IN_SECONDS );

	return $items;
}

/**
 * Numeric value of one pricebook item.
 *
 * @param string $key Item key.
 * @return float 0.0 when the key is unknown.
 */
function starter_price( string $key ): float {
	$items = starter_get_pricebook();

	return isset( $items[ $key ]['value'] ) ? (float) $items[ $key ]['value'] : 0.0;
}

/**
 * Drop the pricebook transient.
 */
function starter_pricebook_invalidate_cache(): void {
	delete_transient( STARTER_PRICEBOOK_TRANSIENT );
}
add_action(
	'acf/options_page/save',
	static function ( $post_id, $menu_slug ) {
		if ( STARTER_PRICEBOOK_OPTIONS_SLUG === $menu_slug ) {
			starter_pricebook_invalidate_cache();
		}
	},
	10,
	2
);

/**
 * URL of the module's front-end script (theme enqueues it, see inc/assets.php).
 *
 * @return string
 */
function starter_pricebook_asset_url(): string {
	return plugins_url( 'assets/js/prices.js', __FILE__ );
}

/**
 * Cache-busting version of the module's front-end script.
 *
 * @return string
 */
function starter_pricebook_asset_version(): string {
	$file  = __DIR__ . '/assets/js/prices.js';
	$mtime = is_readable( $file ) ? filemtime( $file ) : false;

	return false === $mtime ? STARTER_CORE_VERSION : (string) $mtime;
}

/**
 * Calculator example: price × quantity, rounded to 2 decimals.
 *
 * @param string $key Pricebook item key.
 * @param float  $qty Quantity.
 * @return float
 */
function starter_pricebook_calc_total( string $key, float $qty ): float {
	return round( starter_price( $key ) * $qty, 2 );
}

/**
 * Reference checks against the baseline demo item (starter_pricebook_default_items()). Mirrors the
 * real-project pattern of hardcoded reference numbers checked in via WP-CLI: when someone changes a
 * pricebook number, they update these expected values deliberately, in the same commit.
 *
 * @return array{ok: bool, checks: array<int, array{id: string, label: string, expected: float, actual: float, pass: bool}>}
 */
function starter_pricebook_etalon_checks(): array {
	$cases = array(
		array(
			'id'       => 'demo-1',
			'key'      => 'starter_demo_unit',
			'qty'      => 3.0,
			'expected' => 300.0,
		),
		array(
			'id'       => 'demo-2',
			'key'      => 'starter_demo_unit',
			'qty'      => 10.0,
			'expected' => 1000.0,
		),
	);

	$checks = array();
	foreach ( $cases as $case ) {
		$actual   = starter_pricebook_calc_total( $case['key'], $case['qty'] );
		$checks[] = array(
			'id'       => $case['id'],
			/* translators: 1: pricebook item key, 2: quantity. */
			'label'    => sprintf( __( '%1$s × %2$s', 'starter' ), $case['key'], $case['qty'] ),
			'expected' => $case['expected'],
			'actual'   => $actual,
			'pass'     => abs( $actual - $case['expected'] ) < 0.0001,
		);
	}

	$ok = true;
	foreach ( $checks as $check ) {
		if ( empty( $check['pass'] ) ) {
			$ok = false;
			break;
		}
	}

	return array(
		'ok'     => $ok,
		'checks' => $checks,
	);
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/class-starter-pricebook-cli-command.php';
	WP_CLI::add_command( 'starter pricebook', 'Starter_Pricebook_CLI_Command' );
}
