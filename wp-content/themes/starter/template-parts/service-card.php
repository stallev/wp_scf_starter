<?php
/**
 * Service card for a page (data: starter_get_service_card()).
 *
 * Args:
 * - page (WP_Post)  Page with a service card (required).
 * - priority (bool) Card image is the page LCP: eager + fetchpriority=high, no .reveal. Default false.
 * - reveal (bool)   Default true (ignored when priority).
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

$starter_page = isset( $args['page'] ) ? $args['page'] : null;
if ( ! $starter_page instanceof WP_Post ) {
	return;
}

$starter_priority = ! empty( $args['priority'] );
$starter_reveal   = ( ! isset( $args['reveal'] ) || (bool) $args['reveal'] ) && ! $starter_priority;
$starter_card     = starter_get_service_card( $starter_page );
$starter_size     = starter_card_size();
?>
<a class="service-card<?php echo $starter_reveal ? ' reveal' : ''; ?>" href="<?php echo esc_url( $starter_card['url'] ); ?>">
	<?php if ( '' !== trim( $starter_card['badge'] ) ) : ?>
		<span class="service-card__badge"><?php echo esc_html( $starter_card['badge'] ); ?></span>
	<?php endif; ?>
	<div class="service-card__media">
		<?php
		starter_the_image(
			$starter_card['image_id'],
			$starter_size['name'],
			array(
				'priority' => $starter_priority,
				'alt'      => '',
				'class'    => 'service-card__img',
				'sizes'    => '(max-width: 599px) 100vw, (max-width: 1023px) 50vw, 33vw',
			)
		);
		?>
	</div>
	<div class="service-card__body">
		<h3 class="service-card__title"><?php echo esc_html( $starter_card['title'] ); ?></h3>
		<?php if ( '' !== trim( $starter_card['text'] ) ) : ?>
			<p class="service-card__text"><?php echo esc_html( $starter_card['text'] ); ?></p>
		<?php endif; ?>
		<div class="service-card__meta">
			<?php if ( '' !== trim( $starter_card['price'] ) ) : ?>
				<span class="service-card__price">
					<?php echo esc_html( $starter_card['price'] ); ?>
					<?php if ( '' !== trim( $starter_card['price_note'] ) ) : ?>
						<small class="service-card__price-note"><?php echo esc_html( $starter_card['price_note'] ); ?></small>
					<?php endif; ?>
				</span>
			<?php endif; ?>
			<span class="service-card__more"><?php esc_html_e( 'Подробнее', 'starter' ); ?> <?php starter_the_icon( 'arrow', 16 ); ?></span>
		</div>
	</div>
</a>
