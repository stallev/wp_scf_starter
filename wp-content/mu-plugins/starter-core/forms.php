<?php
/**
 * Lead form contract: AJAX submit, nonce, honeypot, rate limit, storage, Telegram notification.
 *
 * Front-end contract (theme, M4):
 *   POST admin-ajax.php  action=starter_submit_lead
 *   starter_lead_nonce   wp_create_nonce( 'starter_lead_submit' ) — see starter_lead_hidden_fields()
 *   starter_hp_company   honeypot, must stay empty (hidden from people)
 *   name, contact, service, message, source, consent
 * Responses: wp_send_json_success( { message, accepted: true } ) — also for the honeypot / wp_send_json_error( { message, code }, status ).
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/** AJAX action. */
const STARTER_LEAD_ACTION = 'starter_submit_lead';

/** Nonce action and field. */
const STARTER_LEAD_NONCE_ACTION = 'starter_lead_submit';
const STARTER_LEAD_NONCE_FIELD  = 'starter_lead_nonce';

/** Honeypot field name. */
const STARTER_LEAD_HONEYPOT = 'starter_hp_company';

add_action( 'wp_ajax_' . STARTER_LEAD_ACTION, 'starter_handle_submit_lead' );
add_action( 'wp_ajax_nopriv_' . STARTER_LEAD_ACTION, 'starter_handle_submit_lead' );
add_action( 'starter_lead_created', 'starter_notify_telegram_lead', 10, 2 );
add_filter( 'starter_lead_skip_telegram', 'starter_lead_skip_telegram_in_e2e' );

/**
 * Test-only toggle: the e2e forms suite sets the option `starter_e2e_mode` (WP-CLI) so real
 * submits never reach Telegram. Honoured only when WP_ENVIRONMENT_TYPE is 'local' (wp-env), so
 * a stray option on staging/production changes nothing. See tests/e2e/forms.spec.ts.
 *
 * @param mixed $skip Current value of the starter_lead_skip_telegram filter.
 * @return bool
 */
function starter_lead_skip_telegram_in_e2e( $skip ): bool {
	if ( (bool) $skip ) {
		return true;
	}

	return 'local' === wp_get_environment_type() && (bool) get_option( 'starter_e2e_mode' );
}

/**
 * Hidden inputs every lead form needs: action, nonce, honeypot.
 *
 * @param string $source Form source key (page slug / form id) stored with the lead.
 * @return string HTML.
 */
function starter_lead_hidden_fields( string $source = '' ): string {
	$html  = '<input type="hidden" name="action" value="' . esc_attr( STARTER_LEAD_ACTION ) . '">';
	$html .= wp_nonce_field( STARTER_LEAD_NONCE_ACTION, STARTER_LEAD_NONCE_FIELD, false, false );
	$html .= '<input type="hidden" name="source" value="' . esc_attr( $source ) . '">';
	$html .= '<div class="starter-hp" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">'
		. '<label>' . esc_html__( 'Компания', 'starter' ) . ' <input type="text" name="' . esc_attr( STARTER_LEAD_HONEYPOT ) . '" value="" tabindex="-1" autocomplete="off"></label>'
		. '</div>';

	return $html;
}

/**
 * Rate-limit settings.
 *
 * @return array{limit: int, window: int} Max successful submits per window (seconds) per IP.
 */
function starter_lead_rate_limit_settings(): array {
	/**
	 * Filter lead rate limit.
	 *
	 * @param array{limit: int, window: int} $settings Defaults: 5 leads per 10 minutes per IP.
	 */
	$settings = (array) apply_filters(
		'starter_lead_rate_limit',
		array(
			'limit'  => 5,
			'window' => 10 * MINUTE_IN_SECONDS,
		)
	);

	return array(
		'limit'  => max( 1, (int) ( $settings['limit'] ?? 5 ) ),
		'window' => max( 1, (int) ( $settings['window'] ?? 600 ) ),
	);
}

/**
 * Client IP for the rate limit.
 *
 * Uses REMOTE_ADDR only. Behind a reverse proxy / load balancer, let the web server rewrite
 * REMOTE_ADDR (mod_remoteip, nginx real_ip) trusting ONLY the real proxy addresses — never trust
 * X-Forwarded-For from arbitrary clients, or anyone can rotate IPs and bypass the limit.
 * Note: the wp-env image trusts private ranges for X-Forwarded-For; do not copy that to production.
 *
 * @return string
 */
function starter_lead_client_ip(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

	/**
	 * Filter the client IP used for the lead rate limit.
	 *
	 * @param string $ip REMOTE_ADDR.
	 */
	return (string) apply_filters( 'starter_lead_client_ip', $ip );
}

/**
 * Transient key for the current client (hashed IP, the raw IP is never stored).
 *
 * @return string
 */
function starter_lead_rate_limit_key(): string {
	$ip = starter_lead_client_ip();

	return 'starter_lead_rl_' . substr( wp_hash( $ip, 'nonce' ), 0, 20 );
}

/**
 * Whether the current client reached the limit.
 *
 * @return bool
 */
function starter_lead_is_rate_limited(): bool {
	$settings = starter_lead_rate_limit_settings();

	return (int) get_transient( starter_lead_rate_limit_key() ) >= $settings['limit'];
}

/**
 * Count a stored lead against the limit.
 */
function starter_lead_bump_rate_limit(): void {
	$settings = starter_lead_rate_limit_settings();
	$key      = starter_lead_rate_limit_key();

	set_transient( $key, (int) get_transient( $key ) + 1, $settings['window'] );
}

/**
 * Send a JSON error and stop.
 *
 * @param string $code    Machine code (nonce, rate_limit, consent, contact, save, …).
 * @param string $message Human message.
 * @param int    $status  HTTP status.
 */
function starter_lead_fail( string $code, string $message, int $status = 400 ): never {
	wp_send_json_error(
		array(
			'message' => $message,
			'code'    => $code,
		),
		$status
	);
}

/**
 * Read and sanitize the submitted lead fields. Caller has verified the nonce.
 *
 * @return array{name: string, contact: string, service: string, message: string, source: string, consent: bool}
 */
function starter_lead_read_input(): array {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in starter_handle_submit_lead().
	$text = static function ( string $key, int $max = 200 ): string {
		// Arrays (field[]=x) are not valid values: treat them as empty instead of casting.
		$value = isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in the same expression.
		return mb_substr( trim( $value ), 0, $max );
	};

	$contact = $text( 'contact' );
	if ( '' === $contact ) {
		$contact = $text( 'phone' ); // Alias used by simple phone-only forms.
	}

	$message = isset( $_POST['message'] ) && is_scalar( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['message'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in the same expression.
	$consent = isset( $_POST['consent'] ) && is_scalar( $_POST['consent'] ) && in_array( (string) $_POST['consent'], array( '1', 'on', 'yes', 'true' ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared against a whitelist.
	// phpcs:enable

	return array(
		'name'    => $text( 'name', 100 ),
		'contact' => $contact,
		'service' => $text( 'service', 150 ),
		'message' => mb_substr( trim( $message ), 0, 2000 ),
		'source'  => sanitize_title( $text( 'source', 100 ) ),
		'consent' => $consent,
	);
}

/**
 * AJAX handler: validate, store the lead, fire starter_lead_created.
 */
function starter_handle_submit_lead(): void {
	$nonce = isset( $_POST[ STARTER_LEAD_NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ STARTER_LEAD_NONCE_FIELD ] ) ) : '';
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, STARTER_LEAD_NONCE_ACTION ) ) {
		starter_lead_fail( 'nonce', __( 'Сессия устарела. Обновите страницу и попробуйте снова.', 'starter' ), 403 );
	}

	// Bots fill every field: pretend success, store nothing, notify nobody.
	// The response is identical to a real success, so bots cannot detect the trap.
	if ( ! empty( $_POST[ STARTER_LEAD_HONEYPOT ] ) ) {
		starter_lead_success();
	}

	if ( starter_lead_is_rate_limited() ) {
		starter_lead_fail( 'rate_limit', __( 'Слишком много заявок. Попробуйте через несколько минут.', 'starter' ), 429 );
	}

	$data = starter_lead_read_input();

	if ( ! $data['consent'] ) {
		starter_lead_fail( 'consent', __( 'Нужно согласие на обработку персональных данных.', 'starter' ) );
	}

	/**
	 * Filter required lead fields (keys of the sanitized data).
	 *
	 * @param string[] $required Default: contact.
	 */
	$required = (array) apply_filters( 'starter_lead_required_fields', array( 'contact' ) );
	foreach ( $required as $field ) {
		if ( ! isset( $data[ $field ] ) || '' === $data[ $field ] ) {
			starter_lead_fail(
				(string) $field,
				'contact' === $field
					? __( 'Укажите телефон или мессенджер для связи.', 'starter' )
					: __( 'Заполните обязательные поля.', 'starter' )
			);
		}
	}

	$lead_id = wp_insert_post(
		array(
			'post_type'    => 'starter_lead',
			'post_status'  => 'private',
			'post_title'   => sprintf(
				/* translators: 1: name or contact, 2: source key. */
				__( 'Заявка: %1$s (%2$s)', 'starter' ),
				'' !== $data['name'] ? $data['name'] : $data['contact'],
				'' !== $data['source'] ? $data['source'] : 'site'
			),
			'post_content' => $data['message'],
		),
		true
	);

	if ( is_wp_error( $lead_id ) || 0 === $lead_id ) {
		starter_lead_fail( 'save', __( 'Не удалось сохранить заявку. Позвоните нам или попробуйте позже.', 'starter' ), 500 );
	}

	$lead_id = (int) $lead_id;
	$meta    = array(
		'starter_lead_name'    => $data['name'],
		'starter_lead_contact' => $data['contact'],
		'starter_lead_service' => $data['service'],
		'starter_lead_source'  => $data['source'],
		'starter_lead_consent' => 1, // Consent is required above.
		'starter_lead_status'  => 'new',
	);
	foreach ( $meta as $name => $value ) {
		starter_update_field( $name, $value, $lead_id );
	}

	starter_lead_bump_rate_limit();

	/**
	 * Fires after a lead is stored.
	 *
	 * @param int                  $lead_id Lead post ID.
	 * @param array<string, mixed> $data    Sanitized lead data (name, contact, service, message, source, consent).
	 */
	do_action( 'starter_lead_created', $lead_id, $data );

	starter_lead_success();
}

/**
 * Send the success response (same body for real leads and the honeypot) and stop.
 */
function starter_lead_success(): never {
	wp_send_json_success(
		array(
			'message'  => __( 'Спасибо! Мы свяжемся с вами.', 'starter' ),
			'accepted' => true,
		)
	);
}

/**
 * Clickable contact for the Telegram message: @user / t.me → Telegram link, digits → tel:.
 *
 * @param string $contact Raw contact.
 * @return string Safe HTML (Telegram HTML parse mode).
 */
function starter_lead_contact_html( string $contact ): string {
	$contact = trim( $contact );
	$escaped = esc_html( $contact );

	if ( preg_match( '/^@([A-Za-z0-9_]{4,})$/', $contact, $m ) ) {
		return '<a href="' . esc_url( 'https://t.me/' . $m[1] ) . '">' . $escaped . '</a>';
	}

	if ( preg_match( '#^(?:https?://)?(?:t\.me|telegram\.me)/([A-Za-z0-9_]+)#i', $contact, $m ) ) {
		return '<a href="' . esc_url( 'https://t.me/' . $m[1] ) . '">' . $escaped . '</a>';
	}

	$href = starter_phone_href( $contact );
	if ( (bool) preg_match( '/^\+\d{7,15}$/', $href ) ) {
		return '<a href="' . esc_url( 'tel:' . $href ) . '">' . $escaped . '</a>';
	}

	return $escaped;
}

/**
 * Telegram notification for a new lead. Skips silently without credentials.
 *
 * @param int                  $lead_id Lead ID.
 * @param array<string, mixed> $data    Sanitized lead data.
 */
function starter_notify_telegram_lead( int $lead_id, array $data ): void {
	/**
	 * Skip the Telegram notification (tests, staging).
	 *
	 * @param bool $skip    Whether to skip.
	 * @param int  $lead_id Lead ID.
	 */
	if ( apply_filters( 'starter_lead_skip_telegram', false, $lead_id ) ) {
		return;
	}

	$creds = starter_get_telegram_bot_credentials();
	if ( null === $creds ) {
		return;
	}

	$dash  = '—';
	$lines = array(
		'<b>' . esc_html__( 'Новая заявка', 'starter' ) . '</b> #' . $lead_id,
		'',
		'<b>' . esc_html__( 'Имя:', 'starter' ) . '</b> ' . ( '' !== (string) $data['name'] ? esc_html( (string) $data['name'] ) : $dash ),
		'<b>' . esc_html__( 'Контакт:', 'starter' ) . '</b> ' . starter_lead_contact_html( (string) $data['contact'] ),
	);
	if ( '' !== (string) ( $data['service'] ?? '' ) ) {
		$lines[] = '<b>' . esc_html__( 'Услуга:', 'starter' ) . '</b> ' . esc_html( (string) $data['service'] );
	}
	if ( '' !== (string) ( $data['message'] ?? '' ) ) {
		$lines[] = '<b>' . esc_html__( 'Комментарий:', 'starter' ) . '</b> ' . esc_html( (string) $data['message'] );
	}
	$lines[] = '<b>' . esc_html__( 'Источник:', 'starter' ) . '</b> ' . ( '' !== (string) ( $data['source'] ?? '' ) ? esc_html( (string) $data['source'] ) : $dash );
	$lines[] = '';
	$lines[] = '<a href="' . esc_url( admin_url( 'post.php?post=' . $lead_id . '&action=edit' ) ) . '">' . esc_html__( 'Открыть в админке', 'starter' ) . '</a>';

	$response = wp_remote_post(
		'https://api.telegram.org/bot' . $creds['token'] . '/sendMessage',
		array(
			'timeout' => 10,
			'body'    => array(
				'chat_id'                  => $creds['chat_id'],
				'text'                     => implode( "\n", $lines ),
				'parse_mode'               => 'HTML',
				'disable_web_page_preview' => 'true',
			),
		)
	);

	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
		return;
	}

	// Log only the lead ID and a redacted error: the token is part of the request URL.
	if ( is_wp_error( $response ) ) {
		$error = str_replace( $creds['token'], '***', $response->get_error_message() );
		error_log( sprintf( '[starter_lead] Telegram request failed for lead #%d: %s', $lead_id, $error ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug-only diagnostics, token redacted.
		return;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		error_log( sprintf( '[starter_lead] Telegram HTTP %d for lead #%d', $code, $lead_id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug-only diagnostics.
	}
}
