<?php
/**
 * Yoast graph piece: FAQPage from starter_faq items of the current placement.
 *
 * Emitted only on pages whose pages-map entry lists "FAQPage" in `schema` — i.e. pages where the
 * theme renders the FAQ block — because schema must match visible content. Override per request with
 * the `starter_schema_faqpage_enabled` filter.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * FAQPage piece.
 */
class Starter_Schema_FAQPage {

	/**
	 * Identifier for Yoast filters.
	 *
	 * @var string
	 */
	public $identifier = 'starter_faqpage';

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
	 * Only on pages declared with FAQPage in pages-map that have FAQ items for their placement.
	 *
	 * @return bool
	 */
	public function is_needed() {
		$page    = starter_current_page_config();
		$enabled = null !== $page && in_array( 'FAQPage', (array) ( $page['schema'] ?? array() ), true );

		/**
		 * Filter whether the FAQPage piece may be emitted on the current request.
		 *
		 * @param bool                      $enabled Default: pages-map entry lists "FAQPage".
		 * @param array<string, mixed>|null $page    pages-map entry of the current page.
		 */
		if ( ! apply_filters( 'starter_schema_faqpage_enabled', $enabled, $page ) ) {
			return false;
		}

		$location = starter_faq_current_location();

		return '' !== $location && array() !== starter_get_faqs_for( $location );
	}

	/**
	 * Build the piece.
	 *
	 * @return array<string, mixed>|false
	 */
	public function generate() {
		$entities = array();

		foreach ( starter_get_faqs_for( starter_faq_current_location() ) as $faq ) {
			// Plain text of the answer; no the_content filter here (third-party side effects during <head>).
			$html   = do_shortcode( wpautop( $faq->post_content ) );
			$answer = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $html ) ) );
			if ( '' === $answer ) {
				continue;
			}
			$entities[] = array(
				'@type'          => 'Question',
				'name'           => get_the_title( $faq ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $answer,
				),
			);
		}

		if ( ! $entities ) {
			return false;
		}

		$data = array(
			'@type'      => 'FAQPage',
			'@id'        => trailingslashit( starter_schema_canonical() ) . '#/schema/faq',
			'mainEntity' => $entities,
		);

		// Link to the Yoast WebPage node of this URL.
		if ( is_object( $this->context ) && ! empty( $this->context->main_schema_id ) ) {
			$data['isPartOf'] = array( '@id' => (string) $this->context->main_schema_id );
		}

		return $data;
	}
}
