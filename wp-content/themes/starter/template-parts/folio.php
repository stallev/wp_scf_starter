<?php
/**
 * Portfolio grid from CPT starter_project (starter_get_projects()) with the lightbox. Renders
 * nothing without items.
 *
 * Args:
 * - limit (int)           Max items, -1 = all (default 6).
 * - service (string)      Filter by starter_project_service (service page slug).
 * - title (string)        Section heading ('' = none).
 * - reveal (bool)         Default true.
 * - priority_first (bool) First tile is the page LCP (portfolio page on the first screen):
 *                         eager + fetchpriority=high, no .reveal. Default false.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

$starter_args = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'limit'          => 6,
		'service'        => '',
		'title'          => __( 'Наши работы', 'starter' ),
		'reveal'         => true,
		'priority_first' => false,
	)
);

$starter_projects = starter_get_projects(
	array(
		'limit'   => (int) $starter_args['limit'],
		'service' => (string) $starter_args['service'],
	)
);
if ( ! $starter_projects ) {
	return;
}

$starter_card  = starter_card_size();
$starter_sizes = '(max-width: 599px) 100vw, (max-width: 1023px) 50vw, 33vw';
?>
<section class="section folio" id="projects"<?php echo '' !== (string) $starter_args['title'] ? ' aria-labelledby="projects-title"' : ''; ?>>
	<div class="container">
		<?php if ( '' !== (string) $starter_args['title'] ) : ?>
			<div class="section__head<?php echo $starter_args['reveal'] ? ' reveal' : ''; ?>">
				<h2 class="section__title" id="projects-title"><?php echo esc_html( (string) $starter_args['title'] ); ?></h2>
			</div>
		<?php endif; ?>
		<div class="folio__grid">
			<?php foreach ( $starter_projects as $starter_index => $starter_project ) : ?>
				<?php
				$starter_priority = $starter_args['priority_first'] && 0 === $starter_index;
				$starter_thumb    = (int) get_post_thumbnail_id( $starter_project );
				$starter_thumb    = $starter_thumb > 0 ? $starter_thumb : starter_get_default_image_id();
				$starter_what     = '' !== $starter_project->post_excerpt ? $starter_project->post_excerpt : get_the_title( $starter_project );
				$starter_place    = implode(
					' · ',
					array_filter(
						array(
							trim( (string) starter_field( 'starter_project_location', $starter_project->ID ) ),
							trim( (string) starter_field( 'starter_project_date_label', $starter_project->ID ) ),
						)
					)
				);
				$starter_classes  = 'folio__item ' . starter_folio_orientation_class( $starter_thumb ) . ( $starter_args['reveal'] && ! $starter_priority ? ' reveal' : '' );
				$starter_large    = $starter_thumb > 0 ? (string) wp_get_attachment_image_url( $starter_thumb, 'large' ) : '';
				?>
				<figure class="<?php echo esc_attr( $starter_classes ); ?>">
					<?php if ( '' !== $starter_large ) : ?>
						<button class="folio__open" type="button"
							data-lightbox
							data-lightbox-group="projects"
							data-lightbox-src="<?php echo esc_url( $starter_large ); ?>"
							data-lightbox-srcset="<?php echo esc_attr( (string) wp_get_attachment_image_srcset( $starter_thumb, 'large' ) ); ?>"
							data-lightbox-title="<?php echo esc_attr( get_the_title( $starter_project ) ); ?>"
							data-lightbox-desc="<?php echo esc_attr( $starter_place ); ?>"
							<?php /* translators: %s: project title. */ ?>
							aria-label="<?php echo esc_attr( sprintf( __( 'Открыть фото: %s', 'starter' ), get_the_title( $starter_project ) ) ); ?>">
							<?php
							starter_the_image(
								$starter_thumb,
								$starter_card['name'],
								array(
									'priority' => $starter_priority,
									'alt'      => '',
									'class'    => 'folio__img',
									'sizes'    => $starter_sizes,
								)
							);
							?>
						</button>
					<?php endif; ?>
					<figcaption class="folio__cap">
						<span class="folio__title"><?php echo esc_html( get_the_title( $starter_project ) ); ?></span>
						<?php if ( '' !== $starter_place ) : ?>
							<span class="folio__place"><?php echo esc_html( $starter_place ); ?></span>
						<?php endif; ?>
						<?php if ( get_the_title( $starter_project ) !== $starter_what ) : ?>
							<span class="folio__what"><?php echo esc_html( $starter_what ); ?></span>
						<?php endif; ?>
					</figcaption>
				</figure>
			<?php endforeach; ?>
		</div>
	</div>
</section>
