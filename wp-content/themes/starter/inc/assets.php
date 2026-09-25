<?php
/**
 * Front-end assets: main.css + main.js (defer) and inline data objects.
 *
 * Rules (performance P1, K2):
 * - every script gets 'strategy' => 'defer' (or 'async'): a dependent script without a strategy
 *   silently makes its deferred parent parser-blocking again;
 * - versions come from filemtime(), so cached assets are busted on every change;
 * - data for scripts goes through wp_add_inline_script( …, 'before' ) — no inline <script> in templates;
 * - fonts (@font-face) live at the top of main.css: one render-blocking stylesheet, not two.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

/**
 * URL of a theme file.
 *
 * @param string $relative Path relative to the theme root, e.g. assets/js/main.js.
 * @return string
 */
function starter_asset_url( string $relative ): string {
	return get_template_directory_uri() . '/' . ltrim( $relative, '/' );
}

/**
 * Cache-busting version of a theme file (filemtime, falls back to the theme version).
 *
 * @param string $relative Path relative to the theme root.
 * @return string
 */
function starter_asset_version( string $relative ): string {
	$file  = get_template_directory() . '/' . ltrim( $relative, '/' );
	$mtime = is_readable( $file ) ? filemtime( $file ) : false;

	return false === $mtime ? STARTER_THEME_VERSION : (string) $mtime;
}

/**
 * Deferred script args for wp_enqueue_script() — use for every theme script.
 *
 * @return array{in_footer: bool, strategy: string}
 */
function starter_script_args(): array {
	return array(
		'in_footer' => true,
		'strategy'  => 'defer',
	);
}

/**
 * Enqueue main.css and main.js; expose window.STARTER_LEAD for the lead form.
 */
function starter_enqueue_assets(): void {
	wp_enqueue_style( 'starter-main', starter_asset_url( 'assets/css/main.css' ), array(), starter_asset_version( 'assets/css/main.css' ) );

	wp_enqueue_script( 'starter-main', starter_asset_url( 'assets/js/main.js' ), array(), starter_asset_version( 'assets/js/main.js' ), starter_script_args() );

	$lead = array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'action'  => defined( 'STARTER_LEAD_ACTION' ) ? STARTER_LEAD_ACTION : 'starter_submit_lead',
		'i18n'    => array(
			'required'  => __( 'Заполните обязательные поля.', 'starter' ),
			'consent'   => __( 'Нужно согласие на обработку персональных данных.', 'starter' ),
			'error'     => __( 'Не удалось отправить заявку. Попробуйте ещё раз.', 'starter' ),
			'network'   => __( 'Ошибка сети. Проверьте соединение и попробуйте снова.', 'starter' ),
			'nonce'     => __( 'Сессия устарела. Обновите страницу и попробуйте снова.', 'starter' ),
			'rateLimit' => __( 'Слишком много заявок. Попробуйте через несколько минут.', 'starter' ),
		),
	);

	$i18n = array(
		'close' => __( 'Закрыть', 'starter' ),
		'prev'  => __( 'Предыдущее', 'starter' ),
		'next'  => __( 'Следующее', 'starter' ),
	);

	wp_add_inline_script(
		'starter-main',
		'window.STARTER_LEAD = ' . wp_json_encode( $lead, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ';'
		. 'window.STARTER_I18N = ' . wp_json_encode( $i18n, JSON_UNESCAPED_UNICODE ) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'starter_enqueue_assets' );

/**
 * Logged-in users: defer the admin bar scripts too, so the final HTML has no parser-blocking
 * script for anyone. Both scripts only bind event handlers on DOM ready and have no inline
 * dependents, so deferring them is safe.
 */
function starter_defer_admin_bar_scripts(): void {
	foreach ( array( 'admin-bar', 'hoverintent-js' ) as $handle ) {
		if ( wp_script_is( $handle, 'registered' ) ) {
			wp_script_add_data( $handle, 'strategy', 'defer' );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'starter_defer_admin_bar_scripts', 100 );
