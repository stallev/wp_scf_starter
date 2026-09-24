<?php
/**
 * SCF group: portfolio project (starter_project).
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register group_starter_project.
 */
function starter_register_project_fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_starter_project',
			'title'    => __( 'Проект', 'starter' ),
			'fields'   => array(
				starter_scf_field( 'starter_project_location', __( 'Локация', 'starter' ), 'text', array( 'wrapper' => starter_scf_width( 50 ) ) ),
				starter_scf_field( 'starter_project_date_label', __( 'Дата на сайте', 'starter' ), 'text', array( 'wrapper' => starter_scf_width( 50 ) ) ),
				starter_scf_field(
					'starter_project_service',
					__( 'Услуга', 'starter' ),
					'text',
					array(
						'instructions' => __( 'Ключ услуги для фильтра (slug страницы услуги).', 'starter' ),
						'wrapper'      => starter_scf_width( 50 ),
					)
				),
				starter_scf_field(
					'starter_project_gallery',
					__( 'Галерея', 'starter' ),
					'gallery',
					array(
						'return_format' => 'id',
						'preview_size'  => 'medium',
					)
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'starter_project',
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'starter_register_project_fields' );
