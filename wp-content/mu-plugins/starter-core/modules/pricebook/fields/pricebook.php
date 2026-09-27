<?php
/**
 * SCF group: pricebook items on the `starter-pricebook` options page.
 *
 * Read only via starter_get_pricebook() / starter_price(), never get_field() with a key.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register group_starter_pricebook.
 */
function starter_register_pricebook_fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_starter_pricebook',
			'title'    => __( 'Прайс-лист', 'starter' ),
			'fields'   => array(
				starter_scf_field(
					'starter_pricebook_items',
					__( 'Позиции', 'starter' ),
					'repeater',
					array(
						'layout'       => 'table',
						'button_label' => __( 'Добавить позицию', 'starter' ),
						'instructions' => __( 'Формулы — в коде (starter_pricebook_calc_total()), здесь — только числа.', 'starter' ),
						'sub_fields'   => array(
							starter_scf_sub_field(
								'starter_pricebook_items',
								'key',
								__( 'Ключ', 'starter' ),
								'text',
								array(
									'required'     => 1,
									'instructions' => __( 'Латиница/цифры/подчёркивание, например starter_demo_unit.', 'starter' ),
								)
							),
							starter_scf_sub_field( 'starter_pricebook_items', 'label', __( 'Название', 'starter' ), 'text', array( 'required' => 1 ) ),
							starter_scf_sub_field( 'starter_pricebook_items', 'value', __( 'Значение', 'starter' ), 'number', array( 'step' => 'any' ) ),
							starter_scf_sub_field( 'starter_pricebook_items', 'unit', __( 'Единица', 'starter' ) ),
							starter_scf_sub_field( 'starter_pricebook_items', 'note', __( 'Примечание', 'starter' ) ),
						),
					)
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => STARTER_PRICEBOOK_OPTIONS_SLUG,
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'starter_register_pricebook_fields' );
