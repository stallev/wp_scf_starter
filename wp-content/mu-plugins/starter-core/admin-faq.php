<?php
/**
 * FAQ admin UX: accordion grouped by placement, drag-reorder and delete via AJAX (nonce + caps).
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/** Parent menu of the FAQ CPT. */
const STARTER_FAQ_PARENT = 'edit.php?post_type=starter_faq';

/** Manager page slug. */
const STARTER_FAQ_PAGE = 'starter-faq-manager';

add_action( 'admin_menu', 'starter_faq_admin_menu' );
add_action( 'admin_menu', 'starter_faq_admin_menu_primary', 999 );
add_action( 'load-edit.php', 'starter_faq_admin_redirect_list' );
add_filter( 'submenu_file', 'starter_faq_admin_submenu_file', 10, 2 );
add_action( 'admin_enqueue_scripts', 'starter_faq_admin_assets' );
add_action( 'wp_ajax_starter_faq_reorder', 'starter_ajax_faq_reorder' );
add_action( 'wp_ajax_starter_faq_delete', 'starter_ajax_faq_delete' );

/**
 * Register FAQ → «По местам».
 */
function starter_faq_admin_menu(): void {
	add_submenu_page(
		STARTER_FAQ_PARENT,
		__( 'FAQ', 'starter' ),
		__( 'По местам', 'starter' ),
		'edit_posts',
		STARTER_FAQ_PAGE,
		'starter_faq_admin_render_page'
	);
}

/**
 * Remove the flat CPT list submenu: the grouped manager replaces it.
 */
function starter_faq_admin_menu_primary(): void {
	global $submenu;

	if ( empty( $submenu[ STARTER_FAQ_PARENT ] ) || ! is_array( $submenu[ STARTER_FAQ_PARENT ] ) ) {
		return;
	}

	foreach ( $submenu[ STARTER_FAQ_PARENT ] as $index => $item ) {
		if ( isset( $item[2] ) && STARTER_FAQ_PARENT === $item[2] ) {
			unset( $submenu[ STARTER_FAQ_PARENT ][ $index ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- intentional menu tweak.
		}
	}
}

/**
 * Redirect the flat list (edit.php?post_type=starter_faq) to the manager.
 */
function starter_faq_admin_redirect_list(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only routing.
	$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
	if ( 'starter_faq' !== $post_type || isset( $_GET['page'] ) || isset( $_GET['post_status'] ) ) {
		return;
	}
	// phpcs:enable

	wp_safe_redirect( admin_url( STARTER_FAQ_PARENT . '&page=' . STARTER_FAQ_PAGE ) );
	exit;
}

/**
 * Highlight «По местам» while the manager is open.
 *
 * @param string|null $submenu_file Current submenu file.
 * @param string      $parent_file  Parent file.
 * @return string|null
 */
function starter_faq_admin_submenu_file( $submenu_file, $parent_file ) {
	if ( STARTER_FAQ_PARENT !== $parent_file ) {
		return $submenu_file;
	}

	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.

	return STARTER_FAQ_PAGE === $page ? STARTER_FAQ_PAGE : $submenu_file;
}

/**
 * Manager assets.
 *
 * @param string $hook_suffix Admin page.
 */
function starter_faq_admin_assets( string $hook_suffix ): void {
	if ( 'starter_faq_page_' . STARTER_FAQ_PAGE !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style( 'starter-admin-faq', starter_core_url( 'assets/admin-faq.css' ), array(), starter_core_asset_version( 'assets/admin-faq.css' ) );
	wp_enqueue_script(
		'starter-admin-faq',
		starter_core_url( 'assets/admin-faq.js' ),
		array( 'jquery', 'jquery-ui-sortable' ),
		starter_core_asset_version( 'assets/admin-faq.js' ),
		true
	);
	wp_localize_script(
		'starter-admin-faq',
		'STARTER_FAQ_ADMIN',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'starter_faq_admin' ),
			'i18n'    => array(
				'confirmDelete' => __( 'Удалить этот вопрос?', 'starter' ),
				'saved'         => __( 'Порядок сохранён', 'starter' ),
				'error'         => __( 'Ошибка сохранения', 'starter' ),
			),
		)
	);
}

/**
 * FAQ location of a post ('' when empty).
 *
 * @param int $post_id FAQ ID.
 * @return string
 */
function starter_faq_location_of( int $post_id ): string {
	return (string) starter_field( 'starter_faq_location', $post_id );
}

/**
 * All FAQ posts grouped by placement. Known placements come first; unknown values get their own group.
 *
 * @return array<string, WP_Post[]>
 */
function starter_faq_admin_grouped(): array {
	$query = new WP_Query(
		array(
			'post_type'              => 'starter_faq',
			'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page'         => -1, // Admin screen: every item must be reachable.
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);

	$grouped = array_fill_keys( array_keys( starter_faq_locations() ), array() );

	foreach ( $query->posts as $post ) {
		if ( ! $post instanceof WP_Post ) {
			continue;
		}
		$location               = starter_faq_location_of( $post->ID );
		$location               = '' !== $location ? $location : 'home';
		$grouped[ $location ][] = $post;
	}

	foreach ( $grouped as $location => $posts ) {
		usort(
			$posts,
			static fn( WP_Post $a, WP_Post $b ): int => (int) starter_field( 'starter_faq_order', $a->ID ) <=> (int) starter_field( 'starter_faq_order', $b->ID )
		);
		$grouped[ $location ] = $posts;
	}

	return $grouped;
}

/**
 * One FAQ row.
 *
 * @param WP_Post $post FAQ post.
 */
function starter_faq_admin_render_row( WP_Post $post ): void {
	$order = (int) starter_field( 'starter_faq_order', $post->ID );
	$edit  = get_edit_post_link( $post->ID, 'raw' );
	?>
	<li class="starter-faq-row" data-id="<?php echo esc_attr( (string) $post->ID ); ?>">
		<span class="starter-faq-row__handle" title="<?php esc_attr_e( 'Перетащить', 'starter' ); ?>">⋮⋮</span>
		<span class="starter-faq-row__order"><?php echo esc_html( (string) $order ); ?></span>
		<div class="starter-faq-row__question">
			<?php echo esc_html( get_the_title( $post ) ); ?>
			<?php if ( 'publish' !== $post->post_status ) : ?>
				<em class="starter-faq-row__status">(<?php echo esc_html( (string) $post->post_status ); ?>)</em>
			<?php endif; ?>
		</div>
		<div class="starter-faq-row__answer"><?php echo wp_kses_post( $post->post_content ); ?></div>
		<span class="starter-faq-row__actions">
			<?php if ( $edit ) : ?>
				<a class="button button-small" href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Изменить', 'starter' ); ?></a>
			<?php endif; ?>
			<?php if ( current_user_can( 'delete_post', $post->ID ) ) : ?>
				<button type="button" class="button button-small button-link-delete starter-faq-row__delete" data-id="<?php echo esc_attr( (string) $post->ID ); ?>"><?php esc_html_e( 'Удалить', 'starter' ); ?></button>
			<?php endif; ?>
		</span>
	</li>
	<?php
}

/**
 * Manager page.
 */
function starter_faq_admin_render_page(): void {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'Недостаточно прав.', 'starter' ) );
	}

	$labels  = starter_faq_locations();
	$grouped = starter_faq_admin_grouped();
	?>
	<div class="wrap starter-faq-admin" id="starter-faq-admin">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'FAQ', 'starter' ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=starter_faq' ) ); ?>"><?php esc_html_e( 'Добавить вопрос', 'starter' ); ?></a>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( STARTER_FAQ_PARENT . '&post_status=all' ) ); ?>"><?php esc_html_e( 'Плоский список', 'starter' ); ?></a>
		<p class="starter-faq-admin__hint"><?php esc_html_e( 'Перетаскивайте строки, чтобы изменить порядок внутри места вывода.', 'starter' ); ?></p>
		<div class="starter-faq-admin__loader" id="starter-faq-loader" hidden aria-hidden="true">
			<span class="spinner is-active"></span>
			<span><?php esc_html_e( 'Сохранение…', 'starter' ); ?></span>
		</div>
		<div class="starter-faq-admin__status" id="starter-faq-status" role="status" aria-live="polite"></div>

		<?php foreach ( $grouped as $location => $items ) : ?>
			<details class="starter-faq-acc" <?php echo count( $items ) > 0 ? 'open' : ''; ?>>
				<summary class="starter-faq-acc__summary">
					<span class="starter-faq-acc__title"><?php echo esc_html( $labels[ $location ] ?? (string) $location ); ?></span>
					<code class="starter-faq-acc__key"><?php echo esc_html( (string) $location ); ?></code>
					<span class="starter-faq-acc__count"><?php echo esc_html( (string) count( $items ) ); ?></span>
				</summary>
				<?php if ( empty( $items ) ) : ?>
					<p class="starter-faq-acc__empty"><?php esc_html_e( 'Нет вопросов для этого места.', 'starter' ); ?></p>
				<?php else : ?>
					<ul class="starter-faq-list" data-location="<?php echo esc_attr( (string) $location ); ?>">
						<?php
						foreach ( $items as $post ) {
							starter_faq_admin_render_row( $post );
						}
						?>
					</ul>
				<?php endif; ?>
			</details>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * AJAX: save order within a placement.
 */
function starter_ajax_faq_reorder(): void {
	check_ajax_referer( 'starter_faq_admin', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	$location = isset( $_POST['location'] ) ? sanitize_title( wp_unslash( $_POST['location'] ) ) : '';
	$ids      = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'absint', wp_unslash( $_POST['ids'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint on every item.

	if ( '' === $location || empty( $ids ) ) {
		wp_send_json_error( array( 'message' => 'bad_request' ), 400 );
	}

	// Known placement, or reordering inside an existing (legacy/custom) group: every item already has it.
	$known = array_key_exists( $location, starter_faq_locations() );
	if ( ! $known ) {
		foreach ( $ids as $post_id ) {
			if ( starter_faq_location_of( $post_id ) !== $location ) {
				wp_send_json_error( array( 'message' => 'unknown_location' ), 400 );
			}
		}
	}

	$order = 10;
	foreach ( array_filter( $ids ) as $post_id ) {
		if ( 'starter_faq' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			continue;
		}
		starter_update_field( 'starter_faq_order', $order, $post_id );
		starter_update_field( 'starter_faq_location', $location, $post_id );
		$order += 10;
	}

	wp_send_json_success( array( 'ok' => true ) );
}

/**
 * AJAX: move a FAQ item to the trash.
 */
function starter_ajax_faq_delete(): void {
	check_ajax_referer( 'starter_faq_admin', 'nonce' );

	$post_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	if ( ! $post_id || 'starter_faq' !== get_post_type( $post_id ) ) {
		wp_send_json_error( array( 'message' => 'not_found' ), 404 );
	}

	if ( ! current_user_can( 'delete_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	if ( ! wp_trash_post( $post_id ) ) {
		wp_send_json_error( array( 'message' => 'delete_failed' ), 500 );
	}

	wp_send_json_success( array( 'id' => $post_id ) );
}
