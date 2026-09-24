<?php
/**
 * Generic custom post types: leads, reviews, projects, FAQ.
 *
 * Business CPT (catalog products, …) live in optional modules. Every CPT's args pass through the
 * `starter_post_type_args` filter, so a project can change slugs or visibility without editing core.
 *
 * @package Starter_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * CPT definitions (post type => register_post_type args).
 *
 * @return array<string, array<string, mixed>>
 */
function starter_post_type_definitions(): array {
	return array(
		// Leads are private data: no front end, no REST, no search.
		'starter_lead'    => array(
			'labels'              => array(
				'name'               => __( 'Заявки', 'starter' ),
				'singular_name'      => __( 'Заявка', 'starter' ),
				'edit_item'          => __( 'Просмотреть заявку', 'starter' ),
				'menu_name'          => __( 'Заявки', 'starter' ),
				'search_items'       => __( 'Искать заявки', 'starter' ),
				'not_found'          => __( 'Заявок не найдено', 'starter' ),
				'not_found_in_trash' => __( 'В корзине заявок нет', 'starter' ),
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'menu_position'       => 25,
			'menu_icon'           => 'dashicons-email-alt',
			'supports'            => array( 'title', 'editor' ),
			// Personal data: only Editors and Admins (edit_others_posts) see leads; nobody adds them by hand.
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'capabilities'        => array(
				'create_posts'  => 'do_not_allow',
				'edit_posts'    => 'edit_others_posts',
				'delete_posts'  => 'delete_others_posts',
				'publish_posts' => 'do_not_allow',
			),
			'delete_with_user'    => false,
		),
		// Reviews and FAQ are rendered inside pages; singles would be thin duplicate content.
		'starter_review'  => array(
			'labels'             => array(
				'name'          => __( 'Отзывы', 'starter' ),
				'singular_name' => __( 'Отзыв', 'starter' ),
				'add_new_item'  => __( 'Добавить отзыв', 'starter' ),
				'edit_item'     => __( 'Редактировать отзыв', 'starter' ),
				'menu_name'     => __( 'Отзывы', 'starter' ),
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_rest'       => true,
			'has_archive'        => false,
			'rewrite'            => false,
			'menu_position'      => 26,
			'menu_icon'          => 'dashicons-star-filled',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
		),
		'starter_project' => array(
			'labels'        => array(
				'name'          => __( 'Проекты', 'starter' ),
				'singular_name' => __( 'Проект', 'starter' ),
				'add_new_item'  => __( 'Добавить проект', 'starter' ),
				'edit_item'     => __( 'Редактировать проект', 'starter' ),
				'menu_name'     => __( 'Портфолио', 'starter' ),
			),
			'public'        => true,
			'show_in_rest'  => true,
			'has_archive'   => false,
			'menu_position' => 27,
			'menu_icon'     => 'dashicons-portfolio',
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
			'rewrite'       => array(
				'slug'       => 'projects',
				'with_front' => false,
			),
		),
		'starter_faq'     => array(
			'labels'             => array(
				'name'          => __( 'FAQ', 'starter' ),
				'singular_name' => __( 'Вопрос', 'starter' ),
				'add_new_item'  => __( 'Добавить вопрос', 'starter' ),
				'edit_item'     => __( 'Редактировать вопрос', 'starter' ),
				'menu_name'     => __( 'FAQ', 'starter' ),
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_rest'       => true,
			'has_archive'        => false,
			'rewrite'            => false,
			'menu_position'      => 28,
			'menu_icon'          => 'dashicons-editor-help',
			'supports'           => array( 'title', 'editor' ),
		),
	);
}

/**
 * Register the CPT.
 */
function starter_register_post_types(): void {
	foreach ( starter_post_type_definitions() as $post_type => $args ) {
		/**
		 * Filter CPT args before registration.
		 *
		 * @param array<string, mixed> $args      register_post_type() args.
		 * @param string               $post_type Post type key.
		 */
		$args = (array) apply_filters( 'starter_post_type_args', $args, $post_type );
		register_post_type( $post_type, $args );
	}
}
add_action( 'init', 'starter_register_post_types' );
