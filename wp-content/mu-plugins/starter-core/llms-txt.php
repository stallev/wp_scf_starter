<?php
/**
 * Curated /llms.txt for AI discovery + explicit Allow groups for AI crawlers in robots.txt.
 *
 * Body: data/llms.txt.md (projects edit it). Placeholders are replaced at serve time:
 *   {{site_name}}, {{site_description}}, {{origin}} (home URL without trailing slash),
 *   {{pages}} (Markdown list of indexable pages from pages-map), {{phone}}, {{email}}, {{address}}.
 * Keep Yoast's own llms.txt feature off, otherwise two sources compete for the same URL.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'starter_llms_txt_register_rewrite', 5 );
add_filter( 'query_vars', 'starter_llms_txt_query_vars' );
add_action( 'template_redirect', 'starter_serve_llms_txt', 0 );
// After Yoast (99999), so the copied `*` rules are the final ones.
add_filter( 'robots_txt', 'starter_robots_txt_ai_allow', 100000, 2 );

/**
 * Path of the llms.txt template (filterable, e.g. to keep it in the theme).
 *
 * @return string
 */
function starter_llms_txt_template_path(): string {
	return (string) apply_filters( 'starter_llms_txt_template', STARTER_CORE_PATH . '/data/llms.txt.md' );
}

/**
 * Markdown list of indexable pages from pages-map.
 *
 * @return string
 */
function starter_llms_txt_pages_list(): string {
	$origin = untrailingslashit( home_url() );
	$lines  = array();

	foreach ( starter_core_pages() as $page ) {
		if ( ! empty( $page['noindex'] ) || in_array( $page['type'] ?? '', array( 'post', 'single', 'taxonomy' ), true ) ) {
			continue;
		}
		$lines[] = sprintf( '- [%s](%s%s)', (string) ( $page['title'] ?? '' ), $origin, (string) ( $page['url'] ?? '/' ) );
	}

	if ( ! $lines ) {
		$lines[] = sprintf( '- [%s](%s/)', (string) starter_get_company( 'name' ), $origin );
	}

	return implode( "\n", $lines );
}

/**
 * Rendered llms.txt body ('' when the template is missing).
 *
 * @return string
 */
function starter_get_llms_txt_body(): string {
	$path = starter_llms_txt_template_path();
	if ( ! is_readable( $path ) ) {
		return '';
	}

	$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local template file.
	if ( false === $raw ) {
		return '';
	}

	$address = starter_get_company( 'address.text' );
	if ( '' === $address ) {
		$address = implode( ', ', array_filter( array( starter_get_company( 'address.locality' ), starter_get_company( 'address.street' ) ) ) );
	}

	$vars = array(
		'{{site_name}}'        => (string) starter_get_company( 'name' ),
		'{{site_description}}' => (string) starter_get_company( 'description' ),
		'{{origin}}'           => untrailingslashit( home_url() ),
		'{{pages}}'            => starter_llms_txt_pages_list(),
		'{{phone}}'            => (string) starter_get_company( 'phone' ),
		'{{email}}'            => (string) starter_get_company( 'email' ),
		'{{address}}'          => (string) $address,
	);

	/**
	 * Filter llms.txt placeholder values.
	 *
	 * @param array<string, string> $vars Placeholder => value.
	 */
	$vars = (array) apply_filters( 'starter_llms_txt_vars', $vars );
	$body = strtr( $raw, $vars );

	// Drop list items whose value resolved to nothing ("- Телефон: ") and an empty "> " description.
	$body = str_replace( array( "\r\n", "\r" ), "\n", $body );
	$body = (string) preg_replace( '/^- [^:\n]+:[ \t]*$\n?/m', '', $body );
	$body = (string) preg_replace( '/^>[ \t]*$\n?/m', '', $body );
	$body = (string) preg_replace( "/\n{3,}/", "\n\n", $body );

	return rtrim( $body ) . "\n";
}

/**
 * Rewrite /llms.txt → index.php?starter_llms_txt=1.
 */
function starter_llms_txt_register_rewrite(): void {
	add_rewrite_rule( '^llms\.txt$', 'index.php?starter_llms_txt=1', 'top' );
}

/**
 * Register the query var.
 *
 * @param string[] $vars Public query vars.
 * @return string[]
 */
function starter_llms_txt_query_vars( array $vars ): array {
	$vars[] = 'starter_llms_txt';

	return $vars;
}

/**
 * Serve /llms.txt as text/plain.
 */
function starter_serve_llms_txt(): void {
	if ( ! get_query_var( 'starter_llms_txt' ) ) {
		return;
	}

	$body = starter_get_llms_txt_body();

	status_header( '' === $body ? 404 : 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	echo '' === $body ? "llms.txt template missing\n" : $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text curated file, not HTML.
	exit;
}

/**
 * AI crawlers that get an explicit Allow group in robots.txt.
 *
 * @return string[]
 */
function starter_ai_bots(): array {
	$bots = array(
		'GPTBot',
		'OAI-SearchBot',
		'ChatGPT-User',
		'ClaudeBot',
		'Claude-SearchBot',
		'Claude-User',
		'anthropic-ai',
		'Google-Extended',
		'PerplexityBot',
		'Perplexity-User',
		'Applebot-Extended',
		'YandexAdditional',
	);

	/**
	 * Filter the AI crawler allow-list for robots.txt (return [] to disable).
	 *
	 * @param string[] $bots User-agent names.
	 */
	return array_values( array_filter( array_map( 'strval', (array) apply_filters( 'starter_ai_bots', $bots ) ) ) );
}

/**
 * Rules (Allow/Disallow lines) of the `User-agent: *` group(s) in a robots.txt body.
 *
 * @param string $robots robots.txt body.
 * @return string[] Lines like "Disallow: /wp-admin/".
 */
function starter_robots_txt_star_rules( string $robots ): array {
	$rules    = array();
	$agents   = array();
	$in_rules = false;
	$lines    = preg_split( '/\r\n|\r|\n/', $robots );

	foreach ( is_array( $lines ) ? $lines : array() as $line ) {
		$line = trim( (string) preg_replace( '/#.*$/', '', $line ) );
		if ( ! preg_match( '/^([A-Za-z-]+)\s*:\s*(.*)$/', $line, $m ) ) {
			continue;
		}
		$field = strtolower( $m[1] );
		$value = trim( $m[2] );

		if ( 'user-agent' === $field ) {
			if ( $in_rules ) {
				$agents   = array();
				$in_rules = false;
			}
			$agents[] = $value;
		} elseif ( in_array( $field, array( 'allow', 'disallow' ), true ) ) {
			$in_rules = true;
			if ( in_array( '*', $agents, true ) && '' !== $value ) {
				$rules[] = ucfirst( $field ) . ': ' . $value;
			}
		}
	}

	return array_values( array_unique( $rules ) );
}

/**
 * Append one group for all AI crawlers: the site's `*` rules + "Allow: /".
 *
 * A crawler obeys only the most specific group naming it, so the group repeats the `*` rules
 * (a bare "Allow: /" would open /wp-admin/ etc. to AI bots). Skipped when blog_public = 0.
 *
 * @param string      $output    robots.txt body.
 * @param bool|string $is_public Whether the site is public (core passes a bool).
 * @return string
 */
function starter_robots_txt_ai_allow( $output, $is_public ): string {
	$output = (string) $output;
	if ( empty( $is_public ) ) {
		return $output;
	}

	$bots = starter_ai_bots();
	if ( ! $bots ) {
		return $output;
	}

	$lines = array( '# AI crawlers (starter-core)' );
	foreach ( $bots as $bot ) {
		$lines[] = 'User-agent: ' . $bot;
	}
	$lines   = array_merge( $lines, starter_robots_txt_star_rules( $output ) );
	$lines[] = 'Allow: /';

	return rtrim( $output ) . "\n\n" . implode( "\n", $lines ) . "\n";
}
