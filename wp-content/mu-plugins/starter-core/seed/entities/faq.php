<?php
/**
 * Seed entity: FAQ (seed/faq.json → starter_faq).
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Import faq.json. Item: slug, title (question), content (answer), location, order.
 *
 * @return array{created: int, updated: int, unchanged: int, skipped: int, errors: string[], warnings: string[]}
 */
function starter_seed_import_faq(): array {
	$stats = starter_seed_stats();
	$data  = starter_seed_load_for( 'faq.json', $stats );
	if ( null === $data ) {
		return $stats;
	}

	foreach ( starter_seed_items( $data ) as $item ) {
		$post_id = starter_seed_count(
			starter_seed_upsert_post(
				'starter_faq',
				array(
					'slug'    => (string) ( $item['slug'] ?? '' ),
					'title'   => (string) ( $item['title'] ?? '' ),
					'content' => (string) ( $item['content'] ?? '' ),
				)
			),
			$stats
		);
		if ( $post_id <= 0 ) {
			continue;
		}

		starter_seed_update_field( 'starter_faq_location', sanitize_title( (string) ( $item['location'] ?? 'home' ) ), $post_id );
		starter_seed_update_field( 'starter_faq_order', (int) ( $item['order'] ?? 10 ), $post_id );
	}

	return $stats;
}
