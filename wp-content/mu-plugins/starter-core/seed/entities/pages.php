<?php
/**
 * Seed entity: pages from pages-map (config.generated.php → pages). No-op when pages-map is empty.
 *
 * Creates missing pages and keeps titles/parents in sync; content is never overwritten (editors own it).
 * Sets the static front page and the posts page from the `front` / `blog-index` types.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Import pages from pages-map.
 *
 * @return array{created: int, updated: int, unchanged: int, skipped: int, errors: string[], warnings: string[]}
 */
function starter_seed_import_pages(): array {
	$stats = starter_seed_stats();
	$pages = array();

	foreach ( starter_core_pages() as $page ) {
		$type = (string) ( $page['type'] ?? '' );
		if ( in_array( $type, array( 'front', 'page', 'service', 'blog-index', 'utility' ), true ) ) {
			$pages[] = $page;
		}
	}

	// Parents before children.
	usort( $pages, static fn( array $a, array $b ): int => substr_count( (string) $a['url'], '/' ) <=> substr_count( (string) $b['url'], '/' ) );

	$templates = array_keys( wp_get_theme()->get_page_templates( null, 'page' ) );

	foreach ( $pages as $page ) {
		$type = (string) $page['type'];
		$path = trim( (string) $page['url'], '/' );
		$path = 'front' === $type && '' === $path ? 'home' : $path;

		$segments = explode( '/', $path );
		$slug     = (string) array_pop( $segments );
		$parent   = 0;
		if ( $segments ) {
			$parent = starter_seed_find_post_id( implode( '/', $segments ), 'page' );
			if ( 0 === $parent ) {
				$stats['warnings'][] = sprintf( 'page %s: parent not found, created at top level', (string) $page['url'] );
			}
		}

		$result  = starter_seed_upsert_post(
			'page',
			array(
				'slug'   => $slug,
				'path'   => $path,
				'title'  => (string) $page['title'],
				'parent' => $parent,
			)
		);
		$page_id = starter_seed_count( $result, $stats );
		if ( $page_id <= 0 || starter_seed_is_dry_run() ) {
			continue;
		}

		$template = (string) ( $page['template'] ?? '' );
		if ( in_array( $template, $templates, true ) && get_post_meta( $page_id, '_wp_page_template', true ) !== $template ) {
			update_post_meta( $page_id, '_wp_page_template', $template );
		}

		if ( 'front' === $type ) {
			starter_seed_set_option( 'show_on_front', 'page' );
			starter_seed_set_option( 'page_on_front', $page_id );
		} elseif ( 'blog-index' === $type ) {
			starter_seed_set_option( 'page_for_posts', $page_id );
		}
	}

	return $stats;
}

/**
 * Update an option only when the value differs (no-op in dry-run).
 *
 * @param string     $name  Option.
 * @param int|string $value Value.
 */
function starter_seed_set_option( string $name, $value ): void {
	if ( starter_seed_is_dry_run() || (string) get_option( $name ) === (string) $value ) {
		return;
	}

	update_option( $name, $value );
}
