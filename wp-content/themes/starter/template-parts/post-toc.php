<?php
/**
 * Post table of contents (H2/H3 with anchors), rendered by starter_render_post_toc().
 *
 * Args: items — list of { id, text, level }.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

$starter_items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
if ( count( $starter_items ) < 2 ) {
	return;
}
?>
<aside class="post-layout__toc">
	<details class="post-toc" open>
		<summary class="post-toc__summary"><?php esc_html_e( 'Содержание', 'starter' ); ?></summary>
		<nav aria-label="<?php esc_attr_e( 'Содержание статьи', 'starter' ); ?>">
			<ol class="post-toc__list">
				<?php foreach ( $starter_items as $starter_item ) : ?>
					<?php
					if ( empty( $starter_item['id'] ) || empty( $starter_item['text'] ) ) {
						continue;
					}
					?>
					<li class="post-toc__item post-toc__item--h<?php echo 3 === (int) ( $starter_item['level'] ?? 2 ) ? '3' : '2'; ?>">
						<a class="post-toc__link" href="#<?php echo esc_attr( (string) $starter_item['id'] ); ?>"><?php echo esc_html( (string) $starter_item['text'] ); ?></a>
					</li>
				<?php endforeach; ?>
			</ol>
		</nav>
	</details>
</aside>
