<?php
/**
 * First-screen page header: breadcrumbs + H1 (text LCP candidate) + optional lead-in.
 *
 * Never add .reveal here: the H1 is the LCP node of inner pages (K1).
 *
 * Args:
 * - title (string) H1 text (default: the queried title).
 * - lead (string)  Optional paragraph under the H1.
 * - crumbs (bool)  Show breadcrumbs (default true).
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

$starter_args = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'title'  => single_post_title( '', false ),
		'lead'   => '',
		'crumbs' => true,
	)
);
?>
<header class="page-head">
	<div class="container">
		<?php
		if ( $starter_args['crumbs'] ) {
			starter_the_breadcrumbs();
		}
		?>
		<h1 class="page-head__title"><?php echo esc_html( (string) $starter_args['title'] ); ?></h1>
		<?php if ( '' !== (string) $starter_args['lead'] ) : ?>
			<p class="page-head__lead"><?php echo esc_html( (string) $starter_args['lead'] ); ?></p>
		<?php endif; ?>
	</div>
</header>
