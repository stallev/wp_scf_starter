<?php
/**
 * SCF group: FAQ item (starter_faq). Question = title, answer = content.
 *
 * Location = placement key: `home` for the front page, otherwise the page slug.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Known FAQ placements (key => label): `home` + pages from pages-map, extendable by filter.
 *
 * Values not in this list are still valid (admin groups them under their raw key).
 *
 * @return array<string, string>
 */
function starter_faq_locations(): array {
	$locations = array( 'home' => __( 'Главная', 'starter' ) );

	foreach ( starter_core_pages() as $page ) {
		$type = (string) ( $page['type'] ?? '' );
		$url  = trim( (string) ( $page['url'] ?? '' ), '/' );
		if ( 'front' === $type || '' === $url || ! in_array( $type, array( 'page', 'service', 'blog-index', 'utility' ), true ) ) {
			continue;
		}
		$segments                      = explode( '/', $url );
		$locations[ end( $segments ) ] = (string) ( $page['title'] ?? $url );
	}

	/**
	 * Filter FAQ placements.
	 *
	 * @param array<string, string> $locations Key => label.
	 */
	return (array) apply_filters( 'starter_faq_locations', $locations );
}

/**
 * Register group_starter_faq.
 */
function starter_register_faq_fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_starter_faq',
			'title'    => __( 'FAQ', 'starter' ),
			'fields'   => array(
				starter_scf_field(
					'starter_faq_location',
					__( 'Место вывода', 'starter' ),
					'text',
					array(
						'default_value' => 'home',
						'instructions'  => sprintf(
							/* translators: %s: list of known placement keys. */
							__( '«home» — главная, иначе slug страницы. Известные: %s', 'starter' ),
							implode( ', ', array_keys( starter_faq_locations() ) )
						),
						'wrapper'       => starter_scf_width( 50 ),
					)
				),
				starter_scf_field(
					'starter_faq_order',
					__( 'Порядок', 'starter' ),
					'number',
					array(
						'default_value' => 10,
						'wrapper'       => starter_scf_width( 25 ),
					)
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'starter_faq',
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'starter_register_faq_fields' );
