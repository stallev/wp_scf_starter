<?php
/**
 * Post author: byline under the title or a card after the text.
 *
 * Args:
 * - variant (string) byline | card (default).
 * - reveal (bool)    Card only, default true.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

$starter_variant = isset( $args['variant'] ) && 'byline' === $args['variant'] ? 'byline' : 'card';
$starter_reveal  = ! isset( $args['reveal'] ) || (bool) $args['reveal'];
$starter_author  = starter_get_post_author_data();
if ( null === $starter_author ) {
	return;
}

if ( 'byline' === $starter_variant ) :
	?>
	<p class="post-author post-author--byline">
		<span class="post-author__name"><?php echo esc_html( $starter_author['name'] ); ?></span>
		<?php if ( '' !== $starter_author['instagram_url'] ) : ?>
			<span class="post-author__sep" aria-hidden="true">·</span>
			<a class="post-author__ig" href="<?php echo esc_url( $starter_author['instagram_url'] ); ?>" target="_blank" rel="noopener noreferrer">
				<?php starter_the_icon( 'instagram', 16 ); ?>
				<span><?php echo esc_html( $starter_author['instagram_label'] ); ?></span>
			</a>
		<?php endif; ?>
	</p>
	<?php
	return;
endif;
?>
<aside class="post-author post-author--card<?php echo $starter_reveal ? ' reveal' : ''; ?>" aria-label="<?php esc_attr_e( 'Автор статьи', 'starter' ); ?>">
	<div class="post-author__media" aria-hidden="true">
		<?php if ( '' !== $starter_author['avatar_html'] ) : ?>
			<?php echo wp_kses_post( $starter_author['avatar_html'] ); ?>
		<?php else : ?>
			<span class="post-author__monogram"><?php echo esc_html( $starter_author['initials'] ); ?></span>
		<?php endif; ?>
	</div>
	<div class="post-author__body">
		<p class="post-author__label"><?php esc_html_e( 'Автор', 'starter' ); ?></p>
		<p class="post-author__name"><?php echo esc_html( $starter_author['name'] ); ?></p>
		<?php if ( '' !== $starter_author['instagram_url'] ) : ?>
			<a class="post-author__ig" href="<?php echo esc_url( $starter_author['instagram_url'] ); ?>" target="_blank" rel="noopener noreferrer">
				<?php starter_the_icon( 'instagram', 16 ); ?>
				<span><?php echo esc_html( $starter_author['instagram_label'] ); ?></span>
			</a>
		<?php endif; ?>
	</div>
</aside>
