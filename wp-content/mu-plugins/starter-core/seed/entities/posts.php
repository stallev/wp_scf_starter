<?php
/**
 * Seed entity: blog posts (seed/posts.json → post + categories).
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Import posts.json. Item: slug, title, excerpt, content, date (Y-m-d), categories [{name, slug}], image.
 *
 * @return array{created: int, updated: int, unchanged: int, skipped: int, errors: string[], warnings: string[]}
 */
function starter_seed_import_posts(): array {
	$stats = starter_seed_stats();
	$data  = starter_seed_load_for( 'posts.json', $stats );
	if ( null === $data ) {
		return $stats;
	}

	foreach ( starter_seed_items( $data ) as $item ) {
		$title   = (string) ( $item['title'] ?? '' );
		$post_id = starter_seed_count(
			starter_seed_upsert_post(
				'post',
				array(
					'slug'    => (string) ( $item['slug'] ?? '' ),
					'title'   => $title,
					'excerpt' => (string) ( $item['excerpt'] ?? '' ),
					'content' => (string) ( $item['content'] ?? '' ),
					'date'    => (string) ( $item['date'] ?? '' ),
				)
			),
			$stats
		);
		if ( $post_id <= 0 ) {
			continue;
		}

		if ( ! empty( $item['categories'] ) && is_array( $item['categories'] ) && ! starter_seed_is_dry_run() ) {
			$term_ids = starter_seed_categories( $item['categories'], $stats );
			$current  = wp_get_post_categories( $post_id );
			sort( $term_ids );
			sort( $current );
			if ( $term_ids && $term_ids !== $current ) {
				wp_set_post_categories( $post_id, $term_ids, false );
			}
		}

		starter_seed_set_featured_image( $post_id, (string) ( $item['image'] ?? '' ), $title, $stats );
	}

	return $stats;
}

/**
 * Find or create categories by slug.
 *
 * @param array<int, mixed>                                                                                     $categories [{name, slug}] or names.
 * @param array{created: int, updated: int, unchanged: int, skipped: int, errors: string[], warnings: string[]} $stats      Stats (by reference).
 * @return int[] Term IDs.
 */
function starter_seed_categories( array $categories, array &$stats ): array {
	$ids = array();

	foreach ( $categories as $category ) {
		$name = is_array( $category ) ? (string) ( $category['name'] ?? '' ) : (string) $category;
		$slug = is_array( $category ) && ! empty( $category['slug'] ) ? sanitize_title( (string) $category['slug'] ) : sanitize_title( $name );
		if ( '' === $name ) {
			continue;
		}

		$term = get_term_by( 'slug', $slug, 'category' );
		if ( $term instanceof WP_Term ) {
			$ids[] = (int) $term->term_id;
			continue;
		}

		$inserted = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
		if ( is_wp_error( $inserted ) ) {
			$stats['warnings'][] = sprintf( 'category %s: %s', $slug, $inserted->get_error_message() );
			continue;
		}
		$ids[] = (int) $inserted['term_id'];
	}

	return $ids;
}
