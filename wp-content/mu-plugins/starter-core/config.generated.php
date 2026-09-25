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
	'fonts'     => array(
		'preload' => array(),
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
	'pages'     => array(
		array(
			'url'       => '/',
			'title'     => 'Услуги для частных клиентов и бизнеса',
			'template'  => 'front-page.php',
			'type'      => 'front',
			'noindex'   => false,
			'lead_form' => true,
			'schema'    => array(
				'LocalBusiness',
				'FAQPage',
			),
		),
		array(
			'url'       => '/services/',
			'title'     => 'Услуги',
			'template'  => 'page.php',
			'type'      => 'page',
			'noindex'   => false,
			'lead_form' => true,
			'schema'    => array(),
		),
		array(
			'url'       => '/services/demo-service/',
			'title'     => 'Демо-услуга',
			'template'  => 'page.php',
			'type'      => 'service',
			'noindex'   => false,
			'lead_form' => true,
			'schema'    => array(
				'Service',
			),
		),
		array(
			'url'       => '/services/demo-service-2/',
			'title'     => 'Вторая демо-услуга',
			'template'  => 'page.php',
			'type'      => 'service',
			'noindex'   => false,
			'lead_form' => true,
			'schema'    => array(
				'Service',
			),
		),
		array(
			'url'       => '/about/',
			'title'     => 'О компании',
			'template'  => 'page.php',
			'type'      => 'page',
			'noindex'   => false,
			'lead_form' => true,
			'schema'    => array(),
		),
		array(
			'url'       => '/contacts/',
			'title'     => 'Контакты',
			'template'  => 'page-contacts.php',
			'type'      => 'page',
			'noindex'   => false,
			'lead_form' => true,
			'schema'    => array(
				'LocalBusiness',
			),
		),
		array(
			'url'       => '/blog/',
			'title'     => 'Блог',
			'template'  => 'home.php',
			'type'      => 'blog-index',
			'noindex'   => false,
			'lead_form' => true,
			'schema'    => array(),
		),
		array(
			'url'       => '/demo-post-getting-started/',
			'title'     => 'С чего начать: демо-статья',
			'template'  => 'single.php',
			'type'      => 'post',
			'noindex'   => false,
			'lead_form' => true,
			'schema'    => array(),
		),
		array(
			'url'       => '/privacy-policy/',
			'title'     => 'Политика конфиденциальности',
			'template'  => 'page.php',
			'type'      => 'utility',
			'noindex'   => true,
			'lead_form' => false,
			'schema'    => array(),
		),
	),
);
