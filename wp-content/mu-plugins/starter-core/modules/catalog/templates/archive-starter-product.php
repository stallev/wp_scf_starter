<?php
/**
 * Catalog archive (/catalog/): head → product grid → pagination.
 *
 * Delivered by the module via template_include (starter_catalog_template_include()); theme
 * functions (get_header(), get_template_part(), starter_the_pagination(), starter_image() …) are
 * already available by request time.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

get_header();

$starter_first_page = ! is_paged();
?>
<main id="main" class="site-main">
	<?php
	get_template_part(
		'template-parts/page-head',
		null,
		array(
			'title' => wp_strip_all_tags( get_the_archive_title() ),
			'lead'  => wp_strip_all_tags( get_the_archive_description() ),
		)
	);
	?>

	<section class="section section--tight catalog" aria-label="<?php esc_attr_e( 'Каталог', 'starter' ); ?>">
		<div class="container">
			<?php if ( have_posts() ) : ?>
				<div class="catalog__grid">
					<?php
					$starter_index = 0;
					while ( have_posts() ) {
						the_post();
						// First row (up to 3 cards) is on the first screen: no .reveal; the first card is the LCP image.
						starter_catalog_render_product_card(
							array(
								'priority' => $starter_first_page && 0 === $starter_index,
								'reveal'   => $starter_index >= 3,
							)
						);
						++$starter_index;
					}
					?>
				</div>
				<?php starter_the_pagination(); ?>
			<?php else : ?>
				<p class="catalog__empty"><?php esc_html_e( 'В каталоге пока нет товаров.', 'starter' ); ?></p>
			<?php endif; ?>
		</div>
	</section>

	<?php
	if ( starter_page_has_lead_form() ) {
		get_template_part( 'template-parts/lead-section' );
	}
	?>
</main>
<?php
get_footer();
