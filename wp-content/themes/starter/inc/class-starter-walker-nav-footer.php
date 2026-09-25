<?php
/**
 * Footer walker (location `footer`): depth 0 = column title, depth 1 = links of the column.
 *
 * Output: <div class="footer__col"><p class="footer__col-title">…</p><ul class="footer__links"><li>…
 * A top-level item without children becomes a column with a linked title.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walker for location `footer`.
 */
class Starter_Walker_Nav_Footer extends Walker_Nav_Menu {

	/**
	 * Links list of a column.
	 *
	 * @param string        $output Output (by reference).
	 * @param int           $depth  Depth.
	 * @param stdClass|null $args   wp_nav_menu() args.
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ): void {
		if ( 0 === (int) $depth ) {
			$output .= '<ul class="footer__links">';
		}
	}

	/**
	 * Close the links list.
	 *
	 * @param string        $output Output (by reference).
	 * @param int           $depth  Depth.
	 * @param stdClass|null $args   wp_nav_menu() args.
	 */
	public function end_lvl( &$output, $depth = 0, $args = null ): void {
		if ( 0 === (int) $depth ) {
			$output .= '</ul>';
		}
	}

	/**
	 * Column title or link.
	 *
	 * @param string        $output            Output (by reference).
	 * @param WP_Post       $data_object       Menu item.
	 * @param int           $depth             Depth.
	 * @param stdClass|null $args              wp_nav_menu() args.
	 * @param int           $current_object_id Current item ID.
	 */
	public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ): void {
		$classes      = empty( $data_object->classes ) ? array() : (array) $data_object->classes;
		$has_children = in_array( 'menu-item-has-children', $classes, true );
		$title        = (string) apply_filters( 'the_title', isset( $data_object->title ) ? (string) $data_object->title : '', $data_object->ID ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter, applied as in Walker_Nav_Menu.
		$url          = isset( $data_object->url ) ? (string) $data_object->url : '';
		$current      = ! empty( $data_object->current ) ? ' aria-current="page"' : '';

		if ( $depth > 0 ) {
			$output .= '<li class="footer__links-item"><a class="footer__link" href="' . esc_url( $url ) . '"' . $current . '>' . esc_html( $title ) . '</a></li>';
			return;
		}

		$output .= '<div class="footer__col">';
		if ( $has_children || '' === $url || '#' === $url ) {
			$output .= '<p class="footer__col-title">' . esc_html( $title ) . '</p>';
		} else {
			$output .= '<p class="footer__col-title"><a class="footer__link" href="' . esc_url( $url ) . '"' . $current . '>' . esc_html( $title ) . '</a></p>';
		}
	}

	/**
	 * Close the column.
	 *
	 * @param string        $output      Output (by reference).
	 * @param WP_Post       $data_object Menu item.
	 * @param int           $depth       Depth.
	 * @param stdClass|null $args        wp_nav_menu() args.
	 */
	public function end_el( &$output, $data_object, $depth = 0, $args = null ): void {
		if ( 0 === (int) $depth ) {
			$output .= '</div>';
		}
	}
}
