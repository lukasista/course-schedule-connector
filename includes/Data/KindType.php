<?php
/**
 * The kind-of-course post type.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the post type a kind of course has its own page made of.
 *
 * A kind — "Gymnastika", "Jojo přípravka", "Lezení" — is a page on the website
 * this replaces: a heading, a paragraph or two about what the training is, a
 * photograph, and the table of every course of that kind. The taxonomy can hold
 * the grouping but it cannot hold any of the rest: a term has a name and a
 * description in a plain-text box, and nothing a designer or an editor would
 * want to work in.
 *
 * So a kind is both, exactly as a trainer is. {@see PostType::KIND} stays what
 * a course is filed under and what a display set filters by; this holds what
 * somebody writes. The two are tied by the same normalised name the rest of the
 * plugin matches on, and the synchronisation writes only the name — everything
 * written here is left alone by it, permanently.
 */
final class KindType {

	/**
	 * Post type name.
	 *
	 * It may not be `cscs_kind`: that belongs to the taxonomy, and a post type
	 * sharing the name would collide with it over the query variable.
	 */
	public const KIND = 'cscs_kind_page';

	/**
	 * The normalised name this page is matched by. The pairing key.
	 */
	public const META_KEY_NAME = '_cscs_kind_key';

	/**
	 * Registers the post type and the field that pairs it with its term.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_post_type(
			self::KIND,
			array(
				'labels'             => array(
					'name'               => __( 'Kinds of course', 'course-schedule-connector' ),
					'singular_name'      => __( 'Kind of course', 'course-schedule-connector' ),
					'menu_name'          => __( 'Kinds of course', 'course-schedule-connector' ),
					'add_new_item'       => __( 'Add a kind of course', 'course-schedule-connector' ),
					'edit_item'          => __( 'Edit kind of course', 'course-schedule-connector' ),
					'new_item'           => __( 'New kind of course', 'course-schedule-connector' ),
					'view_item'          => __( 'View kind of course', 'course-schedule-connector' ),
					'search_items'       => __( 'Search kinds of course', 'course-schedule-connector' ),
					'not_found'          => __( 'No kinds of course found.', 'course-schedule-connector' ),
					'not_found_in_trash' => __( 'No kinds of course in the bin.', 'course-schedule-connector' ),
				),
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => false,
				'show_in_rest'       => true,
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_icon'          => 'dashicons-category',
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
				'capability_type'    => 'post',
				'map_meta_cap'       => true,
				/**
				 * Filters the URL slug of a kind of course.
				 *
				 * Czech, because the site is Czech and a visitor reads the
				 * address. Changing it means raising {@see \CSCS\Plugin::REWRITE_VERSION},
				 * or the rewrite rules are never rebuilt and every such address
				 * answers with a 404.
				 *
				 * @since 0.6.0
				 *
				 * @param string $slug Rewrite slug.
				 */
				'rewrite'            => array( 'slug' => apply_filters( 'cscs_kind_rewrite_slug', 'druh' ) ),
			)
		);

		register_post_meta(
			self::KIND,
			self::META_KEY_NAME,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static function (): bool {
					return current_user_can( 'cscs_manage_content' );
				},
			)
		);
	}
}
