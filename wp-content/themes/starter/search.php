<?php
/**
 * Search results (simple list, no images, no lead form).
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

get_header();

/* translators: %s: search query. */
$starter_title = sprintf( __( 'Поиск: %s', 'starter' ), get_search_query() );
?>
<main id="main" class="site-main">
	<?php get_template_part( 'template-parts/page-head', null, array( 'title' => $starter_title ) ); ?>

	<section class="section section--tight" aria-label="<?php esc_attr_e( 'Результаты поиска', 'starter' ); ?>">
		<div class="container container--narrow">
			<?php get_search_form(); ?>

			<?php if ( have_posts() ) : ?>
				<ol class="search-results">
					<?php
					while ( have_posts() ) :
						the_post();
						?>
						<li class="search-results__item">
							<h2 class="search-results__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<p class="search-results__excerpt"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
						</li>
					<?php endwhile; ?>
				</ol>
				<?php starter_the_pagination(); ?>
			<?php else : ?>
				<p class="posts__empty"><?php esc_html_e( 'Ничего не найдено. Попробуйте другой запрос.', 'starter' ); ?></p>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php
get_footer();
