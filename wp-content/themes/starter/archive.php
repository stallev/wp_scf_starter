<?php
/**
 * Archives (category, tag, author, date, CPT): head → post cards (first card of page 1 is the
 * priority image) → pagination. No lead form unless pages-map / the filter says so.
 *
 * @package Starter
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

	<section class="section section--tight posts" aria-label="<?php esc_attr_e( 'Записи', 'starter' ); ?>">
		<div class="container">
			<?php if ( have_posts() ) : ?>
				<div class="posts__grid">
					<?php
					$starter_index = 0;
					while ( have_posts() ) {
						the_post();
						// First row (up to 3 cards) is on the first screen: no .reveal; the first card is the LCP image.
						get_template_part(
							'template-parts/post-card',
							null,
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
				<p class="posts__empty"><?php esc_html_e( 'Здесь пока ничего нет.', 'starter' ); ?></p>
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
