<?php
/**
 * SCF group: company / NAP on the `starter-company` options page.
 *
 * Read only via starter_get_company() (helpers.php), never get_field() in templates.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register group_starter_company.
 */
function starter_register_company_fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	$g = 'starter_company';

	acf_add_local_field_group(
		array(
			'key'      => 'group_starter_company',
			'title'    => __( 'Компания', 'starter' ),
			'fields'   => array(
				starter_scf_tab( $g, 'main', __( 'Основное', 'starter' ) ),
				starter_scf_field( 'starter_company_name', __( 'Название (бренд)', 'starter' ), 'text', array( 'wrapper' => starter_scf_width( 50 ) ) ),
				starter_scf_field( 'starter_company_legal_name', __( 'Юридическое название', 'starter' ), 'text', array( 'wrapper' => starter_scf_width( 50 ) ) ),
				starter_scf_field(
					'starter_company_description',
					__( 'Краткое описание', 'starter' ),
					'textarea',
					array(
						'rows'         => 3,
						'instructions' => __( 'Одно-два предложения: чем занимается компания. Используется в schema.org и llms.txt.', 'starter' ),
					)
				),
				starter_scf_field(
					'starter_company_business_type',
					__( 'Тип бизнеса (schema.org)', 'starter' ),
					'text',
					array(
						'default_value' => 'LocalBusiness',
						'instructions'  => __( 'Подтип LocalBusiness, например HomeAndConstructionBusiness, ProfessionalService.', 'starter' ),
						'wrapper'       => starter_scf_width( 50 ),
					)
				),
				starter_scf_field(
					'starter_company_price_range',
					__( 'Диапазон цен', 'starter' ),
					'text',
					array(
						'instructions' => __( 'schema.org priceRange, например «$$» или «от 100».', 'starter' ),
						'wrapper'      => starter_scf_width( 50 ),
					)
				),
				starter_scf_field(
					'starter_company_default_image',
					__( 'Изображение по умолчанию', 'starter' ),
					'image',
					array(
						'return_format' => 'id',
						'preview_size'  => 'medium',
						'instructions'  => __( 'Фолбэк для карточек без миниатюры и для schema.org image.', 'starter' ),
					)
				),

				starter_scf_tab( $g, 'contacts', __( 'Контакты', 'starter' ) ),
				starter_scf_field(
					'starter_company_phones',
					__( 'Телефоны', 'starter' ),
					'repeater',
					array(
						'layout'       => 'table',
						'button_label' => __( 'Добавить телефон', 'starter' ),
						'instructions' => __( 'Первый телефон — основной (шапка, schema.org).', 'starter' ),
						'sub_fields'   => array(
							starter_scf_sub_field( 'starter_company_phones', 'number', __( 'Номер', 'starter' ), 'text', array( 'required' => 1 ) ),
							starter_scf_sub_field( 'starter_company_phones', 'label', __( 'Подпись', 'starter' ) ),
						),
					)
				),
				starter_scf_field( 'starter_company_email', __( 'E-mail', 'starter' ), 'email', array( 'wrapper' => starter_scf_width( 50 ) ) ),
				starter_scf_field(
					'starter_company_socials',
					__( 'Мессенджеры и соцсети', 'starter' ),
					'repeater',
					array(
						'layout'       => 'table',
						'button_label' => __( 'Добавить ссылку', 'starter' ),
						'sub_fields'   => array(
							starter_scf_sub_field(
								'starter_company_socials',
								'network',
								__( 'Сеть', 'starter' ),
								'select',
								array(
									'choices'  => starter_social_networks(),
									'required' => 1,
								)
							),
							starter_scf_sub_field(
								'starter_company_socials',
								'url',
								__( 'Ссылка', 'starter' ),
								'text',
								array(
									'required'     => 1,
									'instructions' => __( 'https://…, tg:// или viber:// ссылка', 'starter' ),
								)
							),
							starter_scf_sub_field( 'starter_company_socials', 'label', __( 'Подпись', 'starter' ) ),
						),
					)
				),

				starter_scf_tab( $g, 'address', __( 'Адрес', 'starter' ) ),
				starter_scf_field( 'starter_company_street', __( 'Улица, дом', 'starter' ), 'text', array( 'wrapper' => starter_scf_width( 50 ) ) ),
				starter_scf_field( 'starter_company_locality', __( 'Город', 'starter' ), 'text', array( 'wrapper' => starter_scf_width( 50 ) ) ),
				starter_scf_field( 'starter_company_region', __( 'Регион', 'starter' ), 'text', array( 'wrapper' => starter_scf_width( 50 ) ) ),
				starter_scf_field( 'starter_company_postal_code', __( 'Индекс', 'starter' ), 'text', array( 'wrapper' => starter_scf_width( 25 ) ) ),
				starter_scf_field(
					'starter_company_country',
					__( 'Страна (ISO 3166-1)', 'starter' ),
					'text',
					array(
						'maxlength' => 2,
						'wrapper'   => starter_scf_width( 25 ),
					)
				),
				starter_scf_field(
					'starter_company_address_text',
					__( 'Адрес одной строкой', 'starter' ),
					'textarea',
					array(
						'rows'         => 2,
						'instructions' => __( 'Для вывода на сайте; пусто — собирается из полей выше.', 'starter' ),
					)
				),
				starter_scf_field(
					'starter_company_geo_lat',
					__( 'Широта', 'starter' ),
					'number',
					array(
						'step'    => 'any',
						'wrapper' => starter_scf_width( 25 ),
					)
				),
				starter_scf_field(
					'starter_company_geo_lng',
					__( 'Долгота', 'starter' ),
					'number',
					array(
						'step'    => 'any',
						'wrapper' => starter_scf_width( 25 ),
					)
				),
				starter_scf_field(
					'starter_company_area_served',
					__( 'Зона обслуживания', 'starter' ),
					'textarea',
					array(
						'rows'         => 4,
						'instructions' => __( 'По одному населённому пункту или региону в строке (schema.org areaServed).', 'starter' ),
					)
				),

				starter_scf_tab( $g, 'hours', __( 'Часы работы', 'starter' ) ),
				starter_scf_field(
					'starter_company_hours_text',
					__( 'Часы работы (текст)', 'starter' ),
					'text',
					array( 'instructions' => __( 'Для вывода на сайте, например «Пн–Пт 9:00–18:00».', 'starter' ) )
				),
				starter_scf_field(
					'starter_company_opening_hours',
					__( 'Часы работы (schema.org)', 'starter' ),
					'repeater',
					array(
						'layout'       => 'table',
						'button_label' => __( 'Добавить интервал', 'starter' ),
						'sub_fields'   => array(
							starter_scf_sub_field(
								'starter_company_opening_hours',
								'days',
								__( 'Дни', 'starter' ),
								'checkbox',
								array(
									'choices' => starter_week_days(),
									'layout'  => 'horizontal',
								)
							),
							starter_scf_sub_field( 'starter_company_opening_hours', 'opens', __( 'С (HH:MM)', 'starter' ), 'text', array( 'placeholder' => '09:00' ) ),
							starter_scf_sub_field( 'starter_company_opening_hours', 'closes', __( 'До (HH:MM)', 'starter' ), 'text', array( 'placeholder' => '18:00' ) ),
						),
					)
				),

				starter_scf_tab( $g, 'analytics', __( 'Аналитика', 'starter' ) ),
				starter_scf_field(
					'starter_company_ga4_id',
					__( 'GA4 Measurement ID', 'starter' ),
					'text',
					array(
						'placeholder'  => 'G-XXXXXXXXXX',
						'instructions' => __( 'Пусто — аналитика выключена.', 'starter' ),
						'wrapper'      => starter_scf_width( 50 ),
					)
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => STARTER_COMPANY_OPTIONS_SLUG,
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'starter_register_company_fields' );
