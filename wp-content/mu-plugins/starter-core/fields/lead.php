<?php
/**
 * SCF group: lead (starter_lead). Values are written by forms.php.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register group_starter_lead.
 */
function starter_register_lead_fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_starter_lead',
			'title'    => __( 'Заявка', 'starter' ),
			'position' => 'acf_after_title',
			'fields'   => array(
				starter_scf_field(
					'starter_lead_status',
					__( 'Статус', 'starter' ),
					'select',
					array(
						'choices'       => starter_lead_statuses(),
						'default_value' => 'new',
						'wrapper'       => starter_scf_width( 25 ),
					)
				),
				starter_scf_field( 'starter_lead_name', __( 'Имя', 'starter' ), 'text', array( 'wrapper' => starter_scf_width( 25 ) ) ),
				starter_scf_field( 'starter_lead_contact', __( 'Контакт', 'starter' ), 'text', array( 'wrapper' => starter_scf_width( 50 ) ) ),
				starter_scf_field( 'starter_lead_service', __( 'Услуга', 'starter' ), 'text', array( 'wrapper' => starter_scf_width( 50 ) ) ),
				starter_scf_field(
					'starter_lead_source',
					__( 'Источник (страница/форма)', 'starter' ),
					'text',
					array( 'wrapper' => starter_scf_width( 50 ) )
				),
				starter_scf_field(
					'starter_lead_consent',
					__( 'Согласие на обработку ПДн', 'starter' ),
					'true_false',
					array(
						'ui'      => 1,
						'wrapper' => starter_scf_width( 50 ),
					)
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'starter_lead',
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'starter_register_lead_fields' );
