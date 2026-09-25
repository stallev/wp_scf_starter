<?php
/**
 * Desktop nav walker (location `primary`): .nav__item / .nav__link / .nav__sub / .nav__sub-link.
 *
 * Two levels. A parent with an empty or "#" URL renders as a button; a parent with a URL renders
 * as a link plus a separate toggle button (keyboard and touch users can open the submenu).
 * Menu item description → secondary line of a submenu link.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walker for location `primary`.
 */
class Starter_Walker_Nav_Primary extends Walker_Nav_Menu {

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
			$output .= '<div class="nav__sub" id="' . esc_attr( $this->sub_id ) . '">';
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
			$output .= '</div>';
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
		$desc         = isset( $data_object->description ) ? trim( (string) $data_object->description ) : '';

		if ( $depth > 0 ) {
			$output .= '<a class="nav__sub-link" href="' . esc_url( $url ) . '"' . $current . '>';
			$output .= '<span class="nav__sub-title">' . esc_html( $title ) . '</span>';
			if ( '' !== $desc ) {
				$output .= '<span class="nav__sub-desc">' . esc_html( $desc ) . '</span>';
			}
			$output .= '</a>';
			return;
		}

		$this->sub_id = 'nav-sub-' . (int) $data_object->ID;
		$controls     = ' aria-controls="' . esc_attr( $this->sub_id ) . '"';

		$output .= '<li class="' . esc_attr( 'nav__item' . ( $has_children ? ' nav__item--has-sub' : '' ) ) . '">';

		if ( $has_children && ( '' === $url || '#' === $url ) ) {
			$output .= '<button class="nav__link nav__link--btn nav__toggle" type="button" aria-expanded="false"' . $controls . '>' . esc_html( $title ) . '<span class="nav__caret" aria-hidden="true"></span></button>';
			return;
		}

		$output .= '<a class="nav__link" href="' . esc_url( $url ) . '"' . $current . '>' . esc_html( $title ) . '</a>';
		if ( $has_children ) {
			/* translators: %s: menu item title. */
			$label   = sprintf( __( 'Подменю: %s', 'starter' ), $title );
			$output .= '<button class="nav__toggle nav__toggle--icon" type="button" aria-expanded="false"' . $controls . ' aria-label="' . esc_attr( $label ) . '"><span class="nav__caret" aria-hidden="true"></span></button>';
		}
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
