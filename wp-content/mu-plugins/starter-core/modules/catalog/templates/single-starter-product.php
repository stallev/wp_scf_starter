<?php
/**
 * Single product: head → media + price/sku/characteristics → content.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$starter_product_id      = (int) get_the_ID();
	$starter_price           = (float) starter_field( 'starter_product_price', $starter_product_id );
	$starter_unit            = (string) starter_field( 'starter_product_unit', $starter_product_id );
	$starter_sku             = (string) starter_field( 'starter_product_sku', $starter_product_id );
	$starter_characteristics = (array) starter_field( 'starter_product_characteristics', $starter_product_id );
	$starter_cover           = (int) get_post_thumbnail_id( $starter_product_id );

	$starter_price_line = '';
	if ( $starter_price > 0 ) {
		$starter_price_line = starter_money( $starter_price );
		if ( '' !== $starter_unit ) {
			$starter_price_line .= ' / ' . $starter_unit;
		}
	}
	?>
<main id="main" class="site-main">
	<article class="product" aria-labelledby="product-title">
		<?php get_template_part( 'template-parts/page-head', null, array( 'title' => get_the_title() ) ); ?>

		<div class="section section--tight">
			<div class="container product-layout">
				<?php if ( $starter_cover > 0 ) : ?>
					<figure class="product__media">
						<?php
						starter_the_image(
							$starter_cover,
							'large',
							array(
								'priority' => true,
								'fallback' => false,
							)
						);
						?>
					</figure>
				<?php endif; ?>

				<div class="product__info">
					<?php if ( '' !== $starter_price_line ) : ?>
						<p class="product__price"><?php echo esc_html( $starter_price_line ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $starter_sku ) : ?>
						<p class="product__sku"><?php esc_html_e( 'Артикул:', 'starter' ); ?> <?php echo esc_html( $starter_sku ); ?></p>
					<?php endif; ?>

					<div class="entry-content product__content">
						<?php the_content(); ?>
					</div>

					<?php if ( $starter_characteristics ) : ?>
						<dl class="product__specs">
							<?php
							foreach ( $starter_characteristics as $starter_row ) {
								if ( ! is_array( $starter_row ) ) {
									continue;
								}
								$starter_label = (string) ( $starter_row['label'] ?? '' );
								$starter_value = (string) ( $starter_row['value'] ?? '' );
								if ( '' === $starter_label || '' === $starter_value ) {
									continue;
								}
								?>
								<dt><?php echo esc_html( $starter_label ); ?></dt>
								<dd><?php echo esc_html( $starter_value ); ?></dd>
								<?php
							}
							?>
						</dl>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</article>

	<?php
	if ( starter_page_has_lead_form() ) {
		get_template_part( 'template-parts/lead-section' );
	}
	?>
</main>
	<?php
endwhile;

get_footer();
