<?php
/**
 * Contacts page (slug `contacts`): NAP from company options + map facade + lead section.
 *
 * The contacts block is on the first screen: no .reveal, the map is a click-to-load facade
 * (no third-party iframe on page load).
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$starter_phones  = starter_company_phones();
	$starter_email   = starter_company_value( 'email' );
	$starter_address = starter_company_value( 'address.text' );
	$starter_hours   = starter_company_value( 'hours_text' );
	$starter_legal   = starter_company_value( 'legal_name' );
	$starter_map     = starter_company_map();
	?>
<main id="main" class="site-main">
	<?php get_template_part( 'template-parts/page-head', null, array( 'title' => get_the_title() ) ); ?>

	<section class="section section--tight contacts" aria-label="<?php esc_attr_e( 'Контактные данные', 'starter' ); ?>">
		<div class="container contacts__grid">
			<div class="contacts__info">
				<dl class="contacts__list">
					<?php if ( $starter_phones ) : ?>
						<dt class="contacts__term"><?php esc_html_e( 'Телефон', 'starter' ); ?></dt>
						<?php foreach ( $starter_phones as $starter_phone ) : ?>
							<dd class="contacts__value">
								<a class="contacts__link" href="<?php echo esc_url( starter_tel_href( $starter_phone['number'] ) ); ?>"><?php echo esc_html( $starter_phone['number'] ); ?></a>
								<?php if ( '' !== $starter_phone['label'] ) : ?>
									<span class="contacts__note"><?php echo esc_html( $starter_phone['label'] ); ?></span>
								<?php endif; ?>
							</dd>
						<?php endforeach; ?>
					<?php endif; ?>
					<?php if ( '' !== $starter_email && is_email( $starter_email ) ) : ?>
						<dt class="contacts__term"><?php esc_html_e( 'E-mail', 'starter' ); ?></dt>
						<dd class="contacts__value"><a class="contacts__link" href="<?php echo esc_url( 'mailto:' . $starter_email ); ?>"><?php echo esc_html( $starter_email ); ?></a></dd>
					<?php endif; ?>
					<?php if ( '' !== $starter_address ) : ?>
						<dt class="contacts__term"><?php esc_html_e( 'Адрес', 'starter' ); ?></dt>
						<dd class="contacts__value"><?php echo esc_html( $starter_address ); ?></dd>
					<?php endif; ?>
					<?php if ( '' !== $starter_hours ) : ?>
						<dt class="contacts__term"><?php esc_html_e( 'Режим работы', 'starter' ); ?></dt>
						<dd class="contacts__value"><?php echo esc_html( $starter_hours ); ?></dd>
					<?php endif; ?>
					<?php if ( '' !== $starter_legal ) : ?>
						<dt class="contacts__term"><?php esc_html_e( 'Реквизиты', 'starter' ); ?></dt>
						<dd class="contacts__value"><?php echo esc_html( $starter_legal ); ?></dd>
					<?php endif; ?>
				</dl>
				<?php starter_social_links( 'contacts' ); ?>
			</div>

			<?php
			if ( null !== $starter_map ) {
				get_template_part(
					'template-parts/embed-facade',
					null,
					array(
						'src'        => $starter_map['src'],
						'link'       => $starter_map['link'],
						'link_label' => __( 'Открыть карту в новой вкладке', 'starter' ),
						'title'      => __( 'Карта проезда', 'starter' ),
						'kind'       => 'map',
						'reveal'     => false,
					)
				);
			}
			?>
		</div>
	</section>

	<?php if ( '' !== trim( (string) get_the_content() ) ) : ?>
		<section class="section section--tight">
			<div class="container container--narrow entry-content reveal">
				<?php the_content(); ?>
			</div>
		</section>
	<?php endif; ?>

	<?php
	get_template_part( 'template-parts/faq', null, array( 'location' => (string) get_post_field( 'post_name', (int) get_the_ID() ) ) );

	if ( starter_page_has_lead_form() ) {
		get_template_part( 'template-parts/lead-section', null, array( 'form' => array( 'variant' => 'full' ) ) );
	}
	?>
</main>
	<?php
endwhile;

get_footer();
