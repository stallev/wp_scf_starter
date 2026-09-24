<?php
/**
 * Plugin Name: Starter Core
 * Description: Data layer of the site: CPT, SCF options and fields, lead forms, seed, SEO pieces, llms.txt.
 * Version: 0.1.0
 * Requires PHP: 8.1
 *
 * Must-use loader. Presentation (enqueue, templates) belongs to the theme, not here.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

define( 'STARTER_CORE_VERSION', '0.1.0' );
define( 'STARTER_CORE_PATH', __DIR__ . '/starter-core' );

require_once STARTER_CORE_PATH . '/boot.php';
