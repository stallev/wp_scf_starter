<?php
/**
 * Leads admin: list columns, status filter, delete confirmations, Telegram settings screen.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'manage_starter_lead_posts_columns', 'starter_lead_posts_columns' );
add_action( 'manage_starter_lead_posts_custom_column', 'starter_lead_posts_custom_column', 10, 2 );
add_action( 'restrict_manage_posts', 'starter_lead_status_filter_dropdown' );
add_action( 'pre_get_posts', 'starter_lead_filter_by_status' );
add_action( 'admin_enqueue_scripts', 'starter_lead_admin_assets' );
add_action( 'admin_enqueue_scripts', 'starter_scf_admin_assets' );
add_action( 'admin_notices', 'starter_lead_telegram_notice' );
add_action( 'admin_menu', 'starter_lead_telegram_menu' );
add_action( 'admin_post_starter_telegram_save', 'starter_lead_telegram_save' );

/**
 * List table columns.
 *
 * @param array<string, string> $columns Existing columns.
 * @return array<string, string>
 */
function starter_lead_posts_columns( array $columns ): array {
	$result = array();

	foreach ( $columns as $key => $label ) {
		$result[ $key ] = $label;
		if ( 'title' === $key ) {
			$result['starter_lead_status']  = __( 'Статус', 'starter' );
			$result['starter_lead_contact'] = __( 'Контакт', 'starter' );
			$result['starter_lead_source']  = __( 'Источник', 'starter' );
		}
	}

	return $result;
}

/**
 * Render custom columns.
 *
 * @param string $column  Column key.
 * @param int    $post_id Lead ID.
 */
function starter_lead_posts_custom_column( string $column, int $post_id ): void {
	if ( ! in_array( $column, array( 'starter_lead_status', 'starter_lead_contact', 'starter_lead_source' ), true ) ) {
		return;
	}

	$value = (string) starter_field( $column, $post_id );

	if ( 'starter_lead_status' === $column ) {
		$labels = starter_lead_statuses();
		$value  = $labels[ $value ] ?? $value;
	}

	echo esc_html( '' !== $value ? $value : '—' );
}

/**
 * Status filter above the leads list.
 *
 * @param string $post_type Current post type.
 */
function starter_lead_status_filter_dropdown( string $post_type ): void {
	if ( 'starter_lead' !== $post_type ) {
		return;
	}

	$current = isset( $_GET['starter_lead_status'] ) ? sanitize_key( wp_unslash( $_GET['starter_lead_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
	$choices = array( '' => __( 'Все статусы', 'starter' ) ) + starter_lead_statuses();

	echo '<select name="starter_lead_status">';
	foreach ( $choices as $value => $label ) {
		printf(
			'<option value="%1$s"%2$s>%3$s</option>',
			esc_attr( $value ),
			selected( $current, $value, false ),
			esc_html( $label )
		);
	}
	echo '</select>';
}

/**
 * Apply the status filter.
 *
 * @param WP_Query $query Query.
 */
function starter_lead_filter_by_status( WP_Query $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() || 'starter_lead' !== $query->get( 'post_type' ) ) {
		return;
	}

	$status = isset( $_GET['starter_lead_status'] ) ? sanitize_key( wp_unslash( $_GET['starter_lead_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
	if ( '' === $status || ! array_key_exists( $status, starter_lead_statuses() ) ) {
		return;
	}

	$query->set(
		'meta_query',
		array(
			array(
				'key'   => 'starter_lead_status',
				'value' => $status,
			),
		)
	);
}

/**
 * Confirm dialogs + loader when deleting leads.
 *
 * @param string $hook_suffix Admin page.
 */
function starter_lead_admin_assets( string $hook_suffix ): void {
	if ( ! in_array( $hook_suffix, array( 'edit.php', 'post.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'starter_lead' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_style( 'starter-admin-leads', starter_core_url( 'assets/admin-leads.css' ), array(), starter_core_asset_version( 'assets/admin-leads.css' ) );
	wp_enqueue_script(
		'starter-admin-leads',
		starter_core_url( 'assets/admin-leads.js' ),
		array(),
		starter_core_asset_version( 'assets/admin-leads.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
	wp_localize_script(
		'starter-admin-leads',
		'starterLeadAdmin',
		array(
			'confirmTrash'  => __( 'Переместить заявку в корзину?', 'starter' ),
			'confirmDelete' => __( 'Удалить заявку навсегда? Это действие нельзя отменить.', 'starter' ),
			'confirmBulk'   => __( 'Удалить выбранные заявки?', 'starter' ),
			'loading'       => __( 'Удаление…', 'starter' ),
		)
	);
}

/**
 * Compact SCF inputs on starter screens.
 *
 * @param string $hook_suffix Admin page.
 */
function starter_scf_admin_assets( string $hook_suffix ): void {
	unset( $hook_suffix );

	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	$is_options = str_contains( (string) $screen->id, STARTER_COMPANY_OPTIONS_SLUG );
	$is_cpt     = in_array( (string) $screen->post_type, array_keys( starter_post_type_definitions() ), true );
	if ( ! $is_options && ! $is_cpt ) {
		return;
	}

	wp_enqueue_style( 'starter-admin-scf', starter_core_url( 'assets/admin-scf.css' ), array(), starter_core_asset_version( 'assets/admin-scf.css' ) );
}

/**
 * Notice on the leads list: whether Telegram notifications are configured (no secrets shown).
 */
function starter_lead_telegram_notice(): void {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-starter_lead' !== $screen->id ) {
		return;
	}

	$ok      = null !== starter_get_telegram_bot_credentials();
	$message = $ok
		? __( 'Telegram-уведомления о заявках настроены.', 'starter' )
		: __( 'Telegram-уведомления не настроены: заявки сохраняются только здесь.', 'starter' );

	printf(
		'<div class="notice %1$s"><p>%2$s %3$s</p></div>',
		esc_attr( $ok ? 'notice-success' : 'notice-warning' ),
		esc_html( $message ),
		current_user_can( 'manage_options' )
			? '<a href="' . esc_url( admin_url( 'edit.php?post_type=starter_lead&page=starter-telegram' ) ) . '">' . esc_html__( 'Настройки Telegram', 'starter' ) . '</a>'
			: ''
	);
}

/**
 * Leads → Telegram submenu.
 */
function starter_lead_telegram_menu(): void {
	add_submenu_page(
		'edit.php?post_type=starter_lead',
		__( 'Telegram-уведомления', 'starter' ),
		__( 'Telegram', 'starter' ),
		'manage_options',
		'starter-telegram',
		'starter_lead_telegram_render'
	);
}

/**
 * Telegram settings form. The stored token is never printed back.
 */
function starter_lead_telegram_render(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$option  = get_option( STARTER_TELEGRAM_OPTION, array() );
	$option  = is_array( $option ) ? $option : array();
	$has_key = '' !== (string) ( $option['token'] ?? '' );
	$saved   = isset( $_GET['updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag only.
	$invalid = isset( $_GET['invalid'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag only.
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Telegram-уведомления о заявках', 'starter' ); ?></h1>
		<?php if ( $invalid ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'Не сохранено: токен должен иметь вид 123456:ABC-def_…, Chat ID — число (для групп с минусом) или @channel.', 'starter' ); ?></p></div>
		<?php endif; ?>
		<?php if ( $saved ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Сохранено.', 'starter' ); ?></p></div>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'Токен хранится в защищённой опции (autoload выключен) и не выводится на страницах. Пустое поле токена оставляет текущее значение.', 'starter' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="starter_telegram_save">
			<?php wp_nonce_field( 'starter_telegram_save' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="starter-telegram-token"><?php esc_html_e( 'Токен бота', 'starter' ); ?></label></th>
					<td>
						<input type="password" id="starter-telegram-token" name="starter_telegram_token" class="regular-text" autocomplete="new-password" value="" placeholder="<?php echo esc_attr( $has_key ? __( 'задан — оставьте пустым', 'starter' ) : '' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="starter-telegram-chat"><?php esc_html_e( 'Chat ID', 'starter' ); ?></label></th>
					<td>
						<input type="text" id="starter-telegram-chat" name="starter_telegram_chat_id" class="regular-text" value="<?php echo esc_attr( (string) ( $option['chat_id'] ?? '' ) ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Удалить', 'starter' ); ?></th>
					<td><label><input type="checkbox" name="starter_telegram_clear" value="1"> <?php esc_html_e( 'Стереть сохранённые данные', 'starter' ); ?></label></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Save Telegram settings (manage_options + nonce).
 */
function starter_lead_telegram_save(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Недостаточно прав.', 'starter' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'starter_telegram_save' );

	if ( ! empty( $_POST['starter_telegram_clear'] ) ) {
		delete_option( STARTER_TELEGRAM_OPTION );
	} else {
		$token   = isset( $_POST['starter_telegram_token'] ) ? sanitize_text_field( wp_unslash( $_POST['starter_telegram_token'] ) ) : '';
		$chat_id = isset( $_POST['starter_telegram_chat_id'] ) ? sanitize_text_field( wp_unslash( $_POST['starter_telegram_chat_id'] ) ) : '';
		if ( ( '' !== $token && ! starter_is_valid_telegram_token( $token ) ) || ! preg_match( '/^-?\d+$|^@[A-Za-z0-9_]{5,}$/', $chat_id ) ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=starter_lead&page=starter-telegram&invalid=1' ) );
			exit;
		}
		starter_set_telegram_bot_credentials( $token, $chat_id );
	}

	wp_safe_redirect( admin_url( 'edit.php?post_type=starter_lead&page=starter-telegram&updated=1' ) );
	exit;
}
