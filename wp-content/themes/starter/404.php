<?php
/**
 * 404: short message, link home, search. No lead form, no images.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="site-main">
	<?php
	get_template_part(
		'template-parts/page-head',
		null,
		array(
			'title'  => __( 'Страница не найдена', 'starter' ),
			'lead'   => __( 'Возможно, адрес изменился или страница была удалена.', 'starter' ),
			'crumbs' => false,
		)
	);
	?>
	<section class="section section--tight">
		<div class="container container--narrow">
			<p><a class="btn btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'На главную', 'starter' ); ?></a></p>
			<?php get_search_form(); ?>
		</div>
	</section>
</main>
<?php
get_footer();
