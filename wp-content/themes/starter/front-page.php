<?php
/**
 * Front page — SKELETON. Replace the hero markup with the prototype's first screen when porting.
 *
 * Structure: hero (H1 = text LCP, NO .reveal) → dynamic sections via parts (services, reviews,
 * projects, FAQ) → lead section. Everything below the hero may use .reveal.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

// Settings → Reading = "latest posts" (no static front page yet, e.g. before `wp starter seed`):
// render the blog loop instead of an empty skeleton.
if ( ! is_page() ) {
	locate_template( 'home.php', true, false );
	return;
}

get_header();

$starter_front_id = (int) get_queried_object_id();
$starter_title    = $starter_front_id > 0 ? get_the_title( $starter_front_id ) : starter_company_value( 'name' );
$starter_lead     = $starter_front_id > 0 && has_excerpt( $starter_front_id ) ? get_the_excerpt( $starter_front_id ) : starter_company_value( 'description' );
$starter_phone    = starter_company_value( 'phone' );
$starter_tel      = starter_tel_href();
$starter_has_lead = starter_page_has_lead_form();
$starter_services = starter_get_service_card_pages();
$starter_content  = $starter_front_id > 0 ? trim( (string) get_post_field( 'post_content', $starter_front_id ) ) : '';
?>
<main id="main" class="site-main">
	<section class="hero" aria-labelledby="hero-title">
		<div class="container hero__inner">
			<h1 class="hero__title" id="hero-title"><?php echo esc_html( '' !== $starter_title ? $starter_title : get_bloginfo( 'name' ) ); ?></h1>
			<?php if ( '' !== $starter_lead ) : ?>
				<p class="hero__lead"><?php echo esc_html( $starter_lead ); ?></p>
			<?php endif; ?>
			<div class="hero__cta">
				<?php if ( $starter_has_lead ) : ?>
					<a class="btn btn--primary btn--lg" href="#lead"><?php esc_html_e( 'Оставить заявку', 'starter' ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $starter_tel ) : ?>
					<a class="btn btn--outline btn--lg" href="<?php echo esc_url( $starter_tel ); ?>"><?php starter_the_icon( 'phone', 18 ); ?><span><?php echo esc_html( $starter_phone ); ?></span></a>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<?php if ( '' !== $starter_content ) : ?>
		<section class="section">
			<div class="container container--narrow entry-content reveal">
				<?php
				while ( have_posts() ) {
					the_post();
					the_content();
				}
				?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $starter_services ) : ?>
		<section class="section services" id="services" aria-labelledby="services-title">
			<div class="container">
				<div class="section__head reveal">
					<h2 class="section__title" id="services-title"><?php esc_html_e( 'Услуги', 'starter' ); ?></h2>
				</div>
				<div class="services__grid">
					<?php
					foreach ( $starter_services as $starter_service ) {
						get_template_part( 'template-parts/service-card', null, array( 'page' => $starter_service ) );
					}
					?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php
	get_template_part( 'template-parts/reviews' );
	get_template_part( 'template-parts/folio' );
	get_template_part( 'template-parts/faq', null, array( 'location' => 'home' ) );

	if ( $starter_has_lead ) {
		get_template_part( 'template-parts/lead-section', null, array( 'form' => array( 'variant' => 'full' ) ) );
	}
	?>
</main>
<?php
get_footer();
