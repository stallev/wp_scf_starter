<?php
/**
 * Yoast graph piece: Product/Offer on catalog archive, taxonomy and single pages.
 *
 * Unlike Starter_Schema_Service (gated by a pages-map entry — a fixed set of static pages), the
 * catalog has dynamic per-product URLs that pages-map cannot enumerate, so visibility is decided
 * directly from the queried post type / taxonomy.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Product/Offer piece.
 */
class Starter_Schema_ProductOffer {

	/**
	 * Identifier for Yoast filters.
	 *
	 * @var string
	 */
	public $identifier = 'starter_product_offer';

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
	 * Catalog single, archive or family taxonomy.
	 *
	 * @return bool
	 */
	public function is_needed() {
		return is_singular( 'starter_product' ) || is_post_type_archive( 'starter_product' ) || is_tax( 'starter_product_family' );
	}

	/**
	 * Build the piece: a single Product on single, an ItemList of Products on archive/taxonomy.
	 *
	 * @return array<string, mixed>|false
	 */
	public function generate() {
		if ( is_singular( 'starter_product' ) ) {
			$post = get_queried_object();
			return $post instanceof WP_Post ? $this->product_node( $post ) : false;
		}

		global $wp_query;
		$items    = array();
		$position = 1;
		foreach ( (array) ( $wp_query->posts ?? array() ) as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'item'     => $this->product_node( $post ),
			);
		}

		if ( ! $items ) {
			return false;
		}

		return array(
			'@type'           => 'ItemList',
			'@id'             => trailingslashit( starter_schema_canonical() ) . '#/schema/product-list',
			'itemListElement' => $items,
		);
	}

	/**
	 * Product node with an inline Offer.
	 *
	 * @param WP_Post $post Product post.
	 * @return array<string, mixed>
	 */
	private function product_node( WP_Post $post ): array {
		$price = (float) starter_field( 'starter_product_price', $post->ID );
		$unit  = (string) starter_field( 'starter_product_unit', $post->ID );
		$sku   = (string) starter_field( 'starter_product_sku', $post->ID );
		$url   = (string) get_permalink( $post );

		$data = array(
			'@type' => 'Product',
			'@id'   => trailingslashit( $url ) . '#/schema/product',
			'name'  => get_the_title( $post ),
			'url'   => $url,
		);

		$description = trim( wp_strip_all_tags( '' !== $post->post_excerpt ? $post->post_excerpt : $post->post_content ) );
		if ( '' !== $description ) {
			$data['description'] = $description;
		}
		if ( '' !== $sku ) {
			$data['sku'] = $sku;
		}

		$image_id = (int) get_post_thumbnail_id( $post );
		if ( $image_id > 0 ) {
			$image = wp_get_attachment_image_url( $image_id, 'full' );
			if ( is_string( $image ) ) {
				$data['image'] = $image;
			}
		}

		if ( $price > 0 ) {
			$offer = array(
				'@type'         => 'Offer',
				'price'         => (string) $price,
				'priceCurrency' => (string) apply_filters( 'starter_schema_price_currency', 'RUB' ),
				'availability'  => 'https://schema.org/InStock',
				'url'           => $url,
			);
			if ( '' !== $unit ) {
				$offer['eligibleQuantity'] = array(
					'@type'    => 'QuantitativeValue',
					'unitText' => $unit,
				);
			}
			$data['offers'] = $offer;
		}

		/**
		 * Filter the Product piece (add brand, gtin, aggregateRating, …).
		 *
		 * @param array<string, mixed> $data Product piece.
		 * @param WP_Post              $post Product post.
		 */
		return (array) apply_filters( 'starter_schema_product', $data, $post );
	}
}
