<?php
/**
 * Generic page: page head (H1 = text LCP, no .reveal) → editor content → child pages as cards →
 * projects of a service page → FAQ of this page → lead section (per pages-map lead_form).
 *
 * Pages ported from the prototype get their own template (page-{slug}.php) via /port-page.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$starter_page_id  = (int) get_the_ID();
	$starter_slug     = (string) get_post_field( 'post_name', $starter_page_id );
	$starter_config   = starter_page_config();
	$starter_type     = is_array( $starter_config ) ? (string) ( $starter_config['type'] ?? 'page' ) : 'page';
	$starter_children = get_pages(
		array(
			'parent'      => $starter_page_id,
			'sort_column' => 'menu_order,post_title',
		)
	);
	?>
<main id="main" class="site-main">
	<?php
	get_template_part(
		'template-parts/page-head',
		null,
		array(
			'title' => get_the_title(),
			'lead'  => has_excerpt() ? get_the_excerpt() : '',
		)
	);
	?>

	<?php if ( '' !== trim( (string) get_the_content() ) ) : ?>
		<section class="section section--tight">
			<div class="container container--narrow entry-content">
				<?php the_content(); ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $starter_children ) : ?>
		<section class="section services" aria-labelledby="children-title">
			<div class="container">
				<div class="section__head reveal">
					<h2 class="section__title" id="children-title"><?php esc_html_e( 'Направления', 'starter' ); ?></h2>
				</div>
				<div class="services__grid">
					<?php
					foreach ( $starter_children as $starter_child ) {
						get_template_part( 'template-parts/service-card', null, array( 'page' => $starter_child ) );
					}
					?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php
	if ( 'service' === $starter_type ) {
		get_template_part(
			'template-parts/folio',
			null,
			array(
				'service' => $starter_slug,
				'title'   => __( 'Примеры работ', 'starter' ),
			)
		);
	}

	get_template_part( 'template-parts/faq', null, array( 'location' => $starter_slug ) );

	if ( starter_page_has_lead_form() ) {
		get_template_part(
			'template-parts/lead-section',
			null,
			array( 'form' => array( 'service' => 'service' === $starter_type ? get_the_title() : '' ) )
		);
	}
	?>
</main>
	<?php
endwhile;

get_footer();
