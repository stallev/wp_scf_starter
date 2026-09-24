<?php
/**
 * Yoast graph piece: Service on service pages.
 *
 * A page is a service page when its pages-map entry has type "service" or lists "Service" in `schema`.
 * Name/description come from the service card fields, then the page; `starter_schema_service`
 * filter adds offers, serviceType, hasOfferCatalog, etc.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Service piece.
 */
class Starter_Schema_Service {

	/**
	 * Identifier for Yoast filters.
	 *
	 * @var string
	 */
	public $identifier = 'starter_service';

	/**
	 * Yoast Meta_Tags_Context.
	 *
	 * @var mixed
	 */
	public $context;

	/**
	 * Constructor.
	 *
	 * @param mixed $context Yoast Meta_Tags_Context.
	 */
	public function __construct( $context ) {
		$this->context = $context;
	}

	/**
	 * Only on pages declared as services in pages-map.
	 *
	 * @return bool
	 */
	public function is_needed() {
		if ( ! is_page() ) {
			return false;
		}

		$page = starter_current_page_config();
		if ( null === $page ) {
			return false;
		}

		return 'service' === ( $page['type'] ?? '' ) || in_array( 'Service', (array) ( $page['schema'] ?? array() ), true );
	}

	/**
	 * Build the piece.
	 *
	 * @return array<string, mixed>|false
	 */
	public function generate() {
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		$card        = starter_get_service_card( $post );
		$description = '' !== trim( $card['text'] ) ? $card['text'] : (string) get_the_excerpt( $post );
		$url         = (string) get_permalink( $post );

		$data = array(
			'@type'      => 'Service',
			'@id'        => trailingslashit( starter_schema_canonical() ) . '#/schema/service',
			'name'       => $card['title'],
			'url'        => '' !== $url ? $url : starter_schema_canonical(),
			'provider'   => array( '@id' => starter_schema_localbusiness_id() ),
			'areaServed' => starter_schema_area_served(),
		);

		$description = trim( wp_strip_all_tags( $description ) );
		if ( '' !== $description ) {
			$data['description'] = $description;
		}
		if ( $card['image_id'] > 0 ) {
			$image = wp_get_attachment_image_url( $card['image_id'], 'full' );
			if ( is_string( $image ) ) {
				$data['image'] = $image;
			}
		}
		if ( empty( $data['areaServed'] ) ) {
			unset( $data['areaServed'] );
		}

		/**
		 * Filter the Service piece (add offers with prices from a pricebook module, serviceType, …).
		 *
		 * @param array<string, mixed> $data Service piece.
		 * @param WP_Post              $post Service page.
		 */
		return (array) apply_filters( 'starter_schema_service', $data, $post );
	}
}
