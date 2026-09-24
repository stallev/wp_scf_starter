<?php
/**
 * Seed entity: navigation menus (seed/menus.json → nav menus + theme locations).
 *
 * Item targets: `page` (page path), `post` (post slug) or `url` (absolute, or relative to home).
 * A menu is rebuilt only when its resolved items change (hash in term meta), so re-runs are no-ops.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Import menus.json. Shape: { "menus": [ { "location", "name", "items": [ { "title", "page"|"post"|"url", "children": [] } ] } ] }.
 *
 * @return array{created: int, updated: int, unchanged: int, skipped: int, errors: string[], warnings: string[]}
 */
function starter_seed_import_menus(): array {
	$stats = starter_seed_stats();
	$data  = starter_seed_load_for( 'menus.json', $stats );
	if ( null === $data ) {
		return $stats;
	}

	$menus      = isset( $data['menus'] ) && is_array( $data['menus'] ) ? $data['menus'] : array();
	$locations  = get_theme_mod( 'nav_menu_locations', array() );
	$locations  = is_array( $locations ) ? $locations : array();
	$original   = $locations;
	$registered = get_registered_nav_menus();

	foreach ( $menus as $menu ) {
		$name = is_array( $menu ) ? trim( (string) ( $menu['name'] ?? '' ) ) : '';
		if ( '' === $name ) {
			++$stats['skipped'];
			continue;
		}

		$items   = starter_seed_resolve_menu_items( (array) ( $menu['items'] ?? array() ), $stats );
		$hash    = md5( (string) wp_json_encode( $items ) );
		$object  = wp_get_nav_menu_object( $name );
		$menu_id = $object instanceof WP_Term ? (int) $object->term_id : 0;

		if ( $menu_id > 0 && get_term_meta( $menu_id, '_starter_seed_hash', true ) === $hash ) {
			++$stats['unchanged'];
		} elseif ( starter_seed_is_dry_run() ) {
			++$stats[ $menu_id > 0 ? 'updated' : 'created' ];
		} else {
			$is_new = 0 === $menu_id;
			if ( $is_new ) {
				$created = wp_create_nav_menu( $name );
				if ( is_wp_error( $created ) ) {
					$stats['errors'][] = sprintf( 'menu %s: %s', $name, $created->get_error_message() );
					continue;
				}
				$menu_id = (int) $created;
			}

			starter_seed_clear_menu( $menu_id );
			starter_seed_add_menu_items( $menu_id, $items, 0 );
			update_term_meta( $menu_id, '_starter_seed_hash', $hash );
			++$stats[ $is_new ? 'created' : 'updated' ];
		}

		$location = sanitize_key( (string) ( $menu['location'] ?? '' ) );
		if ( '' !== $location && $menu_id > 0 ) {
			if ( ! isset( $registered[ $location ] ) ) {
				$stats['warnings'][] = sprintf( 'menu %s: location "%s" is not registered by the active theme (assigned anyway)', $name, $location );
			}
			$locations[ $location ] = $menu_id;
		}
	}

	if ( ! starter_seed_is_dry_run() && $original !== $locations ) {
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	return $stats;
}

/**
 * Resolve seed menu items into wp_update_nav_menu_item() args (recursive).
 *
 * @param array<int, mixed>                                                                                     $items Seed items.
 * @param array{created: int, updated: int, unchanged: int, skipped: int, errors: string[], warnings: string[]} $stats Stats (by reference).
 * @return array<int, array{args: array<string, mixed>, children: array<int, mixed>}>
 */
function starter_seed_resolve_menu_items( array $items, array &$stats ): array {
	$result = array();

	foreach ( $items as $item ) {
		if ( ! is_array( $item ) || '' === trim( (string) ( $item['title'] ?? '' ) ) ) {
			continue;
		}

		$args = array(
			'menu-item-title'  => (string) $item['title'],
			'menu-item-status' => 'publish',
		);

		$kind = '';
		$path = '';
		$id   = 0;
		if ( ! empty( $item['page'] ) ) {
			$kind = 'page';
			$path = '/' . trim( (string) $item['page'], '/' ) . '/';
			$id   = starter_seed_find_post_id( trim( (string) $item['page'], '/' ), 'page' );
		} elseif ( ! empty( $item['post'] ) ) {
			$kind = 'post';
			$path = '/' . sanitize_title( (string) $item['post'] ) . '/';
			$id   = starter_seed_find_post_id( (string) $item['post'], 'post' );
		}

		if ( $id > 0 ) {
			$args['menu-item-type']      = 'post_type';
			$args['menu-item-object']    = $kind;
			$args['menu-item-object-id'] = $id;
		} else {
			if ( '' !== $kind ) {
				$stats['warnings'][] = sprintf( 'menu item "%s": %s %s not found, using a custom link', (string) $item['title'], $kind, $path );
			}
			$url                    = '' !== $kind ? $path : (string) ( $item['url'] ?? '/' );
			$args['menu-item-type'] = 'custom';
			$args['menu-item-url']  = preg_match( '#^[a-z][a-z0-9+.-]*:#i', $url ) ? $url : home_url( '/' . ltrim( $url, '/' ) );
		}

		$result[] = array(
			'args'     => $args,
			'children' => starter_seed_resolve_menu_items( (array) ( $item['children'] ?? array() ), $stats ),
		);
	}

	return $result;
}

/**
 * Delete all items of a menu.
 *
 * @param int $menu_id Menu term ID.
 */
function starter_seed_clear_menu( int $menu_id ): void {
	$items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) );

	foreach ( is_array( $items ) ? $items : array() as $item ) {
		wp_delete_post( (int) $item->ID, true );
	}
}

/**
 * Add resolved items to a menu (recursive).
 *
 * @param int                                                                        $menu_id   Menu term ID.
 * @param array<int, array{args: array<string, mixed>, children: array<int, mixed>}> $items     Resolved items.
 * @param int                                                                        $parent_id Parent menu item ID.
 */
function starter_seed_add_menu_items( int $menu_id, array $items, int $parent_id ): void {
	foreach ( $items as $position => $item ) {
		$args                        = $item['args'];
		$args['menu-item-parent-id'] = $parent_id;
		$args['menu-item-position']  = $position + 1;

		$item_id = wp_update_nav_menu_item( $menu_id, 0, $args );
		if ( ! is_wp_error( $item_id ) && $item['children'] ) {
			starter_seed_add_menu_items( $menu_id, $item['children'], (int) $item_id );
		}
	}
}
