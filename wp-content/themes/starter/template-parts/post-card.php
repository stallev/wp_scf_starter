<?php
/**
 * Blog post card (current post of the loop).
 *
 * Args:
 * - priority (bool) The card is on the first screen and its image is the page LCP:
 *                   eager + fetchpriority=high, no .reveal. Only the first card of page 1.
 * - reveal (bool)   Default true (ignored when priority).
 * - heading (string) Title tag: h2 (default, blog index) or h3 (related posts under an h2).
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

$starter_priority = ! empty( $args['priority'] );
$starter_reveal   = ( ! isset( $args['reveal'] ) || (bool) $args['reveal'] ) && ! $starter_priority;
$starter_heading  = isset( $args['heading'] ) && 'h3' === $args['heading'] ? 'h3' : 'h2';
$starter_post_id  = (int) get_the_ID();
$starter_cats     = get_the_category( $starter_post_id );
$starter_cat      = $starter_cats ? $starter_cats[0] : null;
$starter_minutes  = starter_post_reading_minutes( $starter_post_id );
$starter_size     = starter_card_size();
?>
<article class="post-card<?php echo $starter_reveal ? ' reveal' : ''; ?>">
	<a class="post-card__link" href="<?php the_permalink(); ?>">
		<div class="post-card__media">
			<?php
			starter_the_image(
				(int) get_post_thumbnail_id( $starter_post_id ),
				$starter_size['name'],
				array(
					'priority' => $starter_priority,
					'alt'      => '',
					'class'    => 'post-card__img',
					'sizes'    => '(max-width: 599px) 100vw, (max-width: 1023px) 50vw, 33vw',
				)
			);
			?>
		</div>
		<div class="post-card__body">
			<?php if ( $starter_cat ) : ?>
				<span class="post-card__cat"><?php echo esc_html( $starter_cat->name ); ?></span>
			<?php endif; ?>
			<<?php echo esc_html( $starter_heading ); ?> class="post-card__title"><?php the_title(); ?></<?php echo esc_html( $starter_heading ); ?>>
			<p class="post-card__excerpt"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
			<p class="post-card__meta">
				<time datetime="<?php echo esc_attr( (string) get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( (string) get_the_date() ); ?></time>
				<span class="post-card__sep" aria-hidden="true">·</span>
				<?php /* translators: %d: reading time in minutes. */ ?>
				<span><?php echo esc_html( sprintf( _n( '%d мин чтения', '%d мин чтения', $starter_minutes, 'starter' ), $starter_minutes ) ); ?></span>
			</p>
		</div>
	</a>
</article>
