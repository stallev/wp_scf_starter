<?php
/**
 * Images: WebP sub-sizes and the starter_image() helper.
 *
 * - Generated sub-sizes (thumbnail, card, large, …) are written as WebP when the server editor
 *   supports it; originals stay untouched, so `full` URLs (og:image, lightbox source) do not change.
 *   Existing attachments need a one-off regeneration (K11, separate procedure with a backup).
 * - starter_image() is the only way templates print images: exactly one LCP image per page gets
 *   eager + fetchpriority=high, every other image is lazy + async. `loading` is always explicit,
 *   so WordPress never auto-adds fetchpriority=high to a below-the-fold image (K3).
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Write JPEG/PNG sub-sizes as WebP (only when GD/Imagick can encode WebP).
 *
 * WordPress asks this filter twice: with the uploaded file path (whether to convert the full-size
 * image itself) and without a filename (each generated sub-size). Only the second case is mapped,
 * so the original stays JPEG/PNG and `full` URLs (og:image, lightbox source) do not change (K11).
 *
 * @param array<string, string> $formats  Source mime => output mime.
 * @param string|null           $filename Image path; empty while sub-sizes are generated.
 * @return array<string, string>
 */
function starter_webp_output_format( $formats, $filename = null ): array {
	$formats = is_array( $formats ) ? $formats : array();

	if ( empty( $filename ) && wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
	}

	return $formats;
}
add_filter( 'image_editor_output_format', 'starter_webp_output_format', 10, 2 );

/**
 * WebP quality from project.config.json → images.webp_quality (default 80).
 *
 * @param int    $quality   Default quality.
 * @param string $mime_type Output mime type.
 * @return int
 */
function starter_webp_quality( $quality, $mime_type = '' ): int {
	if ( 'image/webp' !== $mime_type ) {
		return (int) $quality;
	}

	$configured = starter_core_config( 'images.webp_quality' );

	return is_numeric( $configured ) ? max( 1, min( 100, (int) $configured ) ) : 80;
}
add_filter( 'wp_editor_set_quality', 'starter_webp_quality', 10, 2 );

/**
 * Responsive <img> for an attachment.
 *
 * Args:
 * - priority (bool)  LCP image: loading=eager + fetchpriority=high. Only ONE per page, never inside .reveal.
 *                    Default false: loading=lazy.
 * - alt (string)     Alt text; default — the attachment alt ('' for decorative).
 * - class (string)   Extra class on <img>.
 * - sizes (string)   `sizes` attribute; default — derived by WordPress from the size width.
 * - fallback (bool)  Use the company default image when $attachment_id is 0 (default true).
 *
 * decoding=async is always set; width/height come from the chosen size (no CLS).
 *
 * @param int                  $attachment_id Attachment ID (0 = fallback image).
 * @param string               $size          Registered size: the card size (starter_card_size()), 'large', etc.
 * @param array<string, mixed> $args          See above.
 * @return string Image HTML, or an empty string when there is no image.
 */
function starter_image( int $attachment_id, string $size = 'large', array $args = array() ): string {
	$fallback = ! array_key_exists( 'fallback', $args ) || (bool) $args['fallback'];
	if ( $attachment_id <= 0 && $fallback ) {
		$attachment_id = starter_get_default_image_id();
	}
	if ( $attachment_id <= 0 || ! wp_attachment_is_image( $attachment_id ) ) {
		return '';
	}

	$priority = ! empty( $args['priority'] );
	$alt      = isset( $args['alt'] ) ? (string) $args['alt'] : (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

	$attr = array(
		'alt'           => $alt,
		'loading'       => $priority ? 'eager' : 'lazy',
		'decoding'      => 'async',
		'fetchpriority' => $priority ? 'high' : 'auto',
	);
	if ( ! empty( $args['class'] ) ) {
		$attr['class'] = (string) $args['class'];
	}
	if ( ! empty( $args['sizes'] ) ) {
		$attr['sizes'] = (string) $args['sizes'];
	}

	return wp_get_attachment_image( $attachment_id, $size, false, $attr );
}

/**
 * Drop fetchpriority="auto" from the final attributes (browser default). starter_image() passes it
 * only to stop core from auto-adding fetchpriority=high; the markup keeps just "high" on the LCP image.
 *
 * @param array<string, string> $attr Image attributes.
 * @return array<string, string>
 */
function starter_image_drop_auto_priority( $attr ): array {
	$attr = is_array( $attr ) ? $attr : array();
	if ( isset( $attr['fetchpriority'] ) && 'auto' === $attr['fetchpriority'] ) {
		unset( $attr['fetchpriority'] );
	}

	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'starter_image_drop_auto_priority', 99 );

/**
 * When WebP sub-sizes exist, keep only WebP candidates in srcset: hi-DPI screens would otherwise
 * pick the heavy JPEG/PNG original. `full` itself stays available (og:image, lightbox source).
 *
 * @param mixed $sources Srcset sources (width => { url, descriptor, value }).
 * @return mixed
 */
function starter_srcset_webp_only( $sources ) {
	if ( ! is_array( $sources ) ) {
		return $sources;
	}

	$webp = array_filter(
		$sources,
		static fn( $source ): bool => is_array( $source ) && str_ends_with( strtolower( (string) ( $source['url'] ?? '' ) ), '.webp' )
	);

	return $webp ? $webp : $sources;
}
add_filter( 'wp_calculate_image_srcset', 'starter_srcset_webp_only' );

/**
 * Echo starter_image().
 *
 * @param int                  $attachment_id Attachment ID.
 * @param string               $size          Image size.
 * @param array<string, mixed> $args          See starter_image().
 */
function starter_the_image( int $attachment_id, string $size = 'large', array $args = array() ): void {
	echo starter_image( $attachment_id, $size, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output.
}
