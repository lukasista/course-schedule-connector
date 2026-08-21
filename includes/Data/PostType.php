<?php
/**
 * Course post type and taxonomies.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the course post type and the taxonomies derived from the API.
 *
 * Courses live in the post table because they have editorial value: an
 * administrator adds a real photograph, a longer description and search engine
 * metadata that the remote system will never hold. Everything the API owns is
 * kept in meta alongside, so a synchronisation can refresh the facts without
 * touching the words somebody wrote.
 */
final class PostType {

	/**
	 * Post type name.
	 */
	public const COURSE = 'cscs_course';

	/**
	 * Room taxonomy. Multi-valued: a course can run in more than one room.
	 */
	public const ROOM = 'cscs_room';

	/**
	 * Trainer taxonomy.
	 */
	public const TRAINER = 'cscs_trainer';

	/**
	 * Activity type taxonomy.
	 */
	public const ACTIVITY = 'cscs_activity';

	/**
	 * Tag taxonomy, mirroring the tags the API attaches to a course.
	 */
	public const TAG = 'cscs_tag';

	/**
	 * Registers the post type and taxonomies.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_post_type(
			self::COURSE,
			array(
				'labels'             => array(
					'name'          => __( 'Courses', 'course-schedule-connector' ),
					'singular_name' => __( 'Course', 'course-schedule-connector' ),
					'menu_name'     => __( 'Courses', 'course-schedule-connector' ),
					'search_items'  => __( 'Search courses', 'course-schedule-connector' ),
					'not_found'     => __( 'No courses found.', 'course-schedule-connector' ),
					'edit_item'     => __( 'Edit course', 'course-schedule-connector' ),
				),
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => false,
				'show_in_rest'       => true,
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_icon'          => 'dashicons-calendar-alt',
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
				'capability_type'    => 'post',
				'map_meta_cap'       => true,
				/**
				 * Filters the URL slug of a course.
				 *
				 * @since 0.2.0
				 *
				 * @param string $slug Rewrite slug.
				 */
				'rewrite'            => array( 'slug' => apply_filters( 'cscs_course_rewrite_slug', 'course' ) ),
			)
		);

		foreach ( self::taxonomies() as $taxonomy => $labels ) {
			register_taxonomy(
				$taxonomy,
				self::COURSE,
				array(
					'labels'            => $labels,
					'public'            => true,
					'hierarchical'      => false,
					'show_admin_column' => true,
					'show_in_rest'      => true,
					'rewrite'           => false,
				)
			);
		}
	}

	/**
	 * Returns the taxonomies and their labels.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function taxonomies(): array {
		return array(
			self::ROOM     => array(
				'name'          => __( 'Rooms', 'course-schedule-connector' ),
				'singular_name' => __( 'Room', 'course-schedule-connector' ),
			),
			self::TRAINER  => array(
				'name'          => __( 'Trainers', 'course-schedule-connector' ),
				'singular_name' => __( 'Trainer', 'course-schedule-connector' ),
			),
			self::ACTIVITY => array(
				'name'          => __( 'Activities', 'course-schedule-connector' ),
				'singular_name' => __( 'Activity', 'course-schedule-connector' ),
			),
			self::TAG      => array(
				'name'          => __( 'Course tags', 'course-schedule-connector' ),
				'singular_name' => __( 'Course tag', 'course-schedule-connector' ),
			),
		);
	}
}
