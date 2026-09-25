<?php
/**
 * Document head and the site header. Each template opens its own <main id="main">.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main"><?php esc_html_e( 'Перейти к содержимому', 'starter' ); ?></a>
<?php get_template_part( 'template-parts/site-header' ); ?>
