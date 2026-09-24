<?php
/**
 * Theme bootstrap. Placeholder for M2 (scaffold); modules arrive in M4.
 *
 * CPT / options / forms live in the starter-core mu-plugin.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Theme supports.
 */
function starter_theme_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
}
add_action( 'after_setup_theme', 'starter_theme_setup' );
