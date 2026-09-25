<?php
/**
 * Desktop nav fallback while no menu is assigned to `primary`: top-level pages from pages-map.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

$starter_links = starter_fallback_nav_links();
if ( ! $starter_links ) {
	return;
}
?>
<ul class="nav__list">
	<?php foreach ( $starter_links as $starter_link ) : ?>
		<li class="nav__item"><a class="nav__link" href="<?php echo esc_url( $starter_link['url'] ); ?>"<?php echo $starter_link['current'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $starter_link['title'] ); ?></a></li>
	<?php endforeach; ?>
</ul>
