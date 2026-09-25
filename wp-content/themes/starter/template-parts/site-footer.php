<?php
/**
 * Global footer: brand + description, footer menu columns, contacts, legal line.
 *
 * NAP only from starter_get_company(); every block is skipped when its data is empty.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

$starter_name    = starter_company_value( 'name' );
$starter_about   = starter_company_value( 'description' );
$starter_legal   = starter_company_value( 'legal_name' );
$starter_email   = starter_company_value( 'email' );
$starter_address = starter_company_value( 'address.text' );
$starter_hours   = starter_company_value( 'hours_text' );
$starter_phones  = starter_company_phones();
$starter_privacy = starter_privacy_url();
$starter_brand   = '' !== $starter_name ? $starter_name : get_bloginfo( 'name' );
?>
<footer class="footer" id="footer">
	<div class="container">
		<div class="footer__grid">
			<div class="footer__col footer__col--brand">
				<a class="brand footer__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php starter_brand_mark(); ?>
					<span class="brand__name"><?php echo esc_html( $starter_brand ); ?></span>
				</a>
				<?php if ( '' !== $starter_about ) : ?>
					<p class="footer__about"><?php echo esc_html( $starter_about ); ?></p>
				<?php endif; ?>
			</div>

			<?php
			if ( starter_has_menu_items( 'footer' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => '',
						'items_wrap'     => '%3$s',
						'fallback_cb'    => false,
						'depth'          => 2,
						'walker'         => new Starter_Walker_Nav_Footer(),
					)
				);
			} else {
				get_template_part( 'template-parts/footer-links-fallback' );
			}
			?>

			<div class="footer__col footer__col--contacts">
				<p class="footer__col-title"><?php esc_html_e( 'Контакты', 'starter' ); ?></p>
				<ul class="footer__contacts">
					<?php foreach ( $starter_phones as $starter_phone ) : ?>
						<li class="footer__contact">
							<?php starter_the_icon( 'phone', 18 ); ?>
							<a class="footer__link" href="<?php echo esc_url( starter_tel_href( $starter_phone['number'] ) ); ?>"><?php echo esc_html( $starter_phone['number'] ); ?></a>
							<?php if ( '' !== $starter_phone['label'] ) : ?>
								<span class="footer__contact-note"><?php echo esc_html( $starter_phone['label'] ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
					<?php if ( '' !== $starter_email && is_email( $starter_email ) ) : ?>
						<li class="footer__contact">
							<?php starter_the_icon( 'mail', 18 ); ?>
							<a class="footer__link" href="<?php echo esc_url( 'mailto:' . $starter_email ); ?>"><?php echo esc_html( $starter_email ); ?></a>
						</li>
					<?php endif; ?>
					<?php if ( '' !== $starter_address ) : ?>
						<li class="footer__contact"><?php starter_the_icon( 'pin', 18 ); ?><span><?php echo esc_html( $starter_address ); ?></span></li>
					<?php endif; ?>
					<?php if ( '' !== $starter_hours ) : ?>
						<li class="footer__contact"><?php starter_the_icon( 'clock', 18 ); ?><span><?php echo esc_html( $starter_hours ); ?></span></li>
					<?php endif; ?>
				</ul>
				<?php starter_social_links( 'footer' ); ?>
			</div>
		</div>

		<div class="footer__legal">
			<p class="footer__copy">&copy; <?php echo esc_html( gmdate( 'Y' ) . ' ' . ( '' !== $starter_legal ? $starter_legal : $starter_brand ) ); ?></p>
			<?php if ( '' !== $starter_privacy ) : ?>
				<a class="footer__link" href="<?php echo esc_url( $starter_privacy ); ?>"><?php esc_html_e( 'Политика конфиденциальности', 'starter' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</footer>
