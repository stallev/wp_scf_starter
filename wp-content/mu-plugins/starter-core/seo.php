<?php
/**
 * SEO integration with Yoast: custom graph pieces, Organization data, noindex + sitemap exclusion.
 *
 * Rule: no second JSON-LD block outside the Yoast graph — every schema piece goes through
 * `wpseo_schema_graph_pieces`.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

require_once STARTER_CORE_PATH . '/seo/class-starter-schema-localbusiness.php';
require_once STARTER_CORE_PATH . '/seo/class-starter-schema-service.php';
require_once STARTER_CORE_PATH . '/seo/class-starter-schema-faqpage.php';

add_filter( 'wpseo_schema_graph_pieces', 'starter_yoast_schema_graph_pieces', 11, 2 );
add_filter( 'wpseo_schema_organization', 'starter_yoast_schema_organization', 11 );
add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', 'starter_yoast_exclude_noindex_from_sitemap' );
add_filter( 'wpseo_robots', 'starter_yoast_noindex_robots', 11 );

/**
 * Stable sitewide @id of the LocalBusiness entity.
 *
 * @return string
 */
function starter_schema_localbusiness_id(): string {
	return trailingslashit( home_url( '/' ) ) . '#/schema/localbusiness';
}

/**
 * Places for schema.org areaServed from the company options (one place per line).
 *
 * @return array<int, array{'@type': string, name: string}>
 */
function starter_schema_area_served(): array {
	$areas = starter_get_company( 'area_served' );
	$areas = is_array( $areas ) ? $areas : array();

	$result = array();
	foreach ( $areas as $name ) {
		$result[] = array(
			'@type' => 'Place',
			'name'  => (string) $name,
		);
	}

	/**
	 * Filter schema.org areaServed (e.g. switch to City / AdministrativeArea types).
	 *
	 * @param array<int, array<string, string>> $result Places.
	 */
	return (array) apply_filters( 'starter_schema_area_served', $result );
}

/**
 * Canonical URL of the current page (Yoast when available).
 *
 * @return string
 */
function starter_schema_canonical(): string {
	if ( function_exists( 'YoastSEO' ) ) {
		$meta = YoastSEO()->meta->for_current_page();
		if ( $meta && ! empty( $meta->canonical ) ) {
			return (string) $meta->canonical;
		}
	}

	// Yoast leaves canonical empty on noindex pages (e.g. staging): fall back to the permalink.
	if ( is_singular() && ! is_front_page() ) {
		$permalink = get_permalink( get_queried_object_id() );
		if ( is_string( $permalink ) && '' !== $permalink ) {
			return $permalink;
		}
	}

	return home_url( '/' );
}

/**
 * Entry of pages-map for the current singular page (by its permalink path).
 *
 * @return array<string, mixed>|null
 */
function starter_current_page_config(): ?array {
	if ( is_front_page() ) {
		foreach ( starter_core_pages() as $page ) {
			if ( 'front' === ( $page['type'] ?? '' ) ) {
				return $page;
			}
		}
		return null;
	}

	if ( ! is_singular() ) {
		return null;
	}

	$path = (string) wp_parse_url( (string) get_permalink( get_queried_object_id() ), PHP_URL_PATH );

	return starter_core_page_by_url( $path );
}

/**
 * Add starter graph pieces to the Yoast graph.
 *
 * @param array<int, mixed> $pieces  Graph pieces.
 * @param mixed             $context Yoast Meta_Tags_Context.
 * @return array<int, mixed>
 */
function starter_yoast_schema_graph_pieces( $pieces, $context = null ): array {
	$pieces = is_array( $pieces ) ? $pieces : array();

	$pieces[] = new Starter_Schema_LocalBusiness( $context );
	$pieces[] = new Starter_Schema_Service( $context );
	$pieces[] = new Starter_Schema_FAQPage( $context );

	return $pieces;
}

/**
 * Align the Yoast Organization name with the company options.
 *
 * @param mixed $data Organization piece.
 * @return mixed
 */
function starter_yoast_schema_organization( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}

	$name  = (string) starter_get_company( 'name' );
	$legal = (string) starter_get_company( 'legal_name' );

	if ( '' !== $name ) {
		$data['name'] = $name;
	}
	if ( '' !== $legal ) {
		$data['legalName'] = $legal;
	}

	return $data;
}

/**
 * Page IDs marked `noindex` in pages-map.
 *
 * @return int[]
 */
function starter_noindex_page_ids(): array {
	static $ids = null;

	if ( null !== $ids ) {
		return $ids;
	}

	$ids = array();
	foreach ( starter_core_pages() as $page ) {
		if ( empty( $page['noindex'] ) || 'front' === ( $page['type'] ?? '' ) ) {
			continue;
		}
		$found = get_page_by_path( trim( (string) ( $page['url'] ?? '' ), '/' ) );
		if ( $found instanceof WP_Post ) {
			$ids[] = (int) $found->ID;
		}
	}

	return $ids;
}

/**
 * Exclude noindex pages from Yoast XML sitemaps.
 *
 * @param mixed $excluded Post IDs already excluded.
 * @return int[]
 */
function starter_yoast_exclude_noindex_from_sitemap( $excluded = array() ): array {
	$excluded = is_array( $excluded ) ? $excluded : array();

	return array_values( array_unique( array_map( 'intval', array_merge( $excluded, starter_noindex_page_ids() ) ) ) );
}

/**
 * Force `noindex, follow` on pages marked noindex in pages-map.
 *
 * @param mixed $robots Yoast robots string.
 * @return mixed
 */
function starter_yoast_noindex_robots( $robots ) {
	if ( ! is_singular() || ! in_array( (int) get_queried_object_id(), starter_noindex_page_ids(), true ) ) {
		return $robots;
	}

	return 'noindex, follow';
}
