<?php
/**
 * Seed targets for the catalog module: product-families.json → starter_product_family,
 * products.json → starter_product. Registered via starter_seed_targets only while the module is
 * loaded, so a disabled module leaves the seeder untouched (no-op).
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add this module's seed targets.
 *
 * @param array<string, callable> $targets Existing targets.
 * @return array<string, callable>
 */
function starter_catalog_seed_targets( array $targets ): array {
	$targets['product_families'] = 'starter_seed_import_product_families';
	$targets['products']         = 'starter_seed_import_products';

	return $targets;
}
add_filter( 'starter_seed_targets', 'starter_catalog_seed_targets' );

/**
 * Import modules/catalog/product-families.json. Item: slug, title, description.
 *
 * @return array{created: int, updated: int, unchanged: int, skipped: int, errors: string[], warnings: string[]}
 */
function starter_seed_import_product_families(): array {
	$stats = starter_seed_stats();
	$data  = starter_seed_load_for( 'modules/catalog/product-families.json', $stats );
	if ( null === $data ) {
		return $stats;
	}

	foreach ( starter_seed_items( $data ) as $item ) {
		$slug  = sanitize_title( (string) ( $item['slug'] ?? '' ) );
		$title = trim( (string) ( $item['title'] ?? '' ) );

		if ( '' === $slug || '' === $title ) {
			$stats['errors'][] = 'product family: missing slug or title';
			continue;
		}

		// Compare against the sanitized form: wp_insert_term()/wp_update_term() run the description
		// through the same filter (e.g. a stray ">" becomes "&gt;"), so a raw-vs-stored diff would
		// report "updated" forever.
		$description = (string) sanitize_term_field( 'description', (string) ( $item['description'] ?? '' ), 0, 'starter_product_family', 'db' );
		$existing    = get_term_by( 'slug', $slug, 'starter_product_family' );

		if ( $existing instanceof WP_Term ) {
			if ( $existing->name === $title && $existing->description === $description ) {
				++$stats['unchanged'];
				continue;
			}
			if ( starter_seed_is_dry_run() ) {
				++$stats['updated'];
				continue;
			}
			$result = wp_update_term(
				$existing->term_id,
				'starter_product_family',
				array(
					'name'        => $title,
					'description' => $description,
				)
			);
			if ( is_wp_error( $result ) ) {
				$stats['errors'][] = sprintf( 'product family %s: %s', $slug, $result->get_error_message() );
				continue;
			}
			++$stats['updated'];
			continue;
		}

		if ( starter_seed_is_dry_run() ) {
			++$stats['created'];
			continue;
		}

		$result = wp_insert_term(
			$title,
			'starter_product_family',
			array(
				'slug'        => $slug,
				'description' => $description,
			)
		);
		if ( is_wp_error( $result ) ) {
			$stats['errors'][] = sprintf( 'product family %s: %s', $slug, $result->get_error_message() );
			continue;
		}
		++$stats['created'];
	}

	return $stats;
}

/**
 * Import modules/catalog/products.json. Item: slug, title, content, excerpt, family (term slug),
 * price, unit, sku, characteristics ([{ label, value }]), image, order.
 *
 * @return array{created: int, updated: int, unchanged: int, skipped: int, errors: string[], warnings: string[]}
 */
function starter_seed_import_products(): array {
	$stats = starter_seed_stats();
	$data  = starter_seed_load_for( 'modules/catalog/products.json', $stats );
	if ( null === $data ) {
		return $stats;
	}

	foreach ( starter_seed_items( $data ) as $item ) {
		$post_id = starter_seed_count(
			starter_seed_upsert_post(
				'starter_product',
				array(
					'slug'       => (string) ( $item['slug'] ?? '' ),
					'title'      => (string) ( $item['title'] ?? '' ),
					'content'    => (string) ( $item['content'] ?? '' ),
					'excerpt'    => (string) ( $item['excerpt'] ?? '' ),
					'menu_order' => (int) ( $item['order'] ?? 0 ),
				)
			),
			$stats
		);
		if ( $post_id <= 0 ) {
			continue;
		}

		starter_seed_update_field( 'starter_product_price', is_numeric( $item['price'] ?? null ) ? (float) $item['price'] : 0, $post_id );
		starter_seed_update_field( 'starter_product_unit', (string) ( $item['unit'] ?? '' ), $post_id );
		starter_seed_update_field( 'starter_product_sku', (string) ( $item['sku'] ?? '' ), $post_id );

		$characteristics = array();
		foreach ( (array) ( $item['characteristics'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$characteristics[] = array(
				'label' => (string) ( $row['label'] ?? '' ),
				'value' => (string) ( $row['value'] ?? '' ),
			);
		}
		starter_seed_update_field( 'starter_product_characteristics', $characteristics, $post_id );

		$family = sanitize_title( (string) ( $item['family'] ?? '' ) );
		if ( '' !== $family && ! starter_seed_is_dry_run() ) {
			wp_set_object_terms( $post_id, array( $family ), 'starter_product_family' );
		}

		if ( ! empty( $item['image'] ) ) {
			starter_seed_set_featured_image( $post_id, (string) $item['image'], (string) ( $item['title'] ?? '' ), $stats );
		}
	}

	return $stats;
}
