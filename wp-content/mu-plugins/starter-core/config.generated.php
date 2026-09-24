<?php
/**
 * Runtime config snapshot — generated, do not edit.
 *
 * Source: project.config.json + pages-map.json. Regenerate: npm run build:config.
 * Read via starter_core_config().
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

return array(
	'project'   => array(
		'name'        => 'Starter',
		'description' => 'WordPress + SCF starter for AI-assisted corporate sites',
	),
	'slug'      => array(
		'prefix'      => 'starter',
		'theme'       => 'starter',
		'core'        => 'starter-core',
		'text_domain' => 'starter',
	),
	'locale'    => 'ru_RU',
	'urls'      => array(
		'local'      => 'http://localhost:8888',
		'staging'    => null,
		'production' => null,
	),
	'images'    => array(
		'card'         => array(
			'name'  => 'card',
			'width' => 768,
		),
		'webp_quality' => 80,
	),
	'analytics' => array(
		'ga4_delay_ms' => 2500,
	),
	'modules'   => array(
		'catalog'   => false,
		'pricebook' => false,
	),
	'pages'     => array(),
);
