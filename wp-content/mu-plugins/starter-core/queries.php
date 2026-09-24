<?php
/**
 * Data providers for theme templates: FAQ, reviews, projects, service cards.
 *
 * Templates call these instead of building WP_Query themselves.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Published FAQ items for a placement, ordered by starter_faq_order.
 *
 * @param string $location Placement key: 'home' or a page slug (see starter_faq_locations()).
 * @return WP_Post[]
 */
function starter_get_faqs_for( string $location ): array {
	$location = sanitize_title( $location );
	if ( '' === $location ) {
		return array();
	}

	$query = new WP_Query(
		array(
			'post_type'              => 'starter_faq',
			'post_status'            => 'publish',
			'posts_per_page'         => 100,
			'meta_key'               => 'starter_faq_order', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- small CPT, ordering by field.
			'orderby'                => array(
				'meta_value_num' => 'ASC',
				'title'          => 'ASC',
			),
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small CPT, placement filter.
				array(
					'key'   => 'starter_faq_location',
					'value' => $location,
				),
			),
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);

	return array_values( array_filter( $query->posts, static fn( $post ) => $post instanceof WP_Post ) );
}

/**
 * FAQ placement of the current request: 'home' on the front page, the post slug on singular views.
 *
 * @return string '' when the request has no placement.
 */
function starter_faq_current_location(): string {
	$location = '';

	if ( is_front_page() ) {
		$location = 'home';
	} elseif ( is_singular() ) {
		$location = (string) get_post_field( 'post_name', get_queried_object_id() );
	}

	/**
	 * Filter the FAQ placement of the current request.
	 *
	 * @param string $location Placement key.
	 */
	return (string) apply_filters( 'starter_faq_current_location', $location );
}

/**
 * Published reviews (menu_order, then newest).
 *
 * @param int $limit Max items, -1 = all.
 * @return WP_Post[]
 */
function starter_get_reviews( int $limit = -1 ): array {
	$query = new WP_Query(
		array(
			'post_type'              => 'starter_review',
			'post_status'            => 'publish',
			'posts_per_page'         => $limit,
			'orderby'                => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);

	return array_values( array_filter( $query->posts, static fn( $post ) => $post instanceof WP_Post ) );
}

/**
 * Published portfolio projects.
 *
 * @param array{service?: string, limit?: int} $args service: starter_project_service key; limit: -1 = all.
 * @return WP_Post[]
 */
function starter_get_projects( array $args = array() ): array {
	$service = sanitize_title( (string) ( $args['service'] ?? '' ) );
	$limit   = (int) ( $args['limit'] ?? -1 );

	$query_args = array(
		'post_type'              => 'starter_project',
		'post_status'            => 'publish',
		'posts_per_page'         => $limit,
		'orderby'                => array(
			'menu_order' => 'ASC',
			'date'       => 'DESC',
		),
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
	);

	if ( '' !== $service ) {
		$query_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small CPT, service filter.
			array(
				'key'   => 'starter_project_service',
				'value' => $service,
			),
		);
	}

	$query = new WP_Query( $query_args );

	return array_values( array_filter( $query->posts, static fn( $post ) => $post instanceof WP_Post ) );
}

/**
 * Pages with an enabled service card, ordered by starter_service_card_order.
 *
 * Card data: starter_get_service_card( $page ).
 *
 * @return WP_Post[]
 */
function starter_get_service_card_pages(): array {
	$query = new WP_Query(
		array(
			'post_type'              => 'page',
			'post_status'            => 'publish',
			'posts_per_page'         => 50,
			'meta_key'               => 'starter_service_card_order', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- ordering by field.
			'orderby'                => array(
				'meta_value_num' => 'ASC',
				'title'          => 'ASC',
			),
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- enabled flag.
				array(
					'key'   => 'starter_service_card_enabled',
					'value' => '1',
				),
			),
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);

	return array_values( array_filter( $query->posts, static fn( $post ) => $post instanceof WP_Post ) );
}

/**
 * Service card view data for a page.
 *
 * @param WP_Post $page Page with an enabled card.
 * @return array{title: string, text: string, price: string, price_note: string, badge: string, image_id: int, url: string}
 */
function starter_get_service_card( WP_Post $page ): array {
	$title    = trim( (string) starter_field( 'starter_service_card_title', $page->ID ) );
	$image_id = (int) starter_field( 'starter_service_card_image', $page->ID );

	if ( $image_id <= 0 ) {
		$image_id = (int) get_post_thumbnail_id( $page );
	}
	if ( $image_id <= 0 ) {
		$image_id = starter_get_default_image_id();
	}

	return array(
		'title'      => '' !== $title ? $title : get_the_title( $page ),
		'text'       => (string) starter_field( 'starter_service_card_text', $page->ID ),
		'price'      => (string) starter_field( 'starter_service_card_price', $page->ID ),
		'price_note' => (string) starter_field( 'starter_service_card_price_note', $page->ID ),
		'badge'      => (string) starter_field( 'starter_service_card_badge', $page->ID ),
		'image_id'   => $image_id,
		'url'        => (string) get_permalink( $page ),
	);
}
