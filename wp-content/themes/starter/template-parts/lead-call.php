<?php
/**
 * Contact card: call / messenger buttons (not a form, no .js-lead). Renders nothing without data.
 *
 * Args:
 * - title (string) Optional heading.
 * - reveal (bool)  Default true.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

$starter_args = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'title'  => '',
		'reveal' => true,
	)
);

$starter_phone      = starter_company_value( 'phone' );
$starter_phone_href = starter_tel_href();
$starter_messengers = array_values(
	array_filter(
		starter_company_socials(),
		static fn( array $social ): bool => in_array( $social['network'], array( 'telegram', 'whatsapp', 'viber' ), true )
	)
);

if ( '' === $starter_phone_href && ! $starter_messengers ) {
	return;
}
?>
<div class="lead-call<?php echo $starter_args['reveal'] ? ' reveal' : ''; ?>">
	<?php if ( '' !== (string) $starter_args['title'] ) : ?>
		<p class="lead-call__title"><?php echo esc_html( (string) $starter_args['title'] ); ?></p>
	<?php endif; ?>
	<div class="lead-call__actions">
		<?php if ( '' !== $starter_phone_href ) : ?>
			<a class="btn btn--outline lead-call__btn" href="<?php echo esc_url( $starter_phone_href ); ?>">
				<?php starter_the_icon( 'phone', 18 ); ?>
				<span><?php echo esc_html( $starter_phone ); ?></span>
			</a>
		<?php endif; ?>
		<?php foreach ( $starter_messengers as $starter_messenger ) : ?>
			<a class="btn btn--outline lead-call__btn lead-call__btn--<?php echo esc_attr( $starter_messenger['network'] ); ?>" href="<?php echo esc_url( $starter_messenger['url'] ); ?>" target="_blank" rel="noopener">
				<?php starter_the_icon( $starter_messenger['network'], 18 ); ?>
				<span><?php echo esc_html( $starter_messenger['label'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
</div>
