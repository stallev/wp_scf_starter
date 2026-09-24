<?php
/**
 * SCF group: review (starter_review). Review text = post content.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register group_starter_review.
 */
function starter_register_review_fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_starter_review',
			'title'    => __( 'Отзыв', 'starter' ),
			'fields'   => array(
				starter_scf_field( 'starter_review_author', __( 'Автор', 'starter' ), 'text', array( 'wrapper' => starter_scf_width( 50 ) ) ),
				starter_scf_field( 'starter_review_location', __( 'Локация', 'starter' ), 'text', array( 'wrapper' => starter_scf_width( 50 ) ) ),
				starter_scf_field( 'starter_review_service', __( 'Услуга', 'starter' ), 'text', array( 'wrapper' => starter_scf_width( 50 ) ) ),
				starter_scf_field(
					'starter_review_date_label',
					__( 'Дата на сайте', 'starter' ),
					'text',
					array(
						'instructions' => __( 'Как показывать дату, например «май 2026».', 'starter' ),
						'wrapper'      => starter_scf_width( 25 ),
					)
				),
				starter_scf_field(
					'starter_review_rating',
					__( 'Оценка', 'starter' ),
					'number',
					array(
						'default_value' => 5,
						'min'           => 1,
						'max'           => 5,
						'wrapper'       => starter_scf_width( 25 ),
					)
				),
				starter_scf_field(
					'starter_review_source_url',
					__( 'Ссылка на оригинал', 'starter' ),
					'url',
					array( 'instructions' => __( 'Карты, агрегатор отзывов — если отзыв опубликован там.', 'starter' ) )
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'starter_review',
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'starter_register_review_fields' );
