<?php
/**
 * SCF group: service card on pages (grid of services, e.g. on the front page).
 *
 * A page appears in starter_get_service_card_pages() when the card is enabled.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register group_starter_service_card.
 */
function starter_register_service_card_fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_starter_service_card',
			'title'    => __( 'Карточка услуги', 'starter' ),
			'position' => 'side',
			'fields'   => array(
				starter_scf_field(
					'starter_service_card_enabled',
					__( 'Показывать в сетке услуг', 'starter' ),
					'true_false',
					array( 'ui' => 1 )
				),
				starter_scf_field(
					'starter_service_card_title',
					__( 'Заголовок карточки', 'starter' ),
					'text',
					array( 'instructions' => __( 'Пусто — заголовок страницы.', 'starter' ) )
				),
				starter_scf_field( 'starter_service_card_text', __( 'Описание', 'starter' ), 'textarea', array( 'rows' => 4 ) ),
				starter_scf_field(
					'starter_service_card_price',
					__( 'Цена (отображение)', 'starter' ),
					'text',
					array( 'instructions' => __( 'Витринная строка, например «от 100».', 'starter' ) )
				),
				starter_scf_field( 'starter_service_card_price_note', __( 'Примечание к цене', 'starter' ) ),
				starter_scf_field( 'starter_service_card_badge', __( 'Бейдж', 'starter' ) ),
				starter_scf_field(
					'starter_service_card_image',
					__( 'Изображение', 'starter' ),
					'image',
					array(
						'return_format' => 'id',
						'preview_size'  => 'thumbnail',
						'instructions'  => __( 'Пусто — миниатюра страницы, затем изображение по умолчанию из «Компании».', 'starter' ),
					)
				),
				starter_scf_field(
					'starter_service_card_order',
					__( 'Порядок', 'starter' ),
					'number',
					array( 'default_value' => 10 )
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'page',
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'starter_register_service_card_fields' );
