<?php
/**
 * Footer links fallback while no menu is assigned to `footer`: one column of pages-map pages.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

$starter_links = starter_fallback_nav_links();
if ( ! $starter_links ) {
	return;
}
?>
<div class="footer__col">
	<p class="footer__col-title"><?php esc_html_e( 'Разделы', 'starter' ); ?></p>
	<ul class="footer__links">
		<?php foreach ( $starter_links as $starter_link ) : ?>
			<li class="footer__links-item"><a class="footer__link" href="<?php echo esc_url( $starter_link['url'] ); ?>"><?php echo esc_html( $starter_link['title'] ); ?></a></li>
		<?php endforeach; ?>
	</ul>
</div>
