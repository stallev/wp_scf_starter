<?php
/**
 * Theme bootstrap: loads inc/* modules only.
 *
 * Presentation lives here (enqueue, templates, parts). Data — CPT, options, fields, forms, SEO —
 * lives in the starter-core mu-plugin and is read only through its starter_get_*() providers.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

define( 'STARTER_THEME_VERSION', '0.1.0' );

$starter_inc = get_template_directory() . '/inc/';

require_once $starter_inc . 'setup.php';
require_once $starter_inc . 'template-tags.php';
require_once $starter_inc . 'assets.php';
require_once $starter_inc . 'images.php';
require_once $starter_inc . 'analytics.php';
require_once $starter_inc . 'head.php';
require_once $starter_inc . 'post-toc.php';
require_once $starter_inc . 'class-starter-walker-nav-primary.php';
require_once $starter_inc . 'class-starter-walker-nav-mobile.php';
require_once $starter_inc . 'class-starter-walker-nav-footer.php';

unset( $starter_inc );
