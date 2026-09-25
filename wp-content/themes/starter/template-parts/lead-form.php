<?php
/**
 * Lead form — the only form.js-lead of a page (K14: exactly one per page, none on utility pages;
 * the caller decides via starter_page_has_lead_form()).
 *
 * Contract (starter-core forms.php): POST admin-ajax.php, action + nonce + source + honeypot come
 * from starter_lead_hidden_fields(); fields name, contact, service, message, consent.
 * main.js submits via fetch and toggles is-sending / is-sent / is-error; on success it dispatches
 * `starter:lead:success` { formId, source } (analytics.js → generate_lead).
 *
 * Args:
 * - form_id (string)      Element id, default 'lead-form'.
 * - title, text (string)  Heading and subtitle.
 * - submit_label (string) Button text.
 * - done_title, done_text Success state.
 * - variant (string)      contact | name_contact (default) | full (adds a message field).
 * - service (string)      Prefilled service name (hidden field), e.g. on a service page.
 * - source (string)       Lead source key; default starter_lead_source().
 * - reveal (bool)         Add .reveal (default true; pass false on the first screen).
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

$starter_args = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'form_id'      => 'lead-form',
		'title'        => __( 'Оставьте заявку', 'starter' ),
		'text'         => __( 'Перезвоним в рабочее время и ответим на вопросы.', 'starter' ),
		'submit_label' => __( 'Отправить заявку', 'starter' ),
		'done_title'   => __( 'Заявка отправлена', 'starter' ),
		'done_text'    => __( 'Спасибо! Мы свяжемся с вами в ближайшее время.', 'starter' ),
		'variant'      => 'name_contact',
		'service'      => '',
		'source'       => starter_lead_source(),
		'reveal'       => true,
	)
);

$starter_id      = sanitize_html_class( (string) $starter_args['form_id'], 'lead-form' );
$starter_variant = (string) $starter_args['variant'];
$starter_privacy = starter_privacy_url();
$starter_classes = 'lead-form js-lead' . ( $starter_args['reveal'] ? ' reveal' : '' );
?>
<form class="<?php echo esc_attr( $starter_classes ); ?>" id="<?php echo esc_attr( $starter_id ); ?>"<?php echo '' !== (string) $starter_args['title'] ? ' aria-labelledby="' . esc_attr( $starter_id ) . '-title"' : ' aria-label="' . esc_attr__( 'Заявка', 'starter' ) . '"'; ?> action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" method="post" novalidate>
	<div class="lead-form__body">
		<?php if ( '' !== (string) $starter_args['title'] ) : ?>
			<p class="lead-form__title" id="<?php echo esc_attr( $starter_id ); ?>-title"><?php echo esc_html( (string) $starter_args['title'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== (string) $starter_args['text'] ) : ?>
			<p class="lead-form__text"><?php echo esc_html( (string) $starter_args['text'] ); ?></p>
		<?php endif; ?>

		<div class="lead-form__fields">
			<?php if ( 'contact' !== $starter_variant ) : ?>
				<div class="field">
					<label class="field__label" for="<?php echo esc_attr( $starter_id ); ?>-name"><?php esc_html_e( 'Имя', 'starter' ); ?></label>
					<input class="field__input" id="<?php echo esc_attr( $starter_id ); ?>-name" name="name" type="text" autocomplete="name" maxlength="100">
				</div>
			<?php endif; ?>
			<div class="field">
				<label class="field__label" for="<?php echo esc_attr( $starter_id ); ?>-contact"><?php esc_html_e( 'Телефон или мессенджер', 'starter' ); ?> <span class="field__req" aria-hidden="true">*</span></label>
				<input class="field__input" id="<?php echo esc_attr( $starter_id ); ?>-contact" name="contact" type="text" autocomplete="tel" inputmode="tel" maxlength="200" required aria-required="true">
			</div>
			<?php if ( 'full' === $starter_variant ) : ?>
				<div class="field">
					<label class="field__label" for="<?php echo esc_attr( $starter_id ); ?>-message"><?php esc_html_e( 'Комментарий', 'starter' ); ?></label>
					<textarea class="field__input field__input--area" id="<?php echo esc_attr( $starter_id ); ?>-message" name="message" rows="3" maxlength="2000"></textarea>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( '' !== (string) $starter_args['service'] ) : ?>
			<input type="hidden" name="service" value="<?php echo esc_attr( (string) $starter_args['service'] ); ?>">
		<?php endif; ?>

		<div class="field field--check">
			<input class="field__checkbox" id="<?php echo esc_attr( $starter_id ); ?>-consent" name="consent" type="checkbox" value="1" required aria-required="true">
			<label class="field__check-label" for="<?php echo esc_attr( $starter_id ); ?>-consent">
				<?php if ( '' !== $starter_privacy ) : ?>
					<?php
					printf(
						/* translators: %s: privacy policy link. */
						esc_html__( 'Согласен(-на) с %s', 'starter' ),
						'<a class="lead-form__privacy" href="' . esc_url( $starter_privacy ) . '" target="_blank" rel="noopener">' . esc_html__( 'политикой обработки персональных данных', 'starter' ) . '</a>'
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'Согласен(-на) на обработку персональных данных', 'starter' ); ?>
				<?php endif; ?>
			</label>
		</div>

		<?php echo starter_lead_hidden_fields( (string) $starter_args['source'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper escapes every attribute. ?>

		<p class="lead-form__error" role="alert"></p>
		<button class="btn btn--primary btn--block lead-form__submit" type="submit"><?php echo esc_html( (string) $starter_args['submit_label'] ); ?></button>
	</div>

	<div class="lead-form__done" role="status" tabindex="-1">
		<span class="lead-form__done-icon"><?php starter_the_icon( 'check', 28 ); ?></span>
		<p class="lead-form__title"><?php echo esc_html( (string) $starter_args['done_title'] ); ?></p>
		<p class="lead-form__text"><?php echo esc_html( (string) $starter_args['done_text'] ); ?></p>
	</div>
</form>
