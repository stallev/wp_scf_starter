<?php
/**
 * SCF options page `starter-company` + the protected Telegram bot option.
 *
 * Optional modules (pricebook, …) register their own options pages.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/** Options page slug of the company settings (fields: fields/company.php). */
const STARTER_COMPANY_OPTIONS_SLUG = 'starter-company';

/** Protected option with Telegram bot credentials: { token, chat_id }, autoload = false, not SCF. */
const STARTER_TELEGRAM_OPTION = 'starter_telegram_bot';

/**
 * Register the company options page.
 */
function starter_register_options_pages(): void {
	if ( ! function_exists( 'acf_add_options_page' ) ) {
		return;
	}

	acf_add_options_page(
		array(
			'page_title' => __( 'Компания', 'starter' ),
			'menu_title' => __( 'Компания', 'starter' ),
			'menu_slug'  => STARTER_COMPANY_OPTIONS_SLUG,
			'capability' => 'manage_options',
			'redirect'   => false,
			'position'   => 58,
			'icon_url'   => 'dashicons-building',
		)
	);
}
add_action( 'acf/init', 'starter_register_options_pages' );

/**
 * Telegram bot credentials from the protected option.
 *
 * The token is a secret: never print it, never log it, never pass it to the front end.
 *
 * @return array{token: string, chat_id: string}|null Null when not configured.
 */
function starter_get_telegram_bot_credentials(): ?array {
	$option = get_option( STARTER_TELEGRAM_OPTION, array() );
	if ( ! is_array( $option ) ) {
		return null;
	}

	$token   = trim( (string) ( $option['token'] ?? '' ) );
	$chat_id = trim( (string) ( $option['chat_id'] ?? '' ) );

	if ( '' === $token || '' === $chat_id ) {
		return null;
	}

	return array(
		'token'   => $token,
		'chat_id' => $chat_id,
	);
}

/**
 * Whether a string looks like a Telegram bot token (<bot id>:<secret>).
 *
 * @param string $token Token.
 * @return bool
 */
function starter_is_valid_telegram_token( string $token ): bool {
	return (bool) preg_match( '/^\d+:[A-Za-z0-9_-]+$/', $token );
}

/**
 * Store Telegram bot credentials (autoload = false). Empty token keeps the current one.
 *
 * @param string $token   Bot token ('' = keep current).
 * @param string $chat_id Chat ID.
 */
function starter_set_telegram_bot_credentials( string $token, string $chat_id ): void {
	$current = get_option( STARTER_TELEGRAM_OPTION, array() );
	$current = is_array( $current ) ? $current : array();

	$value = array(
		'token'   => '' !== $token ? $token : (string) ( $current['token'] ?? '' ),
		'chat_id' => $chat_id,
	);

	if ( false === get_option( STARTER_TELEGRAM_OPTION ) ) {
		add_option( STARTER_TELEGRAM_OPTION, $value, '', false );
		return;
	}

	update_option( STARTER_TELEGRAM_OPTION, $value, false );
}
