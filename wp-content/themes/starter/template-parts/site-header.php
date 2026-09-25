<?php
/**
 * Global header: brand, desktop nav (primary), phone, CTA, burger + mobile drawer (mobile).
 *
 * Menus come from WP locations; without an assigned menu a pages-map fallback is shown.
 * NAP only from starter_get_company().
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

$starter_name       = starter_company_value( 'name' );
$starter_phone      = starter_company_value( 'phone' );
$starter_phone_href = starter_tel_href();
$starter_hours      = starter_company_value( 'hours_text' );
$starter_cta_href   = starter_page_has_lead_form() ? '#lead' : starter_home_hash( 'lead' );
?>
<header class="header" id="header">
	<div class="container header__bar">
		<a class="brand header__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php starter_brand_mark(); ?>
			<span class="brand__name"><?php echo esc_html( '' !== $starter_name ? $starter_name : get_bloginfo( 'name' ) ); ?></span>
		</a>

		<nav class="nav header__nav" aria-label="<?php esc_attr_e( 'Основная навигация', 'starter' ); ?>">
			<?php
			if ( starter_has_menu_items( 'primary' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => '',
						'items_wrap'     => '<ul class="nav__list">%3$s</ul>',
						'fallback_cb'    => false,
						'depth'          => 2,
						'walker'         => new Starter_Walker_Nav_Primary(),
					)
				);
			} else {
				get_template_part( 'template-parts/nav-primary-fallback' );
			}
			?>
		</nav>

		<div class="header__actions">
			<?php if ( '' !== $starter_phone && '' !== $starter_phone_href ) : ?>
				<div class="header__phone">
					<a class="header__phone-link" href="<?php echo esc_url( $starter_phone_href ); ?>"><?php echo esc_html( $starter_phone ); ?></a>
					<?php if ( '' !== $starter_hours ) : ?>
						<span class="header__hours"><?php echo esc_html( $starter_hours ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<a class="btn btn--primary btn--sm header__cta" href="<?php echo esc_url( $starter_cta_href ); ?>"><?php esc_html_e( 'Оставить заявку', 'starter' ); ?></a>
			<button class="burger" id="burger" type="button" aria-expanded="false" aria-controls="menu" aria-label="<?php esc_attr_e( 'Открыть меню', 'starter' ); ?>" data-label-open="<?php esc_attr_e( 'Открыть меню', 'starter' ); ?>" data-label-close="<?php esc_attr_e( 'Закрыть меню', 'starter' ); ?>">
				<span class="burger__box" aria-hidden="true"><span class="burger__line"></span><span class="burger__line"></span><span class="burger__line"></span></span>
			</button>
		</div>
	</div>

	<nav class="menu" id="menu" aria-label="<?php esc_attr_e( 'Мобильное меню', 'starter' ); ?>">
		<?php
		if ( starter_has_menu_items( 'mobile' ) ) {
			wp_nav_menu(
				array(
					'theme_location' => 'mobile',
					'container'      => '',
					'items_wrap'     => '<ul class="menu__list">%3$s</ul>',
					'fallback_cb'    => false,
					'depth'          => 2,
					'walker'         => new Starter_Walker_Nav_Mobile(),
				)
			);
		} else {
			get_template_part( 'template-parts/nav-mobile-fallback' );
		}
		?>
		<div class="menu__footer">
			<?php if ( '' !== $starter_phone && '' !== $starter_phone_href ) : ?>
				<a class="menu__phone" href="<?php echo esc_url( $starter_phone_href ); ?>"><?php echo esc_html( $starter_phone ); ?></a>
			<?php endif; ?>
			<?php if ( '' !== $starter_hours ) : ?>
				<p class="menu__note"><?php echo esc_html( $starter_hours ); ?></p>
			<?php endif; ?>
			<?php starter_social_links( 'menu' ); ?>
		</div>
	</nav>
</header>
