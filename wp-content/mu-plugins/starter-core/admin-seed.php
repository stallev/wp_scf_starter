<?php
/**
 * Tools → Starter Seed: run the seed importer from the admin (manage_options + nonce).
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'starter_seed_admin_menu' );
add_action( 'admin_post_starter_seed_run', 'starter_seed_admin_handle' );

/**
 * Register the Tools page.
 */
function starter_seed_admin_menu(): void {
	add_management_page(
		__( 'Starter Seed', 'starter' ),
		__( 'Starter Seed', 'starter' ),
		'manage_options',
		'starter-seed',
		'starter_seed_admin_render_page'
	);
}

/**
 * Handle the form: run, store the report for the redirect, go back.
 */
function starter_seed_admin_handle(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Недостаточно прав.', 'starter' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'starter_seed_run' );

	$only = array();
	if ( isset( $_POST['starter_seed_only'] ) && is_array( $_POST['starter_seed_only'] ) ) {
		$only = array_map( 'sanitize_key', wp_unslash( $_POST['starter_seed_only'] ) );
	}

	$report = starter_seed_run( $only, array( 'dry_run' => ! empty( $_POST['starter_seed_dry_run'] ) ) );

	set_transient( 'starter_seed_report_' . get_current_user_id(), $report, 5 * MINUTE_IN_SECONDS );

	wp_safe_redirect( admin_url( 'tools.php?page=starter-seed&seeded=' . ( $report['ok'] ? '1' : '0' ) ) );
	exit;
}

/**
 * Render the Tools page.
 */
function starter_seed_admin_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$key    = 'starter_seed_report_' . get_current_user_id();
	$report = get_transient( $key );
	if ( false !== $report ) {
		delete_transient( $key );
	}
	$seeded = isset( $_GET['seeded'] ) ? sanitize_key( wp_unslash( $_GET['seeded'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag only.
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Starter Seed — импорт данных', 'starter' ); ?></h1>
		<p>
			<?php
			/* translators: %s: seed directory path. */
			echo esc_html( sprintf( __( 'Источник: %s', 'starter' ), starter_seed_path() ) );
			?>
		</p>
		<p class="description"><?php esc_html_e( 'Идемпотентный импорт по slug: повторный запуск обновляет записи и не создаёт дубликаты. Секреты в seed не допускаются. То же из консоли: wp starter seed.', 'starter' ); ?></p>

		<?php if ( '1' === $seeded ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Seed выполнен.', 'starter' ); ?></p></div>
		<?php elseif ( '0' === $seeded ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'Seed завершился с ошибками — см. отчёт.', 'starter' ); ?></p></div>
		<?php endif; ?>

		<?php if ( is_array( $report ) ) : ?>
			<pre style="background:#fff;border:1px solid #c3c4c7;padding:12px;max-width:760px;overflow:auto"><?php echo esc_html( starter_seed_format_report( $report ) ); ?></pre>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="starter_seed_run">
			<?php wp_nonce_field( 'starter_seed_run' ); ?>
			<fieldset>
				<legend><strong><?php esc_html_e( 'Цели (ничего не выбрано = все)', 'starter' ); ?></strong></legend>
				<?php foreach ( array_keys( starter_seed_targets() ) as $target ) : ?>
					<label style="display:block;margin:4px 0">
						<input type="checkbox" name="starter_seed_only[]" value="<?php echo esc_attr( $target ); ?>">
						<?php echo esc_html( $target ); ?>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<p>
				<label><input type="checkbox" name="starter_seed_dry_run" value="1"> <?php esc_html_e( 'Пробный прогон (ничего не записывать)', 'starter' ); ?></label>
			</p>
			<?php submit_button( __( 'Запустить seed', 'starter' ) ); ?>
		</form>
	</div>
	<?php
}
