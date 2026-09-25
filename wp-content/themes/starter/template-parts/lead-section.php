<?php
/**
 * Lead section (#lead): heading + contact card (lead-call) + the page's lead form.
 *
 * Render it only when starter_page_has_lead_form() is true — it contains the page's single
 * form.js-lead. Below the fold by default (reveal).
 *
 * Args:
 * - title, text (string) Section heading and lead-in.
 * - form (array)          Args for template-parts/lead-form.
 * - reveal (bool)         Default true.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

$starter_args = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'title'  => __( 'Обсудим вашу задачу', 'starter' ),
		'text'   => __( 'Оставьте контакт — перезвоним, уточним детали и сориентируем по стоимости.', 'starter' ),
		'form'   => array(),
		'reveal' => true,
	)
);

$starter_reveal = (bool) $starter_args['reveal'];
$starter_form   = array_merge( (array) $starter_args['form'], array( 'reveal' => $starter_reveal ) );
?>
<section class="section section--alt lead-section" id="lead" aria-labelledby="lead-title">
	<div class="container lead-section__grid">
		<div class="lead-section__intro<?php echo $starter_reveal ? ' reveal' : ''; ?>">
			<h2 class="section__title" id="lead-title"><?php echo esc_html( (string) $starter_args['title'] ); ?></h2>
			<?php if ( '' !== (string) $starter_args['text'] ) : ?>
				<p class="section__lead"><?php echo esc_html( (string) $starter_args['text'] ); ?></p>
			<?php endif; ?>
			<?php get_template_part( 'template-parts/lead-call', null, array( 'reveal' => false ) ); ?>
		</div>
		<?php get_template_part( 'template-parts/lead-form', null, $starter_form ); ?>
	</div>
</section>
