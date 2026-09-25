<?php
/**
 * Theme setup: supports, menu locations, card image size.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Theme supports, menus and image sizes.
 */
function starter_theme_setup(): void {
	load_theme_textdomain( 'starter', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);

	register_nav_menus(
		array(
			'primary' => __( 'Главное меню', 'starter' ),
			'mobile'  => __( 'Мобильное меню', 'starter' ),
			'footer'  => __( 'Меню в подвале', 'starter' ),
		)
	);

	$card = starter_card_size();
	add_image_size( $card['name'], $card['width'], 0, false );
}
add_action( 'after_setup_theme', 'starter_theme_setup' );

/**
 * Card image size from project.config.json → images.card (name, width; height is proportional).
 *
 * @return array{name: string, width: int}
 */
function starter_card_size(): array {
	$name  = starter_core_config( 'images.card.name' );
	$width = starter_core_config( 'images.card.width' );

	return array(
		'name'  => is_string( $name ) && '' !== $name ? sanitize_key( $name ) : 'card',
		'width' => is_numeric( $width ) && (int) $width > 0 ? (int) $width : 768,
	);
}

/**
 * Show the card size in the Media Library size chooser.
 *
 * @param array<string, string> $sizes Size name => label.
 * @return array<string, string>
 */
function starter_image_size_names( array $sizes ): array {
	$card = starter_card_size();

	/* translators: %d: card image width in pixels. */
	$sizes[ $card['name'] ] = sprintf( __( 'Карточка %d', 'starter' ), $card['width'] );

	return $sizes;
}
add_filter( 'image_size_names_choose', 'starter_image_size_names' );

/**
 * Content width for embeds and full-size images inside post content.
 */
function starter_content_width(): void {
	$GLOBALS['content_width'] = (int) apply_filters( 'starter_content_width', 760 );
}
add_action( 'after_setup_theme', 'starter_content_width', 0 );
