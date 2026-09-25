<?php
/**
 * Blog index (posts page): head (H1 = text LCP) → category chips (3+ categories) → post cards
 * (the first card of page 1 is eager + fetchpriority=high, no .reveal) → pagination → lead section.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

get_header();

$starter_posts_page = (int) get_option( 'page_for_posts' );
$starter_title      = $starter_posts_page > 0 ? get_the_title( $starter_posts_page ) : __( 'Блог', 'starter' );
$starter_lead       = $starter_posts_page > 0 && has_excerpt( $starter_posts_page ) ? get_the_excerpt( $starter_posts_page ) : '';
$starter_cats       = starter_blog_filter_categories();
$starter_first_page = ! is_paged();
?>
<main id="main" class="site-main">
	<?php
	get_template_part(
		'template-parts/page-head',
		null,
		array(
			'title' => $starter_title,
			'lead'  => $starter_lead,
		)
	);
	?>

	<section class="section section--tight posts" aria-label="<?php esc_attr_e( 'Статьи', 'starter' ); ?>">
		<div class="container">
			<?php if ( count( $starter_cats ) >= 3 ) : ?>
				<nav class="chips" aria-label="<?php esc_attr_e( 'Рубрики', 'starter' ); ?>">
					<a class="chip is-active" href="<?php echo esc_url( starter_blog_url() ); ?>" aria-current="page"><?php esc_html_e( 'Все статьи', 'starter' ); ?></a>
					<?php foreach ( $starter_cats as $starter_cat ) : ?>
						<a class="chip" href="<?php echo esc_url( get_category_link( $starter_cat->term_id ) ); ?>"><?php echo esc_html( $starter_cat->name ); ?></a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>

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
				<p class="posts__empty"><?php esc_html_e( 'Статьи скоро появятся.', 'starter' ); ?></p>
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
