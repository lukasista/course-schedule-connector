<?php
/**
 * Storage for courses.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Data;

defined( 'ABSPATH' ) || exit;

use CSCS\Api\Dto\Course;

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
	 * Meta key holding who the course is for.
	 */
	public const META_GENDER = '_cscs_gender';

	/**
	 * Meta key holding the level the course is at.
	 */
	public const META_LEVEL = '_cscs_level';

	/**
	 * Meta key holding the youngest age the course is for.
	 */
	public const META_AGE_FROM = '_cscs_age_from';

	/**
	 * Meta key holding the oldest age the course is for.
	 *
	 * Empty where the name gives a floor and no ceiling — "od 10 let" — which
	 * is a different thing from an unknown age and has to stay tellable apart.
	 */
	public const META_AGE_TO = '_cscs_age_to';

	/**
	 * Where the ids of hand-made courses start.
	 *
	 * A course a person creates here has no id in iSport, and everything
	 * downstream — a manual assignment, a make-up link, a rendered listing —
	 * is written in terms of course ids. Rather than teach each of those about
	 * a second kind of course, one is invented in a range the remote system
	 * will never reach: iSport's ids are in the low thousands, and a billion is
	 * a long way from that.
	 */
	public const MANUAL_ID_BASE = 1000000000;

	/**
	 * Meta key holding one room id the course actually runs in.
	 *
	 * Repeated: a course can run in more than one room, and a listing filtered
	 * by room has to be able to ask whether one particular id is among them.
	 */
	public const META_ROOM_ID = '_cscs_room_ids';

	/**
	 * Meta key marking a course somebody created by hand.
	 */
	public const META_MANUAL = '_cscs_manual';

	/**
	 * Meta key deciding whether the booking button shows on this course.
	 *
	 * One of `default`, `always` or `never`. The default follows the setting,
	 * which is what makes a site-wide change possible without touching a
	 * hundred courses — and the two overrides are what makes an exception
	 * possible without abandoning the default.
	 */
	public const META_BUTTON = '_cscs_show_button';

	/**
	 * Meta keys holding the lecturer's contact details.
	 *
	 * Courses nobody books through iSport still need somebody to ask, and the
	 * remote system has nowhere to put that, so it is written here.
	 */
	public const META_CONTACT_NAME = '_cscs_contact_name';

	/**
	 * Lecturer's e-mail address.
	 */
	public const META_CONTACT_EMAIL = '_cscs_contact_email';

	/**
	 * Lecturer's telephone number.
	 */
	public const META_CONTACT_PHONE = '_cscs_contact_phone';

	/**
	 * Anything else worth saying about how to reach the lecturer.
	 */
	public const META_CONTACT_NOTE = '_cscs_contact_note';

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

		// The normalised name is written after the meta loop rather than in it,
		// so that a site which has locked the trainer name keeps the key of the
		// name it actually shows. A key that disagreed with the name beside it
		// would point the course at somebody else's page.
		$this->pair_with_trainer( $post_id, $course );
		$this->derive_audience( $post_id, $locked );
		$this->derive_kind( $post_id, $locked );
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
	 * Reads the audience out of a course's name again, right now.
	 *
	 * For the editing screen. Leaving a value at "as the name says" deletes the
	 * stored one, and the panel promises the name will be read instead — but
	 * nothing read it until the next synchronisation, so a course somebody
	 * merely opened and saved lost its age, its group and its level until then.
	 * Two of this gym's 113 courses were sitting like that.
	 *
	 * @param int $post_id Course post id.
	 * @return void
	 */
	public function refresh_audience( int $post_id ): void {
		$this->derive_audience( $post_id, $this->locked_fields( $post_id ) );
	}

	/**
	 * Works out who the course is for, at what level and at what age, from its
	 * name.
	 *
	 * After the meta loop and after the title, for the same reason the trainer
	 * key is: a site that has locked the course name shows a name of its own,
	 * and reading the audience out of iSport's name instead would say one thing
	 * beside another. What is on the page is what is read.
	 *
	 * Either value may be locked on its own, which is how an administrator
	 * overrules a reading — a course whose name says nothing, or says it in
	 * words this does not know, is filled in by hand and stays filled in.
	 *
	 * @param int                $post_id Course post id.
	 * @param array<int, string> $locked  Fields a human has edited.
	 * @return void
	 */
	private function derive_audience( int $post_id, array $locked ): void {
		$read = Audience::read( (string) get_post_field( 'post_title', $post_id ) );

		$values = array(
			self::META_GENDER   => $read['gender'],
			self::META_LEVEL    => $read['level'],
			self::META_AGE_FROM => $read['age_from'],
			self::META_AGE_TO   => $read['age_to'],
		);

		foreach ( $values as $key => $value ) {
			if ( in_array( $key, $locked, true ) ) {
				continue;
			}

			if ( '' === $value ) {
				delete_post_meta( $post_id, $key );

				continue;
			}

			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Files the course under the kind of course its name says it is.
	 *
	 * The kind is what a page calls a card — "Gymnastika", "Jojo přípravka" —
	 * and it is a taxonomy rather than a meta value because that is what a
	 * display set filters by, what the courses list can be sorted by and what
	 * somebody can rename in one place for every course under it.
	 *
	 * Read from the activity name where iSport sends one that differs from the
	 * course's own — see {@see CourseKind::read_for()} — because that is the
	 * name that says which group a course belongs to.
	 *
	 * Locked like anything else a person may have corrected: tick *Kind of
	 * course* on the course and the reading stops touching it, which is how two
	 * kinds get merged into one where the names disagree.
	 *
	 * @param int                $post_id Course post id.
	 * @param array<int, string> $locked  Fields a human has edited.
	 * @return void
	 */
	private function derive_kind( int $post_id, array $locked ): void {
		if ( in_array( PostType::KIND, $locked, true ) ) {
			return;
		}

		$kind = CourseKind::read_for(
			(string) get_post_meta( $post_id, '_cscs_activity_name', true ),
			(string) get_post_field( 'post_title', $post_id )
		);

		wp_set_object_terms( $post_id, '' === $kind ? array() : array( $kind ), PostType::KIND );

		// The page written about a kind is made the moment a course is filed
		// under it, the way a trainer's page is: an editor should find it
		// waiting rather than have to know it must be created first.
		if ( '' !== $kind ) {
			( new KindRepository() )->ensure( $kind );
		}
	}

	/**
	 * Ties the course to a trainer's page, making the page if there is none.
	 *
	 * @param int    $post_id Course post id.
	 * @param Course $course  Course record.
	 * @return void
	 */
	private function pair_with_trainer( int $post_id, Course $course ): void {
		$name = (string) get_post_meta( $post_id, '_cscs_trainer_name', true );
		$name = '' === $name ? $course->trainer_name : $name;
		$key  = TrainerRepository::key( $name );

		if ( '' === $key ) {
			delete_post_meta( $post_id, TrainerType::META_KEY_NAME );

			return;
		}

		update_post_meta( $post_id, TrainerType::META_KEY_NAME, $key );

		/**
		 * Filters whether a synchronisation may create a trainer's page.
		 *
		 * A site that would rather write its trainers by hand switches this off
		 * and keeps the pairing: the courses still find a page, they just do not
		 * bring one into being.
		 *
		 * @since 0.5.0
		 *
		 * @param bool   $create Whether to create missing trainer pages.
		 * @param string $name   Trainer name.
		 */
		if ( ! apply_filters( 'cscs_create_trainer_pages', true, $name ) ) {
			return;
		}

		( new TrainerRepository() )->ensure( $name, $course->trainer_image );
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

		// One meta row per room rather than one row holding a list. A serialised
		// array cannot be asked "is this id in you?", and filtering a listing by
		// room is exactly that question.
		$stored = array_map( 'intval', (array) get_post_meta( $post_id, self::META_ROOM_ID ) );
		$wanted = array_map( 'intval', array_keys( $rooms ) );

		foreach ( array_diff( $stored, $wanted ) as $gone ) {
			delete_post_meta( $post_id, self::META_ROOM_ID, $gone );
		}

		foreach ( array_diff( $wanted, $stored ) as $added ) {
			add_post_meta( $post_id, self::META_ROOM_ID, $added );
		}
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
	 * Whether a course id belongs to a course somebody created by hand.
	 *
	 * @param int $course_id Course id.
	 * @return bool
	 */
	public static function is_manual( int $course_id ): bool {
		return $course_id >= self::MANUAL_ID_BASE;
	}

	/**
	 * Returns the ids of the courses somebody created by hand.
	 *
	 * @return array<int, int>
	 */
	public function manual_ids(): array {
		$ids = array();

		foreach ( $this->all_ids() as $course_id => $post_id ) {
			unset( $post_id );

			if ( self::is_manual( (int) $course_id ) ) {
				$ids[] = (int) $course_id;
			}
		}

		return $ids;
	}

	/**
	 * Gives a course post an id of its own when it has none.
	 *
	 * Called for anything created through the WordPress editor rather than by a
	 * synchronisation. The id is derived from the post id, so it is stable and
	 * cannot collide with another course on the same site.
	 *
	 * @param int $post_id Post id.
	 * @return int The course id the post now has.
	 */
	public function ensure_manual_id( int $post_id ): int {
		$existing = (int) get_post_meta( $post_id, self::META_ID, true );

		if ( 0 !== $existing ) {
			return $existing;
		}

		$course_id = self::MANUAL_ID_BASE + $post_id;

		update_post_meta( $post_id, self::META_ID, $course_id );
		update_post_meta( $post_id, self::META_MANUAL, 1 );
		update_post_meta( $post_id, self::META_STATUS, self::STATUS_RUNNING );

		return $course_id;
	}

	/**
	 * Creates a course by hand, from whatever is known about it.
	 *
	 * Used for the courses Jojo Gym does not take bookings or payments for: an
	 * outside lecturer's class occupies a slot in the timetable and belongs on
	 * the website, but no record of it will ever arrive from iSport.
	 *
	 * @param string               $name   Course name.
	 * @param array<string, mixed> $fields Optional meta to seed it with.
	 * @return int Post id, or 0 when the write failed.
	 */
	public function create_manual( string $name, array $fields = array() ): int {
		$name = trim( $name );

		if ( '' === $name ) {
			return 0;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => PostType::COURSE,
				'post_status'  => 'publish',
				'post_title'   => $name,
				'post_content' => '',
			),
			true
		);

		if ( $post_id instanceof \WP_Error || 0 === (int) $post_id ) {
			return 0;
		}

		$post_id = (int) $post_id;

		$this->ensure_manual_id( $post_id );

		foreach ( $fields as $key => $value ) {
			update_post_meta( $post_id, (string) $key, $value );
		}

		// The name is the whole record here, so it is locked from the start:
		// nothing should ever overwrite it, and there is no remote record that
		// could, but saying so out loud costs nothing and survives a change of
		// mind about what synchronisation touches.
		update_post_meta( $post_id, self::META_LOCKED, array( 'post_title' ) );

		return $post_id;
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
			// A hand-made course was never in the remote list and its absence
			// from it means nothing.
			if ( self::is_manual( (int) $remote_id ) || in_array( $remote_id, $seen, true ) ) {
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
