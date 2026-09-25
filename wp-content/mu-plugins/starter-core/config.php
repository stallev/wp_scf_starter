<?php
/**
 * Runtime project config (generated snapshot of project.config.json + pages-map.json).
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read the project config snapshot.
 *
 * Source: config.generated.php, built by `npm run build:config` (the repo root is not deployed).
 * Keys: project, slug, locale, urls, fonts, images, analytics, modules, pages.
 *
 * @param string|null $key Dotted path (e.g. 'modules.catalog', 'images.card.width'); null = whole array.
 * @return mixed Value, or null when the key is missing.
 */
function starter_core_config( ?string $key = null ) {
	static $config = null;

	if ( null === $config ) {
		$file   = STARTER_CORE_PATH . '/config.generated.php';
		$loaded = is_readable( $file ) ? require $file : array();
		$config = is_array( $loaded ) ? $loaded : array();
	}

	if ( null === $key || '' === $key ) {
		return $config;
	}

	return starter_get_by_path( $config, $key );
}

/**
 * Pages from pages-map (compact form: url, title, template, type, noindex, lead_form, schema).
 *
 * @return array<int, array<string, mixed>>
 */
function starter_core_pages(): array {
	$pages = starter_core_config( 'pages' );
	if ( ! is_array( $pages ) ) {
		return array();
	}

	return array_values( array_filter( $pages, 'is_array' ) );
}

/**
 * Entry of pages-map for a public path.
 *
 * @param string $url Path with leading/trailing slash, e.g. /contacts/.
 * @return array<string, mixed>|null
 */
function starter_core_page_by_url( string $url ): ?array {
	$url = '/' . trim( $url, '/' ) . '/';
	$url = '//' === $url ? '/' : $url;

	foreach ( starter_core_pages() as $page ) {
		if ( isset( $page['url'] ) && $page['url'] === $url ) {
			return $page;
		}
	}

	return null;
}
