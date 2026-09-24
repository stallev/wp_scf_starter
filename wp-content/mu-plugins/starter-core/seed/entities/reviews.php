<?php
/**
 * Seed entity: reviews (seed/reviews.json → starter_review).
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Import reviews.json. Item: slug, title, content, author, location, service, date_label, rating, source_url, order.
 *
 * @return array{created: int, updated: int, unchanged: int, skipped: int, errors: string[], warnings: string[]}
 */
function starter_seed_import_reviews(): array {
	$stats = starter_seed_stats();
	$data  = starter_seed_load_for( 'reviews.json', $stats );
	if ( null === $data ) {
		return $stats;
	}

	foreach ( starter_seed_items( $data ) as $item ) {
		$post_id = starter_seed_count(
			starter_seed_upsert_post(
				'starter_review',
				array(
					'slug'       => (string) ( $item['slug'] ?? '' ),
					'title'      => (string) ( $item['title'] ?? '' ),
					'content'    => (string) ( $item['content'] ?? '' ),
					'menu_order' => (int) ( $item['order'] ?? 0 ),
				)
			),
			$stats
		);
		if ( $post_id <= 0 ) {
			continue;
		}

		starter_seed_update_field( 'starter_review_author', (string) ( $item['author'] ?? '' ), $post_id );
		starter_seed_update_field( 'starter_review_location', (string) ( $item['location'] ?? '' ), $post_id );
		starter_seed_update_field( 'starter_review_service', (string) ( $item['service'] ?? '' ), $post_id );
		starter_seed_update_field( 'starter_review_date_label', (string) ( $item['date_label'] ?? '' ), $post_id );
		starter_seed_update_field( 'starter_review_rating', max( 1, min( 5, (int) ( $item['rating'] ?? 5 ) ) ), $post_id );
		starter_seed_update_field( 'starter_review_source_url', esc_url_raw( (string) ( $item['source_url'] ?? '' ) ), $post_id );
	}

	return $stats;
}
