<?php
/**
 * WP-CLI command class `wp starter` (registered in cli.php).
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Starter commands.
 */
class Starter_CLI_Command {

	/**
	 * Import seed JSON (company options, pages from pages-map, posts, FAQ, reviews, projects, service cards, menus).
	 *
	 * Idempotent: records are matched by slug, a second run changes nothing.
	 *
	 * ## OPTIONS
	 *
	 * [--only=<targets>]
	 * : Comma-separated targets: company,pages,posts,faq,reviews,projects,service_cards,menus.
	 *
	 * [--dry-run]
	 * : Report what would change without writing.
	 *
	 * [--dir=<path>]
	 * : Seed directory (default: STARTER_SEED_DIR, wp-content/starter-seed).
	 *
	 * ## EXAMPLES
	 *
	 *     wp starter seed
	 *     wp starter seed --only=faq,reviews
	 *     wp starter seed --dry-run
	 *
	 * @when after_wp_load
	 *
	 * @param string[]             $args       Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 */
	public function seed( $args, $assoc_args ): void {
		unset( $args );

		$only = array_values( array_filter( array_map( 'trim', explode( ',', (string) ( $assoc_args['only'] ?? '' ) ) ) ) );

		$report = starter_seed_run(
			$only,
			array(
				'dir'     => (string) ( $assoc_args['dir'] ?? '' ),
				'dry_run' => ! empty( $assoc_args['dry-run'] ),
			)
		);

		WP_CLI::log( sprintf( 'Seed directory: %s', $report['dir'] ) );
		WP_CLI::log( starter_seed_format_report( $report ) );

		if ( ! $report['ok'] ) {
			WP_CLI::error( 'Seed finished with errors.' );
		}

		WP_CLI::success( $report['dry_run'] ? 'Dry run completed.' : 'Seed completed.' );
	}
}
