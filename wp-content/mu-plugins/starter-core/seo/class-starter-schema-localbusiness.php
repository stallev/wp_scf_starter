<?php
/**
 * Yoast graph piece: LocalBusiness from the company options (sitewide, stable @id).
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * LocalBusiness / NAP piece.
 */
class Starter_Schema_LocalBusiness {

	/**
	 * Identifier for `wpseo_schema_needs_*` / `wpseo_schema_*` filters.
	 *
	 * @var string
	 */
	public $identifier = 'starter_localbusiness';

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
	 * Output on every page once the company has a name.
	 *
	 * @return bool
	 */
	public function is_needed() {
		return '' !== (string) starter_get_company( 'name' );
	}

	/**
	 * Build the piece.
	 *
	 * @return array<string, mixed>|false
	 */
	public function generate() {
		$company = starter_get_company();
		if ( ! is_array( $company ) || '' === (string) $company['name'] ) {
			return false;
		}

		$type = preg_match( '/^[A-Za-z]+$/', (string) $company['business_type'] ) ? (string) $company['business_type'] : 'LocalBusiness';
		$data = array(
			'@type' => $type,
			'@id'   => starter_schema_localbusiness_id(),
			'name'  => (string) $company['name'],
			'url'   => home_url( '/' ),
		);

		if ( '' !== (string) $company['legal_name'] ) {
			$data['legalName'] = (string) $company['legal_name'];
		}
		if ( '' !== (string) $company['description'] ) {
			$data['description'] = (string) $company['description'];
		}
		if ( '' !== (string) $company['phone_href'] ) {
			$data['telephone'] = (string) $company['phone_href'];
		}
		if ( '' !== (string) $company['email'] ) {
			$data['email'] = (string) $company['email'];
		}

		$image_id = (int) $company['default_image_id'];
		$image    = $image_id > 0 ? wp_get_attachment_image_url( $image_id, 'full' ) : false;
		if ( is_string( $image ) ) {
			$data['image'] = $image;
		}

		$address = $this->address( (array) $company['address'] );
		if ( $address ) {
			$data['address'] = $address;
		}

		$geo = (array) $company['geo'];
		if ( is_numeric( $geo['lat'] ?? null ) && is_numeric( $geo['lng'] ?? null ) ) {
			$data['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $geo['lat'],
				'longitude' => (float) $geo['lng'],
			);
		}

		$area = starter_schema_area_served();
		if ( $area ) {
			$data['areaServed'] = $area;
		}

		$hours = $this->opening_hours( (array) $company['opening_hours'] );
		if ( $hours ) {
			$data['openingHoursSpecification'] = $hours;
		}

		if ( '' !== (string) $company['price_range'] ) {
			$data['priceRange'] = (string) $company['price_range'];
		}

		$same_as = array();
		foreach ( (array) $company['socials'] as $social ) {
			$url = is_array( $social ) ? (string) ( $social['url'] ?? '' ) : '';
			if ( str_starts_with( $url, 'https://' ) || str_starts_with( $url, 'http://' ) ) {
				$same_as[] = $url;
			}
		}
		if ( $same_as ) {
			$data['sameAs'] = $same_as;
		}

		return $data;
	}

	/**
	 * PostalAddress from the company address.
	 *
	 * @param array<string, mixed> $address Company address.
	 * @return array<string, string>
	 */
	private function address( array $address ): array {
		$map    = array(
			'street'      => 'streetAddress',
			'locality'    => 'addressLocality',
			'region'      => 'addressRegion',
			'postal_code' => 'postalCode',
			'country'     => 'addressCountry',
		);
		$result = array();

		foreach ( $map as $key => $prop ) {
			$value = trim( (string) ( $address[ $key ] ?? '' ) );
			if ( '' !== $value ) {
				$result[ $prop ] = $value;
			}
		}

		return $result ? array( '@type' => 'PostalAddress' ) + $result : array();
	}

	/**
	 * OpeningHoursSpecification list.
	 *
	 * @param array<int, mixed> $rows Company opening hours.
	 * @return array<int, array<string, mixed>>
	 */
	private function opening_hours( array $rows ): array {
		$result = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['days'] ) || empty( $row['opens'] ) || empty( $row['closes'] ) ) {
				continue;
			}
			$result[] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => array_values( (array) $row['days'] ),
				'opens'     => (string) $row['opens'],
				'closes'    => (string) $row['closes'],
			);
		}

		return $result;
	}
}
