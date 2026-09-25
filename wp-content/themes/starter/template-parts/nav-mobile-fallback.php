<?php
/**
 * Mobile drawer fallback while no menu is assigned to `mobile`: top-level pages from pages-map.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

$starter_links = starter_fallback_nav_links();
?>
<ul class="menu__list">
	<li class="menu__item"><a class="menu__link" href="<?php echo esc_url( home_url( '/' ) ); ?>"<?php echo is_front_page() ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Главная', 'starter' ); ?></a></li>
	<?php foreach ( $starter_links as $starter_link ) : ?>
		<li class="menu__item"><a class="menu__link" href="<?php echo esc_url( $starter_link['url'] ); ?>"<?php echo $starter_link['current'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $starter_link['title'] ); ?></a></li>
	<?php endforeach; ?>
</ul>
