<?php
/**
 * WP-CLI: wp starter pricebook check (registered in module.php, only while the module is loaded).
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pricebook commands.
 */
class Starter_Pricebook_CLI_Command {

	/**
	 * Check the pricebook calculator against reference values (starter_pricebook_etalon_checks()).
	 *
	 * Exits non-zero when any check fails — wire this into CI or a pre-deploy step so a pricing change
	 * that breaks the calculator formula is caught before it reaches the site.
	 *
	 * ## EXAMPLES
	 *
	 *     wp starter pricebook check
	 *
	 * @when after_wp_load
	 *
	 * @param string[]             $args       Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 */
	public function check( $args, $assoc_args ): void {
		unset( $args, $assoc_args );

		$report = starter_pricebook_etalon_checks();

		foreach ( $report['checks'] as $check ) {
			$status = $check['pass'] ? 'OK' : 'FAIL';
			WP_CLI::log(
				sprintf(
					'[%s] %s — expected %s, got %s (%s)',
					$status,
					$check['id'],
					$check['expected'],
					$check['actual'],
					$check['label']
				)
			);
		}

		if ( ! $report['ok'] ) {
			WP_CLI::error( 'Pricebook check failed: calculator does not match the reference values.' );
		}

		WP_CLI::success( 'Pricebook matches the reference values.' );
	}
}
