<?php
/**
 * Heading anchors (h-xxxxxxxxxx) for H2/H3 in posts + the post table of contents.
 *
 * Anchors are stamped on save (server) and in the block editor (assets/js/editor-heading-anchors.js),
 * so TOC links stay stable when the heading text changes. Manually set anchors are kept.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Random heading anchor h- + 10 [a-z0-9], unique within $used.
 *
 * @param array<string, bool> $used Existing anchors (by reference).
 * @return string
 */
function starter_generate_heading_anchor( array &$used ): string {
	$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';

	do {
		$anchor = 'h-';
		for ( $i = 0; $i < 10; $i++ ) {
			$anchor .= $chars[ wp_rand( 0, strlen( $chars ) - 1 ) ];
		}
	} while ( isset( $used[ $anchor ] ) );

	$used[ $anchor ] = true;

	return $anchor;
}

/**
 * Anchor of a core/heading block: attrs.anchor or id="" in its HTML.
 *
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function starter_heading_block_anchor( array $block ): string {
	$anchor = isset( $block['attrs']['anchor'] ) ? trim( (string) $block['attrs']['anchor'] ) : '';
	if ( '' === $anchor && ! empty( $block['innerHTML'] ) && preg_match( '/<h[1-6]\b[^>]*>/i', (string) $block['innerHTML'], $tag ) ) {
		$anchor = starter_heading_tag_id( $tag[0] );
	}

	return $anchor;
}

/**
 * Value of the id attribute of an opening heading tag (data-id="" and similar are ignored).
 *
 * @param string $tag Opening tag, e.g. <h2 class="x" id="y">.
 * @return string
 */
function starter_heading_tag_id( string $tag ): string {
	return preg_match( '/(?<![\w-])id\s*=\s*["\']([^"\']+)["\']/i', $tag, $m ) ? trim( $m[1] ) : '';
}

/**
 * Heading level of a core/heading block (default 2).
 *
 * @param array<string, mixed> $block Parsed block.
 * @return int
 */
function starter_heading_block_level( array $block ): int {
	return isset( $block['attrs']['level'] ) ? (int) $block['attrs']['level'] : 2;
}

/**
 * Collect anchors already used in a block tree.
 *
 * @param array<int, mixed>   $blocks Parsed blocks.
 * @param array<string, bool> $used   Anchors (by reference).
 */
function starter_collect_heading_anchors( array $blocks, array &$used ): void {
	foreach ( $blocks as $block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}
		if ( 'core/heading' === ( $block['blockName'] ?? '' ) ) {
			$anchor = starter_heading_block_anchor( $block );
			if ( '' !== $anchor ) {
				$used[ $anchor ] = true;
			}
		}
		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			starter_collect_heading_anchors( $block['innerBlocks'], $used );
		}
	}
}

/**
 * Write an anchor into a heading block (attrs + id="" in the markup).
 *
 * @param array<string, mixed> $block  Heading block.
 * @param string               $anchor Anchor.
 * @return array<string, mixed>
 */
function starter_set_heading_block_anchor( array $block, string $anchor ): array {
	$block['attrs']           = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
	$block['attrs']['anchor'] = $anchor;

	$html = (string) ( $block['innerHTML'] ?? '' );
	if ( ! preg_match( '/<h[1-6]\b[^>]*>/i', $html, $m ) ) {
		return $block;
	}

	$tag     = $m[0];
	$new_tag = preg_match( '/(?<![\w-])id\s*=/i', $tag )
		? (string) preg_replace( '/(?<![\w-])id\s*=\s*(["\'])[^"\']*\1/i', 'id="' . esc_attr( $anchor ) . '"', $tag, 1 )
		: (string) preg_replace( '/^<h([1-6])/i', '<h$1 id="' . esc_attr( $anchor ) . '"', $tag, 1 );

	$block['innerHTML'] = str_replace( $tag, $new_tag, $html );
	if ( ! empty( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
		foreach ( $block['innerContent'] as $i => $piece ) {
			if ( is_string( $piece ) ) {
				$block['innerContent'][ $i ] = str_replace( $tag, $new_tag, $piece );
			}
		}
	}

	return $block;
}

/**
 * Stamp missing H2/H3 anchors in a block tree.
 *
 * @param array<int, mixed>   $blocks  Parsed blocks.
 * @param array<string, bool> $used    Used anchors (by reference).
 * @param bool                $changed Set to true when something changed.
 * @return array<int, mixed>
 */
function starter_stamp_blocks_heading_anchors( array $blocks, array &$used, bool &$changed ): array {
	foreach ( $blocks as $i => $block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}

		if ( 'core/heading' === ( $block['blockName'] ?? '' ) && in_array( starter_heading_block_level( $block ), array( 2, 3 ), true ) ) {
			$anchor = starter_heading_block_anchor( $block );
			if ( '' === $anchor ) {
				$block   = starter_set_heading_block_anchor( $block, starter_generate_heading_anchor( $used ) );
				$changed = true;
			} elseif ( empty( $block['attrs']['anchor'] ) ) {
				$block['attrs']['anchor'] = $anchor;
				$used[ $anchor ]          = true;
				$changed                  = true;
			}
		}

		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$block['innerBlocks'] = starter_stamp_blocks_heading_anchors( $block['innerBlocks'], $used, $changed );
		}

		$blocks[ $i ] = $block;
	}

	return $blocks;
}

/**
 * Stamp missing H2/H3 anchors in block content.
 *
 * @param string $content Unslashed post content.
 * @return string
 */
function starter_stamp_heading_anchors( string $content ): string {
	if ( '' === trim( $content ) || ! has_blocks( $content ) ) {
		return $content;
	}

	$blocks = parse_blocks( $content );
	$used   = array();
	starter_collect_heading_anchors( $blocks, $used );

	$changed = false;
	$blocks  = starter_stamp_blocks_heading_anchors( $blocks, $used, $changed );

	return $changed ? serialize_blocks( $blocks ) : $content;
}

/**
 * Persist heading anchors when a post is saved.
 *
 * @param array<string, mixed> $data    Slashed, sanitized post data.
 * @param array<string, mixed> $postarr Raw post array.
 * @return array<string, mixed>
 */
function starter_stamp_post_heading_anchors( $data, $postarr ): array {
	$data = (array) $data;

	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		|| 'post' !== ( $data['post_type'] ?? '' )
		|| 'auto-draft' === ( $data['post_status'] ?? '' )
		|| ( ! empty( $postarr['ID'] ) && wp_is_post_revision( (int) $postarr['ID'] ) )
		|| empty( $data['post_content'] ) || ! is_string( $data['post_content'] ) ) {
		return $data;
	}

	// $data is slashed: parse the real markup, then slash it back.
	$data['post_content'] = wp_slash( starter_stamp_heading_anchors( wp_unslash( $data['post_content'] ) ) );

	return $data;
}
add_filter( 'wp_insert_post_data', 'starter_stamp_post_heading_anchors', 10, 2 );

/**
 * TOC items from a block tree.
 *
 * @param array<int, mixed> $blocks Parsed blocks.
 * @return array<int, array{id: string, text: string, level: int}>
 */
function starter_toc_from_blocks( array $blocks ): array {
	$items = array();

	foreach ( $blocks as $block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}
		if ( 'core/heading' === ( $block['blockName'] ?? '' ) ) {
			$level  = starter_heading_block_level( $block );
			$anchor = starter_heading_block_anchor( $block );
			$text   = trim( wp_strip_all_tags( (string) ( $block['innerHTML'] ?? '' ) ) );
			if ( in_array( $level, array( 2, 3 ), true ) && '' !== $anchor && '' !== $text ) {
				$items[] = array(
					'id'    => $anchor,
					'text'  => $text,
					'level' => $level,
				);
			}
		}
		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$items = array_merge( $items, starter_toc_from_blocks( $block['innerBlocks'] ) );
		}
	}

	return $items;
}

/**
 * TOC items from HTML (classic content): H2/H3 that have an id.
 *
 * @param string $html Content HTML.
 * @return array<int, array{id: string, text: string, level: int}>
 */
function starter_toc_from_html( string $html ): array {
	$items = array();

	if ( preg_match_all( '/(<h([23])\b[^>]*>)(.*?)<\/h\2>/is', $html, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $m ) {
			$id   = starter_heading_tag_id( $m[1] );
			$text = trim( wp_strip_all_tags( $m[3] ) );
			if ( '' !== $id && '' !== $text ) {
				$items[] = array(
					'id'    => $id,
					'text'  => $text,
					'level' => (int) $m[2],
				);
			}
		}
	}

	return $items;
}

/**
 * TOC items of a post.
 *
 * @param int $post_id Post ID.
 * @return array<int, array{id: string, text: string, level: int}>
 */
function starter_get_post_toc( int $post_id ): array {
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || 'post' !== $post->post_type || '' === trim( $post->post_content ) ) {
		return array();
	}

	$items = has_blocks( $post->post_content ) ? starter_toc_from_blocks( parse_blocks( $post->post_content ) ) : array();

	return $items ? $items : starter_toc_from_html( $post->post_content );
}

/**
 * Whether the TOC is shown: at least 2 anchored H2/H3 and a long enough article.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function starter_should_show_post_toc( int $post_id ): bool {
	/**
	 * Filter the minimum article length (words) for the TOC. Default 600.
	 *
	 * @param int $words   Minimum words.
	 * @param int $post_id Post ID.
	 */
	$min_words = (int) apply_filters( 'starter_post_toc_min_words', 600, $post_id );

	return count( starter_get_post_toc( $post_id ) ) >= 2
		&& starter_count_words( (string) get_post_field( 'post_content', $post_id ) ) >= $min_words;
}

/**
 * Render the TOC aside (template-parts/post-toc) or nothing.
 *
 * @param int $post_id Post ID (0 = queried object).
 */
function starter_render_post_toc( int $post_id = 0 ): void {
	$post_id = $post_id > 0 ? $post_id : (int) get_queried_object_id();
	if ( $post_id <= 0 || ! starter_should_show_post_toc( $post_id ) ) {
		return;
	}

	get_template_part( 'template-parts/post-toc', null, array( 'items' => starter_get_post_toc( $post_id ) ) );
}

/**
 * Block editor script: stamp h-xxxxxxxxxx anchors on H2/H3 without one.
 */
function starter_enqueue_editor_heading_anchors(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'post' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_script(
		'starter-editor-heading-anchors',
		starter_asset_url( 'assets/js/editor-heading-anchors.js' ),
		array( 'wp-data', 'wp-block-editor' ),
		starter_asset_version( 'assets/js/editor-heading-anchors.js' ),
		starter_script_args()
	);
}
add_action( 'enqueue_block_editor_assets', 'starter_enqueue_editor_heading_anchors' );
