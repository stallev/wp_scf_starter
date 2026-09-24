<?php
/**
 * Seed entity: portfolio projects (seed/projects.json → starter_project).
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Import projects.json. Item: slug, title, excerpt, content, location, date_label, service, image, order.
 *
 * @return array{created: int, updated: int, unchanged: int, skipped: int, errors: string[], warnings: string[]}
 */
function starter_seed_import_projects(): array {
	$stats = starter_seed_stats();
	$data  = starter_seed_load_for( 'projects.json', $stats );
	if ( null === $data ) {
		return $stats;
	}

	foreach ( starter_seed_items( $data ) as $item ) {
		$title   = (string) ( $item['title'] ?? '' );
		$post_id = starter_seed_count(
			starter_seed_upsert_post(
				'starter_project',
				array(
					'slug'       => (string) ( $item['slug'] ?? '' ),
					'title'      => $title,
					'excerpt'    => (string) ( $item['excerpt'] ?? '' ),
					'content'    => (string) ( $item['content'] ?? '' ),
					'menu_order' => (int) ( $item['order'] ?? 0 ),
				)
			),
			$stats
		);
		if ( $post_id <= 0 ) {
			continue;
		}

		starter_seed_update_field( 'starter_project_location', (string) ( $item['location'] ?? '' ), $post_id );
		starter_seed_update_field( 'starter_project_date_label', (string) ( $item['date_label'] ?? '' ), $post_id );
		starter_seed_update_field( 'starter_project_service', sanitize_title( (string) ( $item['service'] ?? '' ) ), $post_id );
		starter_seed_set_featured_image( $post_id, (string) ( $item['image'] ?? '' ), $title, $stats );
	}

	return $stats;
}
