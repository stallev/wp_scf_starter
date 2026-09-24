<?php
/**
 * Seed building blocks: stats, idempotent upsert by slug, field writes, attachments, company mapping.
 *
 * Every write goes through a helper here, so dry-run is enforced in one place.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Empty per-target statistics.
 *
 * @return array{created: int, updated: int, unchanged: int, skipped: int, errors: string[], warnings: string[]}
 */
function starter_seed_stats(): array {
	return array(
		'created'   => 0,
		'updated'   => 0,
		'unchanged' => 0,
		'skipped'   => 0,
		'errors'    => array(),
		'warnings'  => array(),
	);
}

/**
 * Load a seed file for a target; a missing file is a warning (target skipped), other problems are errors.
 *
 * @param string                                                                                                $filename File name.
 * @param array{created: int, updated: int, unchanged: int, skipped: int, errors: string[], warnings: string[]} $stats    Stats (by reference).
 * @return array<string, mixed>|null Null when the target cannot run.
 */
function starter_seed_load_for( string $filename, array &$stats ): ?array {
	$data = starter_seed_load_json( $filename );

	if ( is_wp_error( $data ) ) {
		if ( 'starter_seed_missing' === $data->get_error_code() ) {
			$stats['warnings'][] = $data->get_error_message();
		} else {
			$stats['errors'][] = $data->get_error_message();
		}
		return null;
	}

	return $data;
}

/**
 * Write an SCF field by name (no-op in dry-run).
 *
 * @param string     $name    Field name.
 * @param mixed      $value   Value.
 * @param int|string $post_id Post ID or 'option'.
 */
function starter_seed_update_field( string $name, $value, $post_id ): void {
	if ( starter_seed_is_dry_run() ) {
		return;
	}

	starter_update_field( $name, $value, $post_id );
}

/**
 * Find a post by slug (pages: by full path).
 *
 * @param string $slug      Slug, or path for hierarchical types (e.g. services/design).
 * @param string $post_type Post type.
 * @return int 0 when not found.
 */
function starter_seed_find_post_id( string $slug, string $post_type ): int {
	if ( is_post_type_hierarchical( $post_type ) ) {
		$post = get_page_by_path( $slug, OBJECT, $post_type );
		return $post instanceof WP_Post ? (int) $post->ID : 0;
	}

	$ids = get_posts(
		array(
			'name'             => $slug,
			'post_type'        => $post_type,
			'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		)
	);

	return $ids ? (int) $ids[0] : 0;
}

/**
 * Insert or update a post by slug. Only keys present in $args are written on update, so editor
 * changes in fields the seed does not manage survive a re-run.
 *
 * @param string               $post_type Post type.
 * @param array<string, mixed> $args      slug (required), title (required), path, content, excerpt, status, parent, menu_order, date (Y-m-d).
 * @return array{id: int, action: 'created'|'updated'|'unchanged'|'error', error?: string}
 */
function starter_seed_upsert_post( string $post_type, array $args ): array {
	$slug  = sanitize_title( (string) ( $args['slug'] ?? '' ) );
	$title = trim( (string) ( $args['title'] ?? '' ) );

	if ( '' === $slug || '' === $title ) {
		return array(
			'id'     => 0,
			'action' => 'error',
			'error'  => sprintf( '%s: missing slug or title', $post_type ),
		);
	}

	$lookup   = isset( $args['path'] ) ? (string) $args['path'] : $slug;
	$existing = starter_seed_find_post_id( $lookup, $post_type );

	$payload = array(
		'post_type'   => $post_type,
		'post_name'   => $slug,
		'post_title'  => $title,
		'post_status' => (string) ( $args['status'] ?? 'publish' ),
	);
	$map     = array(
		'content'    => 'post_content',
		'excerpt'    => 'post_excerpt',
		'parent'     => 'post_parent',
		'menu_order' => 'menu_order',
	);
	foreach ( $map as $key => $field ) {
		if ( array_key_exists( $key, $args ) ) {
			$payload[ $field ] = in_array( $key, array( 'parent', 'menu_order' ), true ) ? (int) $args[ $key ] : (string) $args[ $key ];
		}
	}
	if ( ! empty( $args['date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $args['date'] ) ) {
		$payload['post_date']     = $args['date'] . ' 10:00:00';
		$payload['post_date_gmt'] = get_gmt_from_date( $payload['post_date'] );
	}

	if ( $existing > 0 ) {
		$current = get_post( $existing, ARRAY_A );
		$changed = false;
		foreach ( $payload as $field => $value ) {
			if ( 'post_date_gmt' !== $field && is_array( $current ) && (string) ( $current[ $field ] ?? '' ) !== (string) $value ) {
				$changed = true;
				break;
			}
		}

		if ( ! $changed ) {
			return array(
				'id'     => $existing,
				'action' => 'unchanged',
			);
		}

		if ( starter_seed_is_dry_run() ) {
			return array(
				'id'     => $existing,
				'action' => 'updated',
			);
		}

		$payload['ID'] = $existing;
		if ( isset( $payload['post_date'] ) ) {
			$payload['edit_date'] = true;
		}
		$result = wp_update_post( wp_slash( $payload ), true );
	} else {
		if ( starter_seed_is_dry_run() ) {
			return array(
				'id'     => 0,
				'action' => 'created',
			);
		}
		$result = wp_insert_post( wp_slash( $payload ), true );
	}

	if ( is_wp_error( $result ) ) {
		return array(
			'id'     => $existing,
			'action' => 'error',
			'error'  => sprintf( '%s %s: %s', $post_type, $slug, $result->get_error_message() ),
		);
	}

	return array(
		'id'     => (int) $result,
		'action' => $existing > 0 ? 'updated' : 'created',
	);
}

/**
 * Count an upsert result into stats.
 *
 * @param array{id: int, action: 'created'|'updated'|'unchanged'|'error', error?: string}                       $result Upsert result.
 * @param array{created: int, updated: int, unchanged: int, skipped: int, errors: string[], warnings: string[]} $stats  Stats (by reference).
 * @return int Post ID to continue with (0 on error or dry-run create).
 */
function starter_seed_count( array $result, array &$stats ): int {
	if ( 'error' === $result['action'] ) {
		$stats['errors'][] = $result['error'] ?? 'upsert failed';
		return 0;
	}

	++$stats[ $result['action'] ];

	return $result['id'];
}

/**
 * Resolve a seed image: absolute URL, or a path relative to the seed directory.
 *
 * @param string $image Seed value.
 * @return string|null URL or absolute path; null when unusable.
 */
function starter_seed_resolve_image( string $image ): ?string {
	$image = trim( $image );
	if ( '' === $image ) {
		return null;
	}
	if ( preg_match( '#^https?://#i', $image ) ) {
		return $image;
	}

	// Local files must stay inside the seed directory (no ../ escapes to wp-config.php etc.).
	$root = realpath( starter_seed_path() );
	$path = realpath( starter_seed_file( str_replace( '\\', '/', $image ) ) );
	if ( false === $root || false === $path || ! str_starts_with( $path, $root . DIRECTORY_SEPARATOR ) || ! is_file( $path ) || ! is_readable( $path ) ) {
		return null;
	}

	return $path;
}

/**
 * Attachment for a seed image; reuses the one imported earlier from the same source (idempotent).
 *
 * @param string $image   URL or path relative to the seed directory.
 * @param int    $post_id Parent post (0 = none).
 * @param string $title   Attachment title.
 * @return int|WP_Error Attachment ID (0 in dry-run when it would be created).
 */
function starter_seed_attachment( string $image, int $post_id = 0, string $title = '' ) {
	$source = starter_seed_resolve_image( $image );
	if ( null === $source ) {
		return new WP_Error( 'starter_seed_image_missing', sprintf( 'Image not found: %s', $image ) );
	}

	$seed_root  = (string) realpath( starter_seed_path() );
	$source_key = '' !== $seed_root && str_starts_with( $source, $seed_root . DIRECTORY_SEPARATOR ) ? substr( $source, strlen( $seed_root ) + 1 ) : $source;
	$found      = get_posts(
		array(
			'post_type'        => 'attachment',
			'post_status'      => 'inherit',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'meta_key'         => '_starter_seed_source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- seed-time lookup only.
			'meta_value'       => $source_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- seed-time lookup only.
			'suppress_filters' => true,
		)
	);
	if ( $found ) {
		return (int) $found[0];
	}
	if ( starter_seed_is_dry_run() ) {
		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	if ( preg_match( '#^https?://#i', $source ) ) {
		$tmp = download_url( $source, 30 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		$name = sanitize_file_name( wp_basename( (string) wp_parse_url( $source, PHP_URL_PATH ) ) );
		$name = '' !== pathinfo( $name, PATHINFO_EXTENSION ) ? $name : sanitize_title( '' !== $title ? $title : 'image' ) . '.jpg';
	} else {
		$name = wp_basename( $source );
		$tmp  = wp_tempnam( $name );
		if ( ! copy( $source, $tmp ) ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'starter_seed_image_copy', sprintf( 'Could not copy %s', $image ) );
		}
	}

	$attachment_id = media_handle_sideload(
		array(
			'name'     => $name,
			'tmp_name' => $tmp,
		),
		$post_id,
		'' !== $title ? $title : null
	);
	if ( is_wp_error( $attachment_id ) ) {
		wp_delete_file( $tmp );
		return $attachment_id;
	}

	update_post_meta( (int) $attachment_id, '_starter_seed_source', $source_key );

	return (int) $attachment_id;
}

/**
 * Set the featured image from a seed image (warning on failure, the post stays imported).
 *
 * @param int                                                                                                   $post_id Post ID.
 * @param string                                                                                                $image   Seed image value.
 * @param string                                                                                                $title   Attachment title.
 * @param array{created: int, updated: int, unchanged: int, skipped: int, errors: string[], warnings: string[]} $stats   Stats (by reference).
 */
function starter_seed_set_featured_image( int $post_id, string $image, string $title, array &$stats ): void {
	if ( '' === trim( $image ) || $post_id <= 0 ) {
		return;
	}

	$attachment_id = starter_seed_attachment( $image, $post_id, $title );
	if ( is_wp_error( $attachment_id ) ) {
		$stats['warnings'][] = sprintf( 'post %d image: %s', $post_id, $attachment_id->get_error_message() );
		return;
	}

	if ( $attachment_id > 0 && ! starter_seed_is_dry_run() && (int) get_post_thumbnail_id( $post_id ) !== $attachment_id ) {
		set_post_thumbnail( $post_id, $attachment_id );
	}
}

/**
 * Map company.json to option field values (only keys present in the file).
 *
 * @param array<string, mixed> $data company.json.
 * @return array<string, mixed> Field name => value.
 */
function starter_seed_map_company( array $data ): array {
	$fields = array();

	$scalars = array(
		'name'          => 'starter_company_name',
		'legal_name'    => 'starter_company_legal_name',
		'description'   => 'starter_company_description',
		'business_type' => 'starter_company_business_type',
		'email'         => 'starter_company_email',
		'hours_text'    => 'starter_company_hours_text',
		'ga4_id'        => 'starter_company_ga4_id',
		'price_range'   => 'starter_company_price_range',
	);
	foreach ( $scalars as $key => $field ) {
		if ( array_key_exists( $key, $data ) ) {
			$fields[ $field ] = (string) $data[ $key ];
		}
	}

	if ( isset( $data['address'] ) && is_array( $data['address'] ) ) {
		foreach ( array( 'street', 'locality', 'region', 'postal_code', 'country' ) as $key ) {
			if ( array_key_exists( $key, $data['address'] ) ) {
				$fields[ 'starter_company_' . $key ] = (string) $data['address'][ $key ];
			}
		}
		if ( array_key_exists( 'text', $data['address'] ) ) {
			$fields['starter_company_address_text'] = (string) $data['address']['text'];
		}
	}

	if ( isset( $data['geo'] ) && is_array( $data['geo'] ) ) {
		foreach ( array( 'lat', 'lng' ) as $axis ) {
			if ( array_key_exists( $axis, $data['geo'] ) ) {
				$fields[ 'starter_company_geo_' . $axis ] = is_numeric( $data['geo'][ $axis ] ) ? (float) $data['geo'][ $axis ] : '';
			}
		}
	}

	$rows = array(
		'phones'        => array( 'starter_company_phones', array( 'number', 'label' ) ),
		'socials'       => array( 'starter_company_socials', array( 'network', 'url', 'label' ) ),
		'opening_hours' => array( 'starter_company_opening_hours', array( 'days', 'opens', 'closes' ) ),
	);
	foreach ( $rows as $key => $spec ) {
		if ( ! isset( $data[ $key ] ) || ! is_array( $data[ $key ] ) ) {
			continue;
		}
		$fields[ $spec[0] ] = array();
		foreach ( $data[ $key ] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean = array();
			foreach ( $spec[1] as $sub ) {
				$clean[ $sub ] = 'days' === $sub ? array_values( (array) ( $row[ $sub ] ?? array() ) ) : (string) ( $row[ $sub ] ?? '' );
			}
			$fields[ $spec[0] ][] = $clean;
		}
	}

	if ( array_key_exists( 'area_served', $data ) ) {
		$fields['starter_company_area_served'] = implode( "\n", starter_lines( $data['area_served'] ) );
	}

	return $fields;
}
