<?php
/**
 * FAQ accordion from CPT starter_faq (starter_get_faqs_for()). Renders nothing without items.
 *
 * The FAQPage schema (starter-core) reads the same placement, so markup and JSON-LD match.
 * Without JS every answer is visible (aria-expanded="true"); main.js adds .is-enhanced to .faq,
 * collapses the answers and keeps aria-expanded in sync.
 *
 * Args:
 * - location (string) Placement: 'home' or a page slug (default: starter_faq_current_location()).
 * - title (string)    Section heading.
 * - id (string)       Section id (default 'faq').
 * - reveal (bool)     Default true.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

$starter_args = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'location' => starter_faq_current_location(),
		'title'    => __( 'Частые вопросы', 'starter' ),
		'id'       => 'faq',
		'reveal'   => true,
	)
);

$starter_faqs = starter_get_faqs_for( (string) $starter_args['location'] );
if ( ! $starter_faqs ) {
	return;
}

$starter_section = sanitize_html_class( (string) $starter_args['id'], 'faq' );
$starter_reveal  = $starter_args['reveal'] ? ' reveal' : '';
?>
<section class="section faq-section" id="<?php echo esc_attr( $starter_section ); ?>" aria-labelledby="<?php echo esc_attr( $starter_section ); ?>-title">
	<div class="container container--narrow">
		<div class="section__head<?php echo esc_attr( $starter_reveal ); ?>">
			<h2 class="section__title" id="<?php echo esc_attr( $starter_section ); ?>-title"><?php echo esc_html( (string) $starter_args['title'] ); ?></h2>
		</div>
		<div class="faq<?php echo esc_attr( $starter_reveal ); ?>">
			<?php foreach ( $starter_faqs as $starter_faq ) : ?>
				<?php $starter_answer_id = $starter_section . '-a-' . $starter_faq->ID; ?>
				<div class="faq__item">
					<h3 class="faq__heading">
						<button class="faq__q" type="button" aria-expanded="true" aria-controls="<?php echo esc_attr( $starter_answer_id ); ?>">
							<span class="faq__q-text"><?php echo esc_html( get_the_title( $starter_faq ) ); ?></span>
							<span class="faq__icon" aria-hidden="true"></span>
						</button>
					</h3>
					<div class="faq__a" id="<?php echo esc_attr( $starter_answer_id ); ?>">
						<div class="faq__a-inner"><?php echo wp_kses_post( wpautop( $starter_faq->post_content ) ); ?></div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
