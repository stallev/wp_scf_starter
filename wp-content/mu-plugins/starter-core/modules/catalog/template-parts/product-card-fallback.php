<?php
/**
 * Fallback product card, used when the project has no theme part `template-parts/product-card.php`.
 *
 * Included from starter_catalog_render_product_card() inside The Loop: relies on the current global
 * $post (get_the_ID(), the_permalink(), the_title() — no post argument needed).
 *
 * Args (via $starter_catalog_card_args, set by the caller): priority (bool), reveal (bool).
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

$starter_pcf_args = wp_parse_args(
	isset( $starter_catalog_card_args ) && is_array( $starter_catalog_card_args ) ? $starter_catalog_card_args : array(),
	array(
		'priority' => false,
		'reveal'   => true,
	)
);

$starter_pcf_id    = get_the_ID();
$starter_pcf_price = (float) starter_field( 'starter_product_price', $starter_pcf_id );
$starter_pcf_unit  = (string) starter_field( 'starter_product_unit', $starter_pcf_id );

$starter_pcf_price_line = '';
if ( $starter_pcf_price > 0 ) {
	$starter_pcf_price_line = starter_money( $starter_pcf_price );
	if ( '' !== $starter_pcf_unit ) {
		$starter_pcf_price_line .= ' / ' . $starter_pcf_unit;
	}
}
?>
<article class="product-card<?php echo $starter_pcf_args['reveal'] ? ' reveal' : ''; ?>">
	<a class="product-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php
		starter_the_image(
			get_post_thumbnail_id( $starter_pcf_id ),
			'card',
			array( 'priority' => $starter_pcf_args['priority'] )
		);
		?>
	</a>
	<div class="product-card__body">
		<h3 class="product-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<?php if ( '' !== $starter_pcf_price_line ) : ?>
			<p class="product-card__price"><?php echo esc_html( $starter_pcf_price_line ); ?></p>
		<?php endif; ?>
	</div>
</article>
