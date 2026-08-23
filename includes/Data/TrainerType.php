<?php
/**
 * The trainer post type.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the post type a trainer's own page is made of.
 *
 * iSport knows a trainer as a name attached to a course and, at best, a
 * photograph. Everything a visitor actually wants to read about the person who
 * will be in the hall — what they are qualified in, what they do otherwise,
 * the one sentence that makes them a person rather than a name — exists
 * nowhere in the remote system and never will. So a trainer is a post: the
 * synchronisation keeps the name and the photograph current, and everything
 * written here is left alone by it, permanently.
 *
 * The `cscs_trainer` taxonomy stays exactly as it was. It is what display sets
 * filter by and what several hundred courses are already tagged with, and
 * replacing a working filter to avoid holding a name in two places would be
 * paying in broken configuration for a tidiness nobody can see. The post and
 * the term are tied by the same normalised name the whole plugin matches on.
 */
final class TrainerType {

	/**
	 * Post type name.
	 *
	 * Twenty characters, which is exactly what WordPress allows, and it may not
	 * be `cscs_trainer`: that name already belongs to the taxonomy, and a post
	 * type sharing it would collide over the query variable.
	 */
	public const TRAINER = 'cscs_trainer_profile';

	/**
	 * The normalised name this trainer is matched by. The pairing key.
	 */
	public const META_KEY_NAME = '_cscs_trainer_key';

	/**
	 * The trainer's id in iSport, where a class has told us one.
	 */
	public const META_REMOTE_ID = '_cscs_trainer_remote_id';

	/**
	 * Qualifications, a list.
	 */
	public const META_QUALIFICATIONS = '_cscs_qualifications';

	/**
	 * Interests, a list.
	 */
	public const META_HOBBIES = '_cscs_hobbies';

	/**
	 * The short fact worth knowing about this trainer.
	 */
	public const META_FACT = '_cscs_fact';

	/**
	 * The trainer's motto.
	 */
	public const META_MOTTO = '_cscs_motto';

	/**
	 * The attachment made from the photograph iSport holds.
	 */
	public const META_PHOTO_ID = '_cscs_photo_id';

	/**
	 * The address that photograph was fetched from, so it is fetched once.
	 */
	public const META_PHOTO_SOURCE = '_cscs_photo_source';

	/**
	 * Registers the post type and the fields a person fills in.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_post_type(
			self::TRAINER,
			array(
				'labels'             => array(
					'name'               => __( 'Trainers', 'course-schedule-connector' ),
					'singular_name'      => __( 'Trainer', 'course-schedule-connector' ),
					'menu_name'          => __( 'Trainers', 'course-schedule-connector' ),
					'add_new_item'       => __( 'Add trainer', 'course-schedule-connector' ),
					'edit_item'          => __( 'Edit trainer', 'course-schedule-connector' ),
					'new_item'           => __( 'New trainer', 'course-schedule-connector' ),
					'view_item'          => __( 'View trainer', 'course-schedule-connector' ),
					'search_items'       => __( 'Search trainers', 'course-schedule-connector' ),
					'not_found'          => __( 'No trainers found.', 'course-schedule-connector' ),
					'not_found_in_trash' => __( 'No trainers in the bin.', 'course-schedule-connector' ),
				),
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => false,
				'show_in_rest'       => true,
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_icon'          => 'dashicons-groups',
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
				'capability_type'    => 'post',
				'map_meta_cap'       => true,
				/**
				 * Filters the URL slug of a trainer.
				 *
				 * Czech, because the site is Czech and a visitor reads the
				 * address. The plugin's own vocabulary stays English — that is
				 * for whoever maintains it — but nothing a reader sees should be
				 * in a language the site is not written in.
				 *
				 * @since 0.5.0
				 *
				 * @param string $slug Rewrite slug.
				 */
				'rewrite'            => array( 'slug' => apply_filters( 'cscs_trainer_rewrite_slug', 'trener' ) ),
			)
		);

		self::register_meta();
	}

	/**
	 * Registers the fields.
	 *
	 * The two lists are single meta rows holding an array rather than repeated
	 * rows. Nothing ever asks "which trainers hold this qualification" — the
	 * lists are read whole, in order, by the page that prints them — and an
	 * ordered list that a person drags about is one value, not several.
	 *
	 * @return void
	 */
	private static function register_meta(): void {
		$editable = static function (): bool {
			return current_user_can( 'cscs_manage_content' );
		};

		$strings = array(
			self::META_FACT         => 'sanitize_textarea_field',
			self::META_MOTTO        => 'sanitize_text_field',
			self::META_KEY_NAME     => 'sanitize_text_field',
			self::META_PHOTO_SOURCE => 'esc_url_raw',
		);

		foreach ( $strings as $key => $sanitiser ) {
			register_post_meta(
				self::TRAINER,
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'show_in_rest'      => self::META_KEY_NAME === $key ? false : true,
					'sanitize_callback' => $sanitiser,
					'auth_callback'     => $editable,
				)
			);
		}

		foreach ( array( self::META_REMOTE_ID, self::META_PHOTO_ID ) as $key ) {
			register_post_meta(
				self::TRAINER,
				$key,
				array(
					'type'              => 'integer',
					'single'            => true,
					'default'           => 0,
					'show_in_rest'      => true,
					'sanitize_callback' => 'absint',
					'auth_callback'     => $editable,
				)
			);
		}

		foreach ( array( self::META_QUALIFICATIONS, self::META_HOBBIES ) as $key ) {
			register_post_meta(
				self::TRAINER,
				$key,
				array(
					'type'              => 'array',
					'single'            => true,
					'default'           => array(),
					'show_in_rest'      => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
					'sanitize_callback' => array( self::class, 'sanitise_list' ),
					'auth_callback'     => $editable,
				)
			);
		}
	}

	/**
	 * Reduces a submitted list to clean lines.
	 *
	 * @param mixed $value Submitted value.
	 * @return array<int, string>
	 */
	public static function sanitise_list( $value ): array {
		$list = is_array( $value ) ? $value : array( $value );

		return array_values(
			array_filter(
				array_map(
					static function ( $line ): string {
						return sanitize_text_field( trim( (string) $line ) );
					},
					$list
				),
				static function ( string $line ): bool {
					return '' !== $line;
				}
			)
		);
	}
}
