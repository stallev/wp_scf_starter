<?php
/**
 * Seed entity: Yoast meta (seed/yoast-meta.json → _yoast_wpseo_title / _yoast_wpseo_metadesc).
 *
 * Items key by a pages-map URL; import runs after `pages` and `posts` (see starter_seed_targets()) so
 * the page/post already exists. Title/description text lives only in the seed file — this is the SEO
 * mechanism, not the copy (patterns: docs/project/seo/meta-patterns.md).
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve a pages-map URL (front/page/service/blog-index/utility/post) to its seeded post ID.
 *
 * @param string $url Path from pages-map.json, e.g. / or /contacts/.
 * @return int 0 when the URL is not in pages-map or the page/post has not been seeded yet.
 */
function starter_seed_yoast_meta_post_id( string $url ): int {
	$page = starter_core_page_by_url( $url );
	if ( null === $page ) {
		return 0;
	}

	$type = (string) ( $page['type'] ?? '' );
	$path = trim( $url, '/' );

	if ( 'post' === $type ) {
		$segments = explode( '/', $path );
		return starter_seed_find_post_id( (string) end( $segments ), 'post' );
	}

	if ( 'front' === $type && '' === $path ) {
		$path = 'home';
	}

	return starter_seed_find_post_id( $path, 'page' );
}

/**
 * Import yoast-meta.json. Item: url (pages-map path), title, description.
 *
 * @return array{created: int, updated: int, unchanged: int, skipped: int, errors: string[], warnings: string[]}
 */
function starter_seed_import_yoast_meta(): array {
	$stats = starter_seed_stats();
	$data  = starter_seed_load_for( 'yoast-meta.json', $stats );
	if ( null === $data ) {
		return $stats;
	}

	foreach ( starter_seed_items( $data ) as $item ) {
		$url     = (string) ( $item['url'] ?? '' );
		$post_id = starter_seed_yoast_meta_post_id( $url );
		if ( $post_id <= 0 ) {
			++$stats['skipped'];
			$stats['warnings'][] = sprintf( 'yoast-meta: url %s not found (add it to pages-map / posts.json and seed pages/posts first)', $url );
			continue;
		}

		$fields = array(
			'_yoast_wpseo_title'    => (string) ( $item['title'] ?? '' ),
			'_yoast_wpseo_metadesc' => (string) ( $item['description'] ?? '' ),
		);

		$changed = false;
		foreach ( $fields as $key => $value ) {
			if ( '' === $value || (string) get_post_meta( $post_id, $key, true ) === $value ) {
				continue;
			}
			$changed = true;
			starter_seed_update_post_meta( $post_id, $key, $value );
		}

		++$stats[ $changed ? 'updated' : 'unchanged' ];
	}

	return $stats;
}
