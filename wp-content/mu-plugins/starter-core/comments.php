<?php
/**
 * Comments off (corporate sites have no comment UI; open comments without a template are a spam sink).
 *
 * Filter `starter_disable_comments` (default true) turns the whole module off, e.g. for a project
 * that ships comments_template() in its theme.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether comments are disabled site-wide.
 *
 * @return bool
 */
function starter_comments_disabled(): bool {
	/**
	 * Filter whether comments and pingbacks are disabled site-wide.
	 *
	 * @param bool $disabled Default true.
	 */
	return (bool) apply_filters( 'starter_disable_comments', true );
}

/**
 * Wire the hooks (after themes/plugins had a chance to add the filter).
 */
function starter_comments_bootstrap(): void {
	if ( ! starter_comments_disabled() ) {
		return;
	}

	add_filter( 'comments_open', '__return_false', 20 );
	add_filter( 'pings_open', '__return_false', 20 );
	add_filter( 'comments_array', '__return_empty_array', 20 );
	add_filter( 'get_comments_number', '__return_zero', 20 );
	add_filter( 'feed_links_show_comments_feed', '__return_false' );
	add_filter( 'rest_endpoints', 'starter_comments_rest_endpoints' );
	add_filter( 'xmlrpc_methods', 'starter_comments_xmlrpc_methods' );
	add_filter( 'wp_headers', 'starter_comments_headers' );
	add_action( 'init', 'starter_comments_remove_support', 100 );
	add_action( 'admin_menu', 'starter_comments_admin_menu' );
	add_action( 'admin_init', 'starter_comments_admin_redirect' );
	add_action( 'admin_bar_menu', 'starter_comments_admin_bar', 999 );
	add_action( 'template_redirect', 'starter_comments_block_feed', 9 );
	add_action( 'pre_comment_on_post', 'starter_comments_block_post' );
}
add_action( 'after_setup_theme', 'starter_comments_bootstrap', 100 );

/**
 * Remove comments/trackbacks support from every post type.
 */
function starter_comments_remove_support(): void {
	foreach ( get_post_types() as $post_type ) {
		if ( post_type_supports( $post_type, 'comments' ) ) {
			remove_post_type_support( $post_type, 'comments' );
		}
		if ( post_type_supports( $post_type, 'trackbacks' ) ) {
			remove_post_type_support( $post_type, 'trackbacks' );
		}
	}
}

/**
 * Hide Comments and Settings → Discussion in the admin menu.
 */
function starter_comments_admin_menu(): void {
	remove_menu_page( 'edit-comments.php' );
	remove_submenu_page( 'options-general.php', 'options-discussion.php' );
}

/**
 * Send direct visits of the comments screens back to the dashboard.
 */
function starter_comments_admin_redirect(): void {
	global $pagenow;

	if ( in_array( $pagenow, array( 'edit-comments.php', 'comment.php', 'options-discussion.php' ), true ) ) {
		wp_safe_redirect( admin_url() );
		exit;
	}
}

/**
 * Remove the comments node from the admin bar.
 *
 * @param WP_Admin_Bar $bar Admin bar.
 */
function starter_comments_admin_bar( $bar ): void {
	if ( $bar instanceof WP_Admin_Bar ) {
		$bar->remove_node( 'comments' );
	}
}

/**
 * Reject comment submissions (wp-comments-post.php) with 403.
 */
function starter_comments_block_post(): void {
	wp_die( esc_html__( 'Комментарии отключены.', 'starter' ), '', array( 'response' => 403 ) );
}

/**
 * Comment feeds → 404.
 */
function starter_comments_block_feed(): void {
	if ( is_comment_feed() ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
	}
}

/**
 * Drop the comments REST routes.
 *
 * @param array<string, mixed> $endpoints Routes.
 * @return array<string, mixed>
 */
function starter_comments_rest_endpoints( $endpoints ): array {
	$endpoints = is_array( $endpoints ) ? $endpoints : array();
	foreach ( array_keys( $endpoints ) as $route ) {
		if ( str_starts_with( (string) $route, '/wp/v2/comments' ) ) {
			unset( $endpoints[ $route ] );
		}
	}

	return $endpoints;
}

/**
 * Drop pingback XML-RPC methods.
 *
 * @param array<string, mixed> $methods Methods.
 * @return array<string, mixed>
 */
function starter_comments_xmlrpc_methods( $methods ): array {
	$methods = is_array( $methods ) ? $methods : array();
	unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'], $methods['wp.newComment'] );

	return $methods;
}

/**
 * Remove the X-Pingback header.
 *
 * @param array<string, string> $headers Headers.
 * @return array<string, string>
 */
function starter_comments_headers( $headers ): array {
	$headers = is_array( $headers ) ? $headers : array();
	unset( $headers['X-Pingback'] );

	return $headers;
}
