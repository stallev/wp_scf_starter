<?php
/**
 * Seed orchestration: shared by Tools → Starter Seed and `wp starter seed`.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Seed targets in run order (target => importer). Pages before anything that links to pages.
 *
 * Optional modules add their targets (products, pricebook, …) via the `starter_seed_targets` filter.
 *
 * @return array<string, callable>
 */
function starter_seed_targets(): array {
	$targets = array(
		'company'       => 'starter_seed_import_company',
		'pages'         => 'starter_seed_import_pages',
		'posts'         => 'starter_seed_import_posts',
		'faq'           => 'starter_seed_import_faq',
		'reviews'       => 'starter_seed_import_reviews',
		'projects'      => 'starter_seed_import_projects',
		'service_cards' => 'starter_seed_import_service_cards',
		'menus'         => 'starter_seed_import_menus',
	);

	/**
	 * Filter seed targets.
	 *
	 * @param array<string, callable> $targets Target => importer returning starter_seed_stats() shape.
	 */
	return array_filter( (array) apply_filters( 'starter_seed_targets', $targets ), 'is_callable' );
}

/**
 * Run the seed.
 *
 * @param string[]                            $only    Targets to run; empty = all.
 * @param array{dir?: string, dry_run?: bool} $options dir: seed directory override; dry_run: report without writing.
 * @return array{ok: bool, dry_run: bool, dir: string, results: array<string, array<string, mixed>>, errors: string[], warnings: string[]}
 */
function starter_seed_run( array $only = array(), array $options = array() ): array {
	$previous = starter_seed_context();
	starter_seed_context(
		array(
			'dir'     => (string) ( $options['dir'] ?? '' ),
			'dry_run' => (bool) ( $options['dry_run'] ?? false ),
		)
	);

	$report = array(
		'ok'       => true,
		'dry_run'  => starter_seed_is_dry_run(),
		'dir'      => starter_seed_path(),
		'results'  => array(),
		'errors'   => array(),
		'warnings' => array(),
	);

	$all     = starter_seed_targets();
	$unknown = array_diff( $only, array_keys( $all ) );
	if ( $unknown ) {
		$report['errors'][] = sprintf( 'Unknown targets: %s. Use: %s', implode( ', ', $unknown ), implode( ', ', array_keys( $all ) ) );
	}

	if ( ! is_dir( $report['dir'] ) ) {
		$report['errors'][] = sprintf( 'Seed directory not found: %s', $report['dir'] );
	}

	if ( ! $report['errors'] ) {
		foreach ( $all as $target => $importer ) {
			if ( $only && ! in_array( $target, $only, true ) ) {
				continue;
			}

			$stats                        = (array) call_user_func( $importer );
			$report['results'][ $target ] = $stats;

			foreach ( (array) ( $stats['errors'] ?? array() ) as $error ) {
				$report['errors'][] = $target . ': ' . $error;
			}
			foreach ( (array) ( $stats['warnings'] ?? array() ) as $warning ) {
				$report['warnings'][] = $target . ': ' . $warning;
			}
		}
	}

	$report['ok'] = ! $report['errors'];

	starter_seed_context( $previous );

	return $report;
}

/**
 * Human-readable report for CLI / admin.
 *
 * @param array<string, mixed> $report Report from starter_seed_run().
 * @return string
 */
function starter_seed_format_report( array $report ): string {
	$lines = array();

	if ( ! empty( $report['dry_run'] ) ) {
		$lines[] = 'DRY RUN — nothing was written.';
	}

	foreach ( (array) ( $report['results'] ?? array() ) as $target => $stats ) {
		$lines[] = sprintf(
			'%-14s created:%d updated:%d unchanged:%d skipped:%d errors:%d',
			$target,
			(int) ( $stats['created'] ?? 0 ),
			(int) ( $stats['updated'] ?? 0 ),
			(int) ( $stats['unchanged'] ?? 0 ),
			(int) ( $stats['skipped'] ?? 0 ),
			count( (array) ( $stats['errors'] ?? array() ) )
		);
	}

	foreach ( array(
		'warnings' => 'Warnings:',
		'errors'   => 'Errors:',
	) as $key => $title ) {
		if ( ! empty( $report[ $key ] ) ) {
			$lines[] = $title;
			foreach ( (array) $report[ $key ] as $message ) {
				$lines[] = '  - ' . $message;
			}
		}
	}

	return implode( "\n", $lines );
}
