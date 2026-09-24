<?php
/**
 * Shared helpers: array paths, money format, SCF access, company provider, shortcodes.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Set a nested array value by dotted path.
 *
 * @param array<string, mixed> $data  Target array (by reference).
 * @param string               $path  Dotted path, e.g. address.locality.
 * @param mixed                $value Value to set.
 */
function starter_set_by_path( array &$data, string $path, $value ): void {
	$keys    = explode( '.', $path );
	$last    = array_pop( $keys );
	$current = &$data;

	foreach ( $keys as $key ) {
		if ( ! isset( $current[ $key ] ) || ! is_array( $current[ $key ] ) ) {
			$current[ $key ] = array();
		}
		$current = &$current[ $key ];
	}

	$current[ $last ] = $value;
}

/**
 * Read a nested array value by dotted path.
 *
 * @param array<string, mixed> $data    Source array.
 * @param string               $path    Dotted path.
 * @param mixed                $fallback Returned when the path is missing.
 * @return mixed
 */
function starter_get_by_path( array $data, string $path, $fallback = null ) {
	$current = $data;

	foreach ( explode( '.', $path ) as $key ) {
		if ( ! is_array( $current ) || ! array_key_exists( $key, $current ) ) {
			return $fallback;
		}
		$current = $current[ $key ];
	}

	return $current;
}

/**
 * Format a money amount for display (locale-aware thousands separator).
 *
 * Currency symbol is added by the template or the provider, not here.
 *
 * @param float|int|string $value    Amount.
 * @param int              $decimals Decimals (default from filter `starter_money_decimals`, 0).
 * @return string
 */
function starter_money( $value, ?int $decimals = null ): string {
	if ( null === $decimals ) {
		$decimals = (int) apply_filters( 'starter_money_decimals', 0 );
	}

	return number_format_i18n( (float) $value, $decimals );
}

/**
 * SCF option value by field name (K13: always by name, never by key).
 *
 * @param string $name Field name.
 * @return mixed Null when SCF is inactive or the field is empty.
 */
function starter_option( string $name ) {
	if ( ! function_exists( 'get_field' ) ) {
		$value = get_option( 'options_' . $name, null );
		return ( '' === $value || false === $value ) ? null : $value;
	}

	$value = get_field( $name, 'option' );

	return ( '' === $value || false === $value ) ? null : $value;
}

/**
 * SCF post field by name with a post-meta fallback when SCF is inactive.
 *
 * @param string $name    Field name.
 * @param int    $post_id Post ID.
 * @return mixed
 */
function starter_field( string $name, int $post_id ) {
	if ( function_exists( 'get_field' ) ) {
		return get_field( $name, $post_id );
	}

	return get_post_meta( $post_id, $name, true );
}

/**
 * Normalize a phone number into a tel: href value (+digits).
 *
 * @param string $phone Human-readable phone.
 * @return string E.g. +10000000000, or '' when there are no digits.
 */
function starter_phone_href( string $phone ): string {
	$digits = (string) preg_replace( '/[^\d+]/', '', $phone );
	$digits = ltrim( $digits, '+' );

	return '' === $digits ? '' : '+' . $digits;
}

/**
 * Split a textarea value into trimmed non-empty lines.
 *
 * @param mixed $value Textarea value or list.
 * @return string[]
 */
function starter_lines( $value ): array {
	if ( is_array( $value ) ) {
		$lines = $value;
	} else {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $value );
		$lines = is_array( $lines ) ? $lines : array();
	}

	return array_values( array_filter( array_map( 'trim', array_map( 'strval', $lines ) ), static fn( string $line ): bool => '' !== $line ) );
}

/**
 * Company / NAP data from the `starter-company` options page, merged over empty defaults.
 *
 * Shape (see starter_default_company()): name, legal_name, description, business_type, phones[],
 * phone, phone_href, email, address{street, locality, region, postal_code, country, text},
 * geo{lat, lng}, opening_hours[], hours_text, socials[], ga4_id, default_image_id, area_served[],
 * price_range. Templates must read NAP only through this provider (no hardcoded phones).
 *
 * @param string $key Optional dotted key, e.g. 'phone' or 'address.locality'.
 * @return mixed Whole array when $key is empty, otherwise the value ('' when missing).
 */
function starter_get_company( string $key = '' ) {
	static $cache = null;

	$company = $cache;
	if ( null === $company ) {
		$company = starter_default_company();

		$scalars = array(
			'name'          => 'starter_company_name',
			'legal_name'    => 'starter_company_legal_name',
			'description'   => 'starter_company_description',
			'business_type' => 'starter_company_business_type',
			'email'         => 'starter_company_email',
			'hours_text'    => 'starter_company_hours_text',
			'ga4_id'        => 'starter_company_ga4_id',
			'price_range'   => 'starter_company_price_range',
		);
		foreach ( $scalars as $prop => $field ) {
			$value = starter_option( $field );
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				$company[ $prop ] = trim( (string) $value );
			}
		}

		$address = array(
			'street'      => 'starter_company_street',
			'locality'    => 'starter_company_locality',
			'region'      => 'starter_company_region',
			'postal_code' => 'starter_company_postal_code',
			'country'     => 'starter_company_country',
			'text'        => 'starter_company_address_text',
		);
		foreach ( $address as $prop => $field ) {
			$value = starter_option( $field );
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				$company['address'][ $prop ] = trim( (string) $value );
			}
		}

		foreach ( array( 'lat', 'lng' ) as $axis ) {
			$value = starter_option( 'starter_company_geo_' . $axis );
			if ( is_numeric( $value ) ) {
				$company['geo'][ $axis ] = (float) $value;
			}
		}

		$phones = starter_option( 'starter_company_phones' );
		if ( is_array( $phones ) ) {
			foreach ( $phones as $row ) {
				$number = is_array( $row ) ? trim( (string) ( $row['number'] ?? '' ) ) : '';
				if ( '' === $number ) {
					continue;
				}
				$company['phones'][] = array(
					'number' => $number,
					'href'   => starter_phone_href( $number ),
					'label'  => trim( (string) ( $row['label'] ?? '' ) ),
				);
			}
		}
		if ( ! empty( $company['phones'] ) ) {
			$company['phone']      = $company['phones'][0]['number'];
			$company['phone_href'] = $company['phones'][0]['href'];
		}

		$hours = starter_option( 'starter_company_opening_hours' );
		if ( is_array( $hours ) ) {
			foreach ( $hours as $row ) {
				if ( ! is_array( $row ) || empty( $row['days'] ) ) {
					continue;
				}
				$company['opening_hours'][] = array(
					'days'   => array_values( array_map( 'strval', (array) $row['days'] ) ),
					'opens'  => (string) ( $row['opens'] ?? '' ),
					'closes' => (string) ( $row['closes'] ?? '' ),
				);
			}
		}

		$socials = starter_option( 'starter_company_socials' );
		if ( is_array( $socials ) ) {
			foreach ( $socials as $row ) {
				$url = is_array( $row ) ? trim( (string) ( $row['url'] ?? '' ) ) : '';
				if ( '' === $url ) {
					continue;
				}
				$company['socials'][] = array(
					'network' => (string) ( $row['network'] ?? 'other' ),
					'url'     => $url,
					'label'   => trim( (string) ( $row['label'] ?? '' ) ),
				);
			}
		}

		$image = starter_option( 'starter_company_default_image' );
		if ( is_numeric( $image ) ) {
			$company['default_image_id'] = (int) $image;
		} elseif ( is_array( $image ) && isset( $image['ID'] ) ) {
			$company['default_image_id'] = (int) $image['ID'];
		}

		$company['area_served'] = starter_lines( starter_option( 'starter_company_area_served' ) );

		/**
		 * Filter the company data before it is cached for the request.
		 *
		 * @param array<string, mixed> $company Company data.
		 */
		$company = (array) apply_filters( 'starter_company', $company );

		// SCF resolves field names only after acf/init; do not cache an early, incomplete read.
		if ( ! function_exists( 'get_field' ) || did_action( 'acf/init' ) ) {
			$cache = $company;
		}
	}

	if ( '' === $key ) {
		return $company;
	}

	return starter_get_by_path( $company, $key, '' );
}

/**
 * Social link URL by network (telegram, whatsapp, viber, instagram, vk, facebook, …).
 *
 * @param string $network Network key.
 * @return string '' when not set.
 */
function starter_get_social_url( string $network ): string {
	$socials = starter_get_company( 'socials' );
	if ( ! is_array( $socials ) ) {
		return '';
	}

	foreach ( $socials as $row ) {
		if ( is_array( $row ) && ( $row['network'] ?? '' ) === $network ) {
			return (string) $row['url'];
		}
	}

	return '';
}

/**
 * GA4 Measurement ID from company options. Empty = analytics disabled (no default ID in code).
 *
 * @return string
 */
function starter_get_ga4_id(): string {
	$id = (string) starter_get_company( 'ga4_id' );

	return (bool) preg_match( '/^G-[A-Z0-9]+$/', $id ) ? $id : '';
}

/**
 * Fallback image for cards without a featured image (company option, replaces a hardcoded ID).
 *
 * @return int Attachment ID or 0.
 */
function starter_get_default_image_id(): int {
	return (int) starter_get_company( 'default_image_id' );
}

/**
 * URL of a file in this package (admin assets).
 *
 * @param string $relative Path relative to starter-core/, e.g. assets/admin-faq.css.
 * @return string
 */
function starter_core_url( string $relative ): string {
	return plugins_url( ltrim( $relative, '/' ), STARTER_CORE_PATH . '/boot.php' );
}

/**
 * Cache-busting version of a package file (filemtime, falls back to the plugin version).
 *
 * @param string $relative Path relative to starter-core/.
 * @return string
 */
function starter_core_asset_version( string $relative ): string {
	$file  = STARTER_CORE_PATH . '/' . ltrim( $relative, '/' );
	$mtime = is_readable( $file ) ? filemtime( $file ) : false;

	return false === $mtime ? STARTER_CORE_VERSION : (string) $mtime;
}

/**
 * Shortcode [starter_company key="phone"] — company value for editor content.
 *
 * @param array<string, string>|string $atts Shortcode attributes.
 * @return string Escaped text.
 */
function starter_company_shortcode( $atts ): string {
	$atts = shortcode_atts( array( 'key' => '' ), $atts, 'starter_company' );
	if ( '' === $atts['key'] ) {
		return '';
	}

	$value = starter_get_company( sanitize_text_field( $atts['key'] ) );
	if ( is_array( $value ) ) {
		$value = implode( ', ', array_filter( $value, 'is_scalar' ) );
	}

	return esc_html( (string) $value );
}
add_shortcode( 'starter_company', 'starter_company_shortcode' );

/**
 * SCF field definition with the key convention `field_{name}` (keys are for SCF only; code reads by name).
 *
 * @param string               $name  Field name (prefixed, unique).
 * @param string               $label Label.
 * @param string               $type  SCF field type.
 * @param array<string, mixed> $extra Extra settings (instructions, wrapper, choices, sub_fields, …).
 * @return array<string, mixed>
 */
function starter_scf_field( string $name, string $label, string $type = 'text', array $extra = array() ): array {
	return array_merge(
		array(
			'key'   => 'field_' . $name,
			'name'  => $name,
			'label' => $label,
			'type'  => $type,
		),
		$extra
	);
}

/**
 * Repeater sub field: short name inside the row, globally unique key `field_{parent}_{name}`.
 *
 * @param string               $parent_name Parent field name.
 * @param string               $name   Sub field name (row key).
 * @param string               $label  Label.
 * @param string               $type   SCF field type.
 * @param array<string, mixed> $extra  Extra settings.
 * @return array<string, mixed>
 */
function starter_scf_sub_field( string $parent_name, string $name, string $label, string $type = 'text', array $extra = array() ): array {
	$field        = starter_scf_field( $name, $label, $type, $extra );
	$field['key'] = 'field_' . $parent_name . '_' . $name;

	return $field;
}

/**
 * SCF tab field (visual grouping only, stores nothing).
 *
 * @param string $group Group slug for a unique key.
 * @param string $id    Tab id, unique within the group.
 * @param string $label Tab label.
 * @return array<string, mixed>
 */
function starter_scf_tab( string $group, string $id, string $label ): array {
	return array(
		'key'       => 'field_' . $group . '_tab_' . $id,
		'label'     => $label,
		'type'      => 'tab',
		'placement' => 'top',
	);
}

/**
 * SCF wrapper width setting.
 *
 * @param int $width Percent.
 * @return array<string, string>
 */
function starter_scf_width( int $width ): array {
	return array(
		'width' => (string) $width,
		'class' => '',
		'id'    => '',
	);
}

/**
 * Write an SCF field by name (resolves the key by the field_{name} convention so the reference meta
 * is stored even on the first write); plain post meta / option when SCF is inactive.
 *
 * @param string     $name    Field name.
 * @param mixed      $value   Value.
 * @param int|string $post_id Post ID or 'option'.
 */
function starter_update_field( string $name, $value, $post_id ): void {
	if ( function_exists( 'update_field' ) ) {
		update_field( 'field_' . $name, $value, $post_id );
		return;
	}

	if ( 'option' === $post_id ) {
		update_option( 'options_' . $name, $value );
		return;
	}

	update_post_meta( (int) $post_id, $name, $value );
}
