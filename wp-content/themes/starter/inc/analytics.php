<?php
/**
 * Deferred GA4.
 *
 * The page gets only an inline dataLayer/gtag() stub and window.STARTER_GA4 { id, delayMs };
 * analytics.js (defer) injects gtag.js once — delayMs after `load` or on the first interaction,
 * whichever comes first. gtag.js is never a <script src> in the HTML (it used to land before LCP).
 * Events fired earlier queue in dataLayer and are replayed by gtag.js.
 *
 * ID: company options (starter_get_ga4_id()); empty = analytics off. No default ID in code.
 * Administrators (manage_options) are not tracked.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether GA4 is active for the current request.
 *
 * @return bool
 */
function starter_analytics_enabled(): bool {
	$enabled = ! is_admin() && ! current_user_can( 'manage_options' ) && '' !== starter_get_ga4_id();

	/**
	 * Filter whether GA4 is active for the current request.
	 *
	 * @param bool $enabled Default decision.
	 */
	return (bool) apply_filters( 'starter_analytics_enabled', $enabled );
}

/**
 * GA4 delay after `load`, ms (project.config.json → analytics.ga4_delay_ms).
 *
 * @return int
 */
function starter_analytics_delay_ms(): int {
	$delay = starter_core_config( 'analytics.ga4_delay_ms' );

	return is_numeric( $delay ) ? max( 0, (int) $delay ) : 2500;
}

/**
 * Enqueue analytics.js with the inline stub. No dependency on a gtag handle: a registered
 * gtag.js would be printed as <script src> and pulled into the critical path.
 */
function starter_enqueue_analytics(): void {
	if ( ! starter_analytics_enabled() ) {
		return;
	}

	$id = starter_get_ga4_id();

	wp_enqueue_script( 'starter-analytics', starter_asset_url( 'assets/js/analytics.js' ), array(), starter_asset_version( 'assets/js/analytics.js' ), starter_script_args() );

	$config = array(
		'id'      => $id,
		'delayMs' => starter_analytics_delay_ms(),
	);

	wp_add_inline_script(
		'starter-analytics',
		sprintf(
			'window.STARTER_GA4 = %1$s; window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag("js", new Date()); gtag("config", %2$s);',
			wp_json_encode( $config ),
			wp_json_encode( $id )
		),
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'starter_enqueue_analytics', 20 );
