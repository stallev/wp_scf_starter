<?php
/**
 * Reviews grid from CPT starter_review (starter_get_reviews()). Renders nothing without items.
 *
 * Args:
 * - limit (int)    Max items, -1 = all (default 6).
 * - title (string) Section heading ('' = no heading, e.g. inside a page that has its own H1).
 * - reveal (bool)  Default true.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

$starter_args = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'limit'  => 6,
		'title'  => __( 'Отзывы клиентов', 'starter' ),
		'reveal' => true,
	)
);

$starter_reviews = starter_get_reviews( (int) $starter_args['limit'] );
if ( ! $starter_reviews ) {
	return;
}

$starter_reveal = $starter_args['reveal'] ? ' reveal' : '';
?>
<section class="section section--alt reviews" id="reviews"<?php echo '' !== (string) $starter_args['title'] ? ' aria-labelledby="reviews-title"' : ''; ?>>
	<div class="container">
		<?php if ( '' !== (string) $starter_args['title'] ) : ?>
			<div class="section__head<?php echo esc_attr( $starter_reveal ); ?>">
				<h2 class="section__title" id="reviews-title"><?php echo esc_html( (string) $starter_args['title'] ); ?></h2>
			</div>
		<?php endif; ?>
		<div class="reviews__grid">
			<?php foreach ( $starter_reviews as $starter_review ) : ?>
				<?php
				$starter_author = trim( (string) starter_field( 'starter_review_author', $starter_review->ID ) );
				$starter_rating = max( 1, min( 5, (int) starter_field( 'starter_review_rating', $starter_review->ID ) ) );
				$starter_source = (string) starter_field( 'starter_review_source_url', $starter_review->ID );
				$starter_meta   = array_filter(
					array(
						trim( (string) starter_field( 'starter_review_location', $starter_review->ID ) ),
						trim( (string) starter_field( 'starter_review_service', $starter_review->ID ) ),
						trim( (string) starter_field( 'starter_review_date_label', $starter_review->ID ) ),
					)
				);
				?>
				<figure class="review<?php echo esc_attr( $starter_reveal ); ?>">
					<?php /* translators: %d: rating 1–5. */ ?>
					<div class="review__stars" role="img" aria-label="<?php echo esc_attr( sprintf( __( 'Оценка %d из 5', 'starter' ), $starter_rating ) ); ?>">
						<?php
						for ( $starter_i = 0; $starter_i < $starter_rating; $starter_i++ ) {
							starter_the_icon( 'star', 16 );
						}
						?>
					</div>
					<p class="review__title"><?php echo esc_html( get_the_title( $starter_review ) ); ?></p>
					<blockquote class="review__text"><?php echo wp_kses_post( wpautop( $starter_review->post_content ) ); ?></blockquote>
					<figcaption class="review__who">
						<span class="review__author"><?php echo esc_html( '' !== $starter_author ? $starter_author : get_the_title( $starter_review ) ); ?></span>
						<?php if ( $starter_meta ) : ?>
							<span class="review__meta"><?php echo esc_html( implode( ' · ', $starter_meta ) ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $starter_source ) : ?>
							<a class="review__source" href="<?php echo esc_url( $starter_source ); ?>" target="_blank" rel="noopener nofollow"><?php esc_html_e( 'Оригинал отзыва', 'starter' ); ?></a>
						<?php endif; ?>
					</figcaption>
				</figure>
			<?php endforeach; ?>
		</div>
	</div>
</section>
