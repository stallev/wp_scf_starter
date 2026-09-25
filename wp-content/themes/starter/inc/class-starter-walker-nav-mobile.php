<?php
/**
 * Mobile drawer walker (location `mobile`): .menu__item / .menu__link / .menu__sub (accordion).
 *
 * A parent item becomes an accordion button (its own URL is not clickable — add a "All …" child
 * item in the menu if the hub page must be reachable on mobile).
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walker for location `mobile`.
 */
class Starter_Walker_Nav_Mobile extends Walker_Nav_Menu {

	/**
	 * ID of the submenu being rendered (aria-controls target).
	 *
	 * @var string
	 */
	private string $sub_id = '';

	/**
	 * Submenu wrapper.
	 *
	 * @param string        $output Output (by reference).
	 * @param int           $depth  Depth.
	 * @param stdClass|null $args   wp_nav_menu() args.
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ): void {
		if ( 0 === (int) $depth ) {
			$output .= '<div class="menu__sub" id="' . esc_attr( $this->sub_id ) . '"><div class="menu__sub-inner">';
		}
	}

	/**
	 * Close the submenu wrapper.
	 *
	 * @param string        $output Output (by reference).
	 * @param int           $depth  Depth.
	 * @param stdClass|null $args   wp_nav_menu() args.
	 */
	public function end_lvl( &$output, $depth = 0, $args = null ): void {
		if ( 0 === (int) $depth ) {
			$output .= '</div></div>';
		}
	}

	/**
	 * Menu item.
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
			$output .= '<a class="menu__sub-link" href="' . esc_url( $url ) . '"' . $current . '>' . esc_html( $title ) . '</a>';
			return;
		}

		$output .= '<li class="' . esc_attr( 'menu__item' . ( $has_children ? ' menu__item--has-sub' : '' ) ) . '">';

		if ( $has_children ) {
			$this->sub_id = 'menu-sub-' . (int) $data_object->ID;
			$output      .= '<button class="menu__link menu__link--btn" type="button" aria-expanded="false" aria-controls="' . esc_attr( $this->sub_id ) . '">' . esc_html( $title ) . '<span class="menu__caret" aria-hidden="true"></span></button>';
			return;
		}

		$output .= '<a class="menu__link" href="' . esc_url( $url ) . '"' . $current . '>' . esc_html( $title ) . '</a>';
	}

	/**
	 * Close the item.
	 *
	 * @param string        $output      Output (by reference).
	 * @param WP_Post       $data_object Menu item.
	 * @param int           $depth       Depth.
	 * @param stdClass|null $args        wp_nav_menu() args.
	 */
	public function end_el( &$output, $data_object, $depth = 0, $args = null ): void {
		if ( 0 === (int) $depth ) {
			$output .= '</li>';
		}
	}
}
