<?php
/**
 * SCF group: catalog product (price, unit, sku, characteristics).
 *
 * Read only via starter_field() / starter_get_products(), never get_field() with a key.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register group_starter_product.
 */
function starter_register_product_fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_starter_product',
			'title'    => __( 'Товар', 'starter' ),
			'fields'   => array(
				starter_scf_field(
					'starter_product_price',
					__( 'Цена', 'starter' ),
					'number',
					array(
						'step'    => 'any',
						'wrapper' => starter_scf_width( 33 ),
					)
				),
				starter_scf_field(
					'starter_product_unit',
					__( 'Единица измерения', 'starter' ),
					'text',
					array(
						'placeholder' => __( 'шт', 'starter' ),
						'wrapper'     => starter_scf_width( 33 ),
					)
				),
				starter_scf_field(
					'starter_product_sku',
					__( 'Артикул', 'starter' ),
					'text',
					array( 'wrapper' => starter_scf_width( 34 ) )
				),
				starter_scf_field(
					'starter_product_characteristics',
					__( 'Характеристики', 'starter' ),
					'repeater',
					array(
						'layout'       => 'table',
						'button_label' => __( 'Добавить характеристику', 'starter' ),
						'instructions' => __( 'Короткий список пар «параметр — значение» под описанием товара.', 'starter' ),
						'sub_fields'   => array(
							starter_scf_sub_field( 'starter_product_characteristics', 'label', __( 'Параметр', 'starter' ) ),
							starter_scf_sub_field( 'starter_product_characteristics', 'value', __( 'Значение', 'starter' ) ),
						),
					)
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'starter_product',
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'starter_register_product_fields' );
