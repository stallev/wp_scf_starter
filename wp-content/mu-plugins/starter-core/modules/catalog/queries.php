<?php
/**
 * Data provider for catalog templates.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Published products, optionally filtered by family.
 *
 * @param array{family?: string, limit?: int} $args family: starter_product_family term slug; limit: -1 = all.
 * @return WP_Post[]
 */
function starter_get_products( array $args = array() ): array {
	$family = sanitize_title( (string) ( $args['family'] ?? '' ) );
	$limit  = (int) ( $args['limit'] ?? -1 );

	$query_args = array(
		'post_type'              => 'starter_product',
		'post_status'            => 'publish',
		'posts_per_page'         => $limit,
		'orderby'                => array(
			'menu_order' => 'ASC',
			'title'      => 'ASC',
		),
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
	);

	if ( '' !== $family ) {
		$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- small CPT, family filter.
			array(
				'taxonomy' => 'starter_product_family',
				'field'    => 'slug',
				'terms'    => $family,
			),
		);
	}

	$query = new WP_Query( $query_args );

	return array_values( array_filter( $query->posts, static fn( $post ) => $post instanceof WP_Post ) );
}
