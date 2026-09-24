<?php
/**
 * Seed entity: service cards (seed/service-cards.json → SCF fields on existing pages).
 *
 * Pages come from pages-map; a card whose page does not exist yet is skipped with a warning.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Import service-cards.json. Item: page (path), enabled, title, text, price, price_note, badge, order, image.
 *
 * @return array{created: int, updated: int, unchanged: int, skipped: int, errors: string[], warnings: string[]}
 */
function starter_seed_import_service_cards(): array {
	$stats = starter_seed_stats();
	$data  = starter_seed_load_for( 'service-cards.json', $stats );
	if ( null === $data ) {
		return $stats;
	}

	foreach ( starter_seed_items( $data ) as $item ) {
		$path    = trim( (string) ( $item['page'] ?? '' ), '/' );
		$page_id = '' !== $path ? starter_seed_find_post_id( $path, 'page' ) : 0;
		if ( $page_id <= 0 ) {
			++$stats['skipped'];
			$stats['warnings'][] = sprintf( 'service card: page /%s/ not found (add it to pages-map and seed pages first)', $path );
			continue;
		}

		$values = array(
			'starter_service_card_enabled'    => ! array_key_exists( 'enabled', $item ) || ! empty( $item['enabled'] ) ? 1 : 0,
			'starter_service_card_title'      => (string) ( $item['title'] ?? '' ),
			'starter_service_card_text'       => (string) ( $item['text'] ?? '' ),
			'starter_service_card_price'      => (string) ( $item['price'] ?? '' ),
			'starter_service_card_price_note' => (string) ( $item['price_note'] ?? '' ),
			'starter_service_card_badge'      => (string) ( $item['badge'] ?? '' ),
			'starter_service_card_order'      => (int) ( $item['order'] ?? 10 ),
		);

		if ( ! empty( $item['image'] ) ) {
			$attachment_id = starter_seed_attachment( (string) $item['image'], $page_id, (string) ( $item['title'] ?? '' ) );
			if ( is_wp_error( $attachment_id ) ) {
				$stats['warnings'][] = sprintf( 'service card /%s/ image: %s', $path, $attachment_id->get_error_message() );
			} elseif ( $attachment_id > 0 ) {
				$values['starter_service_card_image'] = $attachment_id;
			}
		}

		$changed = false;
		foreach ( $values as $name => $value ) {
			if ( (string) get_post_meta( $page_id, $name, true ) === (string) $value ) {
				continue;
			}
			$changed = true;
			starter_seed_update_field( $name, $value, $page_id );
		}

		++$stats[ $changed ? 'updated' : 'unchanged' ];
	}

	return $stats;
}
