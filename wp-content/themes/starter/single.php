<?php
/**
 * Single blog post: head (crumbs, H1, meta, author) → cover (LCP image, priority, no .reveal) →
 * content + TOC → author card → related posts → lead section.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$starter_post_id = (int) get_the_ID();
	$starter_cats    = get_the_category( $starter_post_id );
	$starter_minutes = starter_post_reading_minutes( $starter_post_id );
	$starter_cover   = (int) get_post_thumbnail_id( $starter_post_id );
	?>
<main id="main" class="site-main">
	<article class="post" aria-labelledby="post-title">
		<header class="page-head page-head--post">
			<div class="container container--narrow">
				<?php starter_the_breadcrumbs(); ?>
				<h1 class="page-head__title" id="post-title"><?php the_title(); ?></h1>
				<p class="post-meta">
					<?php if ( $starter_cats ) : ?>
						<a class="post-meta__cat" href="<?php echo esc_url( (string) get_category_link( $starter_cats[0] ) ); ?>"><?php echo esc_html( $starter_cats[0]->name ); ?></a>
						<span class="post-meta__sep" aria-hidden="true">·</span>
					<?php endif; ?>
					<time datetime="<?php echo esc_attr( (string) get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( (string) get_the_date() ); ?></time>
					<span class="post-meta__sep" aria-hidden="true">·</span>
					<?php /* translators: %d: reading time in minutes. */ ?>
					<span><?php echo esc_html( sprintf( _n( '%d мин чтения', '%d мин чтения', $starter_minutes, 'starter' ), $starter_minutes ) ); ?></span>
				</p>
				<?php get_template_part( 'template-parts/post-author', null, array( 'variant' => 'byline' ) ); ?>
			</div>
		</header>

		<?php if ( $starter_cover > 0 ) : ?>
			<div class="container container--narrow">
				<figure class="post-cover">
					<?php
					starter_the_image(
						$starter_cover,
						'large',
						array(
							'priority' => true,
							'class'    => 'post-cover__img',
							'sizes'    => '(max-width: 800px) 100vw, 760px',
							'fallback' => false,
						)
					);
					?>
				</figure>
			</div>
		<?php endif; ?>

		<div class="section section--tight">
			<div class="container post-layout">
				<div class="post-layout__content">
					<div class="entry-content post-content">
						<?php
						the_content();
						wp_link_pages(
							array(
								'before' => '<nav class="page-links" aria-label="' . esc_attr__( 'Страницы статьи', 'starter' ) . '">',
								'after'  => '</nav>',
							)
						);
						?>
					</div>
					<?php get_template_part( 'template-parts/post-author', null, array( 'variant' => 'card' ) ); ?>
				</div>
				<?php starter_render_post_toc( $starter_post_id ); ?>
			</div>
		</div>
	</article>

	<?php
	$starter_related = new WP_Query(
		array(
			'post_type'           => 'post',
			'posts_per_page'      => 3,
			'post__not_in'        => array( $starter_post_id ),
			'category__in'        => wp_get_post_categories( $starter_post_id ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);

	if ( $starter_related->have_posts() ) :
		?>
		<section class="section section--alt" aria-labelledby="related-title">
			<div class="container">
				<div class="section__head reveal">
					<h2 class="section__title" id="related-title"><?php esc_html_e( 'Читайте также', 'starter' ); ?></h2>
				</div>
				<div class="posts__grid">
					<?php
					while ( $starter_related->have_posts() ) {
						$starter_related->the_post();
						get_template_part( 'template-parts/post-card', null, array( 'heading' => 'h3' ) );
					}
					wp_reset_postdata();
					?>
				</div>
			</div>
		</section>
		<?php
	endif;

	if ( starter_page_has_lead_form() ) {
		get_template_part( 'template-parts/lead-section' );
	}
	?>
</main>
	<?php
endwhile;

get_footer();
