<?php
/**
 * Storage for courses.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Data;

use CSCS\Api\Dto\Course;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes courses as posts.
 *
 * Two rules govern every write. A course is never deleted, because its page may
 * be linked from elsewhere and carries words somebody wrote; it changes state
 * instead. And a field a human has edited is never overwritten, because losing
 * an afternoon's work to a scheduled job is the fastest way to make a site
 * owner stop trusting an integration.
 */
final class CourseRepository {

	/**
	 * Meta key holding the remote course id.
	 */
	public const META_ID = '_cscs_id_course';

	/**
	 * Meta key holding the list of fields edited by hand.
	 */
	public const META_LOCKED = '_cscs_locked_fields';

	/**
	 * Meta key holding the lifecycle state.
	 */
	public const META_STATUS = '_cscs_status';

	/**
	 * Course is currently running or has not started yet.
	 */
	public const STATUS_RUNNING = 'running';

	/**
	 * Course has ended.
	 */
	public const STATUS_FINISHED = 'finished';

	/**
	 * Course is no longer offered by the remote system.
	 */
	public const STATUS_ARCHIVED = 'archived';

	/**
	 * Creates or updates the post for a course.
	 *
	 * @param Course $course Course record.
	 * @return int Post id, or 0 when the write failed.
	 */
	public function save( Course $course ): int {
		$post_id = $this->find( $course->id );
		$locked  = 0 === $post_id ? array() : $this->locked_fields( $post_id );

		$postarr = array(
			'post_type'   => PostType::COURSE,
			'post_status' => 'publish',
		);

		if ( ! in_array( 'post_title', $locked, true ) ) {
			$postarr['post_title'] = '' !== $course->name ? $course->name : $course->activity_name;
		}

		if ( 0 === $post_id ) {
			$postarr['post_content'] = '';
			$post_id                 = (int) wp_insert_post( $postarr, true );
		} else {
			$postarr['ID'] = $post_id;
			$post_id       = (int) wp_update_post( $postarr, true );
		}

		if ( 0 === $post_id || $post_id instanceof \WP_Error ) {
			return 0;
		}

		foreach ( $this->meta_from( $course ) as $key => $value ) {
			if ( in_array( $key, $locked, true ) ) {
				continue;
			}

			update_post_meta( $post_id, $key, $value );
		}

		update_post_meta( $post_id, self::META_STATUS, $this->status_for( $course ) );
		update_post_meta( $post_id, '_cscs_synced_at', time() );

		wp_set_object_terms( $post_id, $this->term_names( $course->trainer_name ), PostType::TRAINER );
		wp_set_object_terms( $post_id, $this->term_names( array_values( $course->tags ) ), PostType::TAG );

		/**
		 * Fires after a course record has been written.
		 *
		 * @since 0.2.0
		 *
		 * @param int    $post_id Post id.
		 * @param Course $course  Course record.
		 */
		do_action( 'cscs_course_saved', $post_id, $course );

		return $post_id;
	}

	/**
	 * Replaces the rooms of a course with the ones its occurrences actually use.
	 *
	 * @param int                $post_id Post id.
	 * @param array<int, string> $rooms   Room id to room name.
	 * @return void
	 */
	public function set_rooms( int $post_id, array $rooms ): void {
		wp_set_object_terms( $post_id, $this->term_names( array_values( $rooms ) ), PostType::ROOM );
		update_post_meta( $post_id, '_cscs_room_ids', array_map( 'intval', array_keys( $rooms ) ) );
	}

	/**
	 * Finds the post for a remote course id.
	 *
	 * @param int $course_id Remote course id.
	 * @return int Post id, or 0.
	 */
	public function find( int $course_id ): int {
		$found = get_posts(
			array(
				'post_type'        => PostType::COURSE,
				'post_status'      => 'any',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'meta_key'         => self::META_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Indexed lookup on a small post type; the alternative is a second table.
				'meta_value'       => (string) $course_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- See above.
				'suppress_filters' => false,
			)
		);

		return array() === $found ? 0 : (int) $found[0];
	}

	/**
	 * Returns every course post id, keyed by remote course id.
	 *
	 * @return array<int, int>
	 */
	public function all_ids(): array {
		$posts = get_posts(
			array(
				'post_type'        => PostType::COURSE,
				'post_status'      => 'any',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => false,
			)
		);

		$map = array();

		foreach ( $posts as $post_id ) {
			$remote_id = (int) get_post_meta( (int) $post_id, self::META_ID, true );

			if ( 0 !== $remote_id ) {
				$map[ $remote_id ] = (int) $post_id;
			}
		}

		return $map;
	}

	/**
	 * Returns course names keyed by remote course id, ordered by name.
	 *
	 * Read from what is stored rather than from the remote system, because the
	 * screens that need a list of courses — tying a make-up lesson to one, for
	 * instance — must work when the network does not.
	 *
	 * @return array<int, string>
	 */
	public function names(): array {
		$posts = get_posts(
			array(
				'post_type'        => PostType::COURSE,
				'post_status'      => 'any',
				'numberposts'      => -1,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		$names = array();

		foreach ( $posts as $post ) {
			$remote_id = (int) get_post_meta( $post->ID, self::META_ID, true );

			if ( 0 !== $remote_id ) {
				$names[ $remote_id ] = $post->post_title;
			}
		}

		return $names;
	}

	/**
	 * Marks courses the remote system no longer lists.
	 *
	 * They are kept: the page may be linked from elsewhere and may hold text a
	 * human wrote. Only the state changes.
	 *
	 * @param array<int, int> $seen Remote course ids present in the last response.
	 * @return int Number of courses archived.
	 */
	public function archive_missing( array $seen ): int {
		$archived = 0;

		foreach ( $this->all_ids() as $remote_id => $post_id ) {
			if ( in_array( $remote_id, $seen, true ) ) {
				continue;
			}

			if ( self::STATUS_ARCHIVED === get_post_meta( $post_id, self::META_STATUS, true ) ) {
				continue;
			}

			update_post_meta( $post_id, self::META_STATUS, self::STATUS_ARCHIVED );
			++$archived;
		}

		return $archived;
	}

	/**
	 * Returns the fields a human has edited.
	 *
	 * @param int $post_id Post id.
	 * @return array<int, string>
	 */
	public function locked_fields( int $post_id ): array {
		$locked = get_post_meta( $post_id, self::META_LOCKED, true );

		return is_array( $locked ) ? array_map( 'strval', $locked ) : array();
	}

	/**
	 * Derives the lifecycle state of a course from its dates.
	 *
	 * @param Course $course Course record.
	 * @return string
	 */
	private function status_for( Course $course ): string {
		$ends = $course->stamp_to;

		if ( null === $ends && null !== $course->date_to ) {
			$ends = (int) strtotime( $course->date_to . ' 23:59:59' );
		}

		if ( null === $ends || 0 === $ends ) {
			return self::STATUS_RUNNING;
		}

		return $ends < time() ? self::STATUS_FINISHED : self::STATUS_RUNNING;
	}

	/**
	 * Maps a course record onto meta keys.
	 *
	 * @param Course $course Course record.
	 * @return array<string, mixed>
	 */
	private function meta_from( Course $course ): array {
		return array(
			self::META_ID             => $course->id,
			'_cscs_api_description'   => $course->description,
			'_cscs_activity_name'     => $course->activity_name,
			'_cscs_match_key'         => $course->match_key,
			'_cscs_course_url'        => (string) $course->url,
			'_cscs_stamp_from'        => (int) $course->stamp_from,
			'_cscs_stamp_to'          => (int) $course->stamp_to,
			'_cscs_date_from'         => (string) $course->date_from,
			'_cscs_date_to'           => (string) $course->date_to,
			'_cscs_price'             => $course->price,
			'_cscs_number_lessons'    => $course->number_lessons,
			'_cscs_trainer_name'      => $course->trainer_name,
			'_cscs_room_name'         => $course->room_name,
			'_cscs_colour'            => (string) $course->colour,
			'_cscs_background'        => (string) $course->background,
			'_cscs_capacity'          => $course->capacity,
			'_cscs_capacity_waiting'  => $course->capacity_waiting,
			'_cscs_occupied'          => $course->occupied,
			'_cscs_available'         => $course->available,
			'_cscs_available_waiting' => $course->available_waiting,
			'_cscs_image'             => (string) $course->image,
			'_cscs_trainer_image'     => (string) $course->trainer_image,
			'_cscs_rating'            => $course->rating,
			'_cscs_terms'             => wp_json_encode( array_map( static fn( $term ): array => get_object_vars( $term ), $course->terms ) ),
		);
	}

	/**
	 * Normalises one or more names into taxonomy terms.
	 *
	 * @param string|array<int, string> $names Names.
	 * @return array<int, string>
	 */
	private function term_names( $names ): array {
		$list = is_array( $names ) ? $names : array( $names );

		return array_values(
			array_filter(
				array_map( 'trim', array_map( 'strval', $list ) ),
				static fn( string $name ): bool => '' !== $name
			)
		);
	}
}
