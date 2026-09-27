<?php
/**
 * Catalog family archive (/catalog/family/<slug>/): head → product grid → pagination.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

get_header();

$starter_first_page = ! is_paged();
$starter_term       = get_queried_object();
?>
<main id="main" class="site-main">
	<?php
	get_template_part(
		'template-parts/page-head',
		null,
		array(
			'title' => $starter_term instanceof WP_Term ? $starter_term->name : wp_strip_all_tags( get_the_archive_title() ),
			'lead'  => $starter_term instanceof WP_Term ? wp_strip_all_tags( term_description( $starter_term->term_id ) ) : '',
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
				<p class="catalog__empty"><?php esc_html_e( 'В этой категории пока нет товаров.', 'starter' ); ?></p>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php
get_footer();
