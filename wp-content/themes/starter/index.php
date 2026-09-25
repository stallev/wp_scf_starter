<?php
/**
 * Fallback template (any view without a more specific one).
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="site-main">
	<?php get_template_part( 'template-parts/page-head', null, array( 'title' => wp_strip_all_tags( wp_get_document_title() ) ) ); ?>

	<section class="section section--tight posts">
		<div class="container">
			<?php if ( have_posts() ) : ?>
				<div class="posts__grid">
					<?php
					while ( have_posts() ) {
						the_post();
						get_template_part( 'template-parts/post-card' );
					}
					?>
				</div>
				<?php starter_the_pagination(); ?>
			<?php else : ?>
				<p class="posts__empty"><?php esc_html_e( 'Здесь пока ничего нет.', 'starter' ); ?></p>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php
get_footer();
