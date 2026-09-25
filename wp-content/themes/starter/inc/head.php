<?php
/**
 * <head> output: font preload, favicon set, geo meta; removes core extras that add origins or bytes.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Preload critical fonts listed in project.config.json → fonts.preload (theme-relative woff2 paths).
 *
 * Only the few files used above the fold (K5: max 4); `crossorigin` is mandatory for fonts,
 * otherwise the preloaded file is fetched twice. URLs are unversioned so they match main.css.
 */
function starter_preload_fonts(): void {
	$files = starter_core_config( 'fonts.preload' );
	if ( ! is_array( $files ) ) {
		return;
	}

	foreach ( $files as $file ) {
		$file = ltrim( (string) $file, '/' );
		if ( ! str_ends_with( $file, '.woff2' ) || ! is_readable( get_template_directory() . '/' . $file ) ) {
			continue;
		}
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( starter_asset_url( $file ) ) );
	}
}
add_action( 'wp_head', 'starter_preload_fonts', 1 );

/**
 * Favicon set from assets/icons (ico + svg + apple-touch + manifest), versioned by filemtime.
 * Placeholders ship with the starter; replace the files with the project icons.
 */
function starter_favicon(): void {
	$icons = array(
		'favicon.ico'          => '<link rel="icon" href="%s" sizes="32x32">',
		'favicon.svg'          => '<link rel="icon" href="%s" type="image/svg+xml">',
		'apple-touch-icon.png' => '<link rel="apple-touch-icon" href="%s">',
		'site.webmanifest'     => '<link rel="manifest" href="%s">',
	);

	foreach ( $icons as $file => $tag ) {
		$relative = 'assets/icons/' . $file;
		if ( ! is_readable( get_template_directory() . '/' . $relative ) ) {
			continue;
		}
		printf( $tag . "\n", esc_url( add_query_arg( 'v', starter_asset_version( $relative ), starter_asset_url( $relative ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static tag template, URL escaped.
	}

	/**
	 * Filter the theme-color meta value ('' = not printed).
	 *
	 * @param string $color CSS color.
	 */
	$color = (string) apply_filters( 'starter_theme_color', '' );
	if ( '' !== $color ) {
		echo '<meta name="theme-color" content="' . esc_attr( $color ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'starter_favicon', 2 );

/**
 * The theme ships its own icon set: drop the Customizer Site Icon tags (duplicates).
 */
function starter_disable_site_icon(): void {
	remove_action( 'wp_head', 'wp_site_icon', 99 );
}
add_action( 'init', 'starter_disable_site_icon' );

/**
 * Geo meta for local SEO from the company address/geo. Printed only when data is present.
 */
function starter_geo_meta(): void {
	$country  = strtoupper( starter_company_value( 'address.country' ) );
	$region   = starter_company_value( 'address.region' );
	$locality = starter_company_value( 'address.locality' );
	$lat      = starter_get_company( 'geo.lat' );
	$lng      = starter_get_company( 'geo.lng' );

	if ( (bool) preg_match( '/^[A-Z]{2}(-[A-Z0-9]{1,3})?$/', $country ) ) {
		echo '<meta name="geo.region" content="' . esc_attr( $country ) . '">' . "\n";
	}

	$placename = implode( ', ', array_filter( array( $locality, $region ) ) );
	if ( '' !== $placename ) {
		echo '<meta name="geo.placename" content="' . esc_attr( $placename ) . '">' . "\n";
	}

	if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
		$position = sprintf( '%s;%s', (float) $lat, (float) $lng );
		echo '<meta name="geo.position" content="' . esc_attr( $position ) . '">' . "\n";
		echo '<meta name="ICBM" content="' . esc_attr( str_replace( ';', ', ', $position ) ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'starter_geo_meta', 3 );

/**
 * Remove the emoji detection script/styles: an inline script, a style block and a dns-prefetch
 * to a third-party origin on every page, for a feature every current browser has natively.
 */
function starter_disable_emoji(): void {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	remove_action( 'admin_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
}
add_action( 'init', 'starter_disable_emoji' );
