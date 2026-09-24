<?php
/**
 * Default data shapes. No client data and no default identifiers (GA4 etc.) live in code.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Empty company skeleton: every key starter_get_company() returns, with neutral values.
 *
 * @return array<string, mixed>
 */
function starter_default_company(): array {
	return array(
		'name'             => (string) get_bloginfo( 'name' ),
		'legal_name'       => '',
		'description'      => (string) get_bloginfo( 'description' ),
		'business_type'    => 'LocalBusiness',
		'phones'           => array(),
		'phone'            => '',
		'phone_href'       => '',
		'email'            => '',
		'address'          => array(
			'street'      => '',
			'locality'    => '',
			'region'      => '',
			'postal_code' => '',
			'country'     => '',
			'text'        => '',
		),
		'geo'              => array(
			'lat' => null,
			'lng' => null,
		),
		'opening_hours'    => array(),
		'hours_text'       => '',
		'socials'          => array(),
		'ga4_id'           => '',
		'default_image_id' => 0,
		'area_served'      => array(),
		'price_range'      => '',
	);
}

/**
 * Social networks offered in the company options (key => label).
 *
 * @return array<string, string>
 */
function starter_social_networks(): array {
	$networks = array(
		'telegram'  => 'Telegram',
		'whatsapp'  => 'WhatsApp',
		'viber'     => 'Viber',
		'instagram' => 'Instagram',
		'vk'        => 'VK',
		'facebook'  => 'Facebook',
		'youtube'   => 'YouTube',
		'other'     => __( 'Другое', 'starter' ),
	);

	/**
	 * Filter the social networks list.
	 *
	 * @param array<string, string> $networks Key => label.
	 */
	return (array) apply_filters( 'starter_social_networks', $networks );
}

/**
 * Day names in schema.org form for opening hours (key => label).
 *
 * @return array<string, string>
 */
function starter_week_days(): array {
	return array(
		'Monday'    => __( 'Пн', 'starter' ),
		'Tuesday'   => __( 'Вт', 'starter' ),
		'Wednesday' => __( 'Ср', 'starter' ),
		'Thursday'  => __( 'Чт', 'starter' ),
		'Friday'    => __( 'Пт', 'starter' ),
		'Saturday'  => __( 'Сб', 'starter' ),
		'Sunday'    => __( 'Вс', 'starter' ),
	);
}

/**
 * Lead statuses (key => label).
 *
 * @return array<string, string>
 */
function starter_lead_statuses(): array {
	return array(
		'new'         => __( 'Новая', 'starter' ),
		'in_progress' => __( 'В работе', 'starter' ),
		'done'        => __( 'Закрыта', 'starter' ),
		'spam'        => __( 'Спам', 'starter' ),
	);
}
