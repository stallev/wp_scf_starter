<?php
/**
 * Click-to-load facade for heavy embeds (map / video / widget).
 *
 * A live third-party iframe costs hundreds of KB and main-thread time on every visit. The page
 * renders a light placeholder that keeps the final box (aspect-ratio → no layout shift); main.js
 * swaps in the iframe from data-src only after a click. No images, no third-party requests.
 *
 * Args:
 * - src (string)        Iframe URL (required; https).
 * - title (string)      Iframe title / placeholder caption (required for a11y).
 * - kind (string)       map | video | widget (icon + defaults). Default map.
 * - ratio (string)      16x9 | 4x3 | 1x1. Default: video 16x9, otherwise 4x3.
 * - link (string)       Fallback URL opened in a new tab (e.g. the map on the provider site).
 * - link_label (string) Fallback link text.
 * - button (string)     Button text.
 * - allow (string)      Iframe `allow` attribute (video: autoplay; fullscreen; …).
 * - reveal (bool)       Default true; pass false when the facade is on the first screen.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

$starter_args = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'src'        => '',
		'title'      => '',
		'kind'       => 'map',
		'ratio'      => '',
		'link'       => '',
		'link_label' => __( 'Открыть в новой вкладке', 'starter' ),
		'button'     => '',
		'allow'      => '',
		'reveal'     => true,
	)
);

$starter_src = esc_url( (string) $starter_args['src'], array( 'https' ) );
if ( '' === $starter_src || '' === (string) $starter_args['title'] ) {
	return;
}

$starter_kind  = in_array( $starter_args['kind'], array( 'map', 'video', 'widget' ), true ) ? (string) $starter_args['kind'] : 'widget';
$starter_ratio = in_array( $starter_args['ratio'], array( '16x9', '4x3', '1x1' ), true ) ? (string) $starter_args['ratio'] : ( 'video' === $starter_kind ? '16x9' : '4x3' );
$starter_icons = array(
	'map'    => 'pin',
	'video'  => 'play',
	'widget' => 'link',
);
$starter_btn   = (string) $starter_args['button'];
if ( '' === $starter_btn ) {
	$starter_btn = 'map' === $starter_kind ? __( 'Показать карту', 'starter' ) : ( 'video' === $starter_kind ? __( 'Смотреть видео', 'starter' ) : __( 'Загрузить', 'starter' ) );
}
$starter_allow = (string) $starter_args['allow'];
if ( '' === $starter_allow && 'video' === $starter_kind ) {
	$starter_allow = 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; fullscreen';
}

$starter_classes = sprintf( 'embed-facade embed-facade--%s embed-facade--%s', $starter_kind, $starter_ratio ) . ( $starter_args['reveal'] ? ' reveal' : '' );
?>
<div class="<?php echo esc_attr( $starter_classes ); ?>" data-src="<?php echo esc_attr( $starter_src ); ?>" data-title="<?php echo esc_attr( (string) $starter_args['title'] ); ?>"<?php echo '' !== $starter_allow ? ' data-allow="' . esc_attr( $starter_allow ) . '"' : ''; ?>>
	<div class="embed-facade__placeholder">
		<span class="embed-facade__icon"><?php starter_the_icon( $starter_icons[ $starter_kind ], 40 ); ?></span>
		<p class="embed-facade__title"><?php echo esc_html( (string) $starter_args['title'] ); ?></p>
		<button class="btn btn--outline embed-facade__btn" type="button"><?php echo esc_html( $starter_btn ); ?></button>
		<?php if ( '' !== (string) $starter_args['link'] ) : ?>
			<a class="embed-facade__link" href="<?php echo esc_url( (string) $starter_args['link'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) $starter_args['link_label'] ); ?></a>
		<?php endif; ?>
	</div>
</div>
