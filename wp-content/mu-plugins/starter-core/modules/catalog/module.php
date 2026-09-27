<?php
/**
 * Catalog example module: CPT `starter_product` + hierarchical taxonomy `starter_product_family`.
 *
 * Loaded only when project.config.json → modules.catalog is true (see boot.php). Pattern this module
 * demonstrates: a public catalog CPT with its own archive/taxonomy/single templates delivered via
 * `template_include` (no theme changes needed to try the module), a Yoast Product/Offer graph piece,
 * and seed targets for demo data. See README.md for the URL model and how to enable it.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/fields/product.php';
require_once __DIR__ . '/queries.php';
require_once __DIR__ . '/seo/class-starter-schema-productoffer.php';
require_once __DIR__ . '/seed.php';

/**
 * Base slug for the catalog archive and product rewrite (default 'catalog').
 *
 * @return string
 */
function starter_catalog_slug(): string {
	/**
	 * Filter the catalog base slug.
	 *
	 * @param string $slug Slug.
	 */
	return (string) apply_filters( 'starter_catalog_slug', 'catalog' );
}

/**
 * Register the `starter_product` CPT.
 */
function starter_register_catalog_post_type(): void {
	$slug = starter_catalog_slug();

	$args = array(
		'labels'        => array(
			'name'          => __( 'Товары', 'starter' ),
			'singular_name' => __( 'Товар', 'starter' ),
			'add_new_item'  => __( 'Добавить товар', 'starter' ),
			'edit_item'     => __( 'Редактировать товар', 'starter' ),
			'search_items'  => __( 'Искать товары', 'starter' ),
			'not_found'     => __( 'Товары не найдены', 'starter' ),
			'menu_name'     => __( 'Каталог', 'starter' ),
		),
		'public'        => true,
		'show_in_rest'  => true,
		'has_archive'   => true,
		'menu_position' => 24,
		'menu_icon'     => 'dashicons-cart',
		'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
		'rewrite'       => array(
			'slug'       => $slug,
			'with_front' => false,
		),
	);

	/** Reuse the core filter so a project can change catalog CPT args the same way as any other CPT. */
	$args = (array) apply_filters( 'starter_post_type_args', $args, 'starter_product' );

	register_post_type( 'starter_product', $args );
}
add_action( 'init', 'starter_register_catalog_post_type' );

/**
 * Register the `starter_product_family` taxonomy (hierarchical, own archive under the catalog slug).
 *
 * Priority 0: before the CPT-affecting rewrite flush check on `init` (99), and consistently early so
 * term archive rewrite rules are always present.
 */
function starter_register_catalog_taxonomy(): void {
	$slug = starter_catalog_slug();

	register_taxonomy(
		'starter_product_family',
		array( 'starter_product' ),
		array(
			'labels'            => array(
				'name'          => __( 'Категории каталога', 'starter' ),
				'singular_name' => __( 'Категория', 'starter' ),
				'search_items'  => __( 'Искать категории', 'starter' ),
				'all_items'     => __( 'Все категории', 'starter' ),
				'edit_item'     => __( 'Редактировать категорию', 'starter' ),
				'add_new_item'  => __( 'Добавить категорию', 'starter' ),
				'menu_name'     => __( 'Категории', 'starter' ),
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array(
				'slug'         => $slug . '/family',
				'with_front'   => false,
				'hierarchical' => true,
			),
		)
	);
}
add_action( 'init', 'starter_register_catalog_taxonomy', 0 );

/**
 * Serve the module's own archive/taxonomy/single templates for the catalog.
 *
 * @param string $template Template chosen by WordPress.
 * @return string
 */
function starter_catalog_template_include( string $template ): string {
	if ( is_singular( 'starter_product' ) ) {
		return __DIR__ . '/templates/single-starter-product.php';
	}
	if ( is_tax( 'starter_product_family' ) ) {
		return __DIR__ . '/templates/taxonomy-starter-product-family.php';
	}
	if ( is_post_type_archive( 'starter_product' ) ) {
		return __DIR__ . '/templates/archive-starter-product.php';
	}

	return $template;
}
add_filter( 'template_include', 'starter_catalog_template_include' );

/**
 * Render a product card: theme part `template-parts/product-card.php` when the project has one,
 * otherwise the module's own fallback part. Runs inside The Loop (the_post() already called).
 *
 * @param array{priority?: bool, reveal?: bool} $args priority: LCP image; reveal: wrap in .reveal.
 */
function starter_catalog_render_product_card( array $args = array() ): void {
	$starter_catalog_card_args = wp_parse_args(
		$args,
		array(
			'priority' => false,
			'reveal'   => true,
		)
	);

	if ( locate_template( 'template-parts/product-card.php' ) ) {
		get_template_part( 'template-parts/product-card', null, $starter_catalog_card_args );
		return;
	}

	include __DIR__ . '/template-parts/product-card-fallback.php';
}

/**
 * Add the Product/Offer piece to the Yoast graph on catalog archive, taxonomy and single pages.
 *
 * @param array<int, mixed> $pieces  Graph pieces.
 * @param mixed             $context Yoast Meta_Tags_Context.
 * @return array<int, mixed>
 */
function starter_catalog_schema_graph_pieces( $pieces, $context = null ): array {
	$pieces   = is_array( $pieces ) ? $pieces : array();
	$pieces[] = new Starter_Schema_ProductOffer( $context );

	return $pieces;
}
add_filter( 'wpseo_schema_graph_pieces', 'starter_catalog_schema_graph_pieces', 12, 2 );
