<?php
/**
 * Reading what a display set asks for.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

use CSCS\Data\CourseRepository;
use CSCS\Data\DisplaySet;
use CSCS\Data\LessonRepository;
use CSCS\Data\PostType;
use CSCS\Data\Schema;
use CSCS\Plugin;
use CSCS\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a display set into rows.
 *
 * Courses are posts, so they are read with the query WordPress already has;
 * classes are rows in the plugin's own table and are read with SQL, because a
 * timetable of several hundred classes filtered by room and date is exactly
 * what a table with indexes is for.
 *
 * Both are capped. A set configured to show everything and a term with two
 * thousand classes in it should produce a long page, not an exhausted one.
 */
final class Query {

	/**
	 * Most rows read for one listing, whatever a set says.
	 */
	private const CEILING = 1000;

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * How many rows the last query could have returned, before its page size.
	 *
	 * @var int
	 */
	private int $total = 0;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Reads the courses a set asks for.
	 *
	 * @param DisplaySet $set Display set.
	 * @return array<int, array<string, mixed>>
	 */
	public function courses( DisplaySet $set, ?ListingArgs $args = null ): array {
		$args = $args ?? ListingArgs::from_array( array() );
		$size = 0 === $set->per_page ? self::CEILING : min( self::CEILING, $set->per_page );
		$arguments = array(
			'post_type'        => PostType::COURSE,
			'post_status'      => 'publish',
			'posts_per_page'   => $size,
			'offset'           => ( $args->page - 1 ) * $size,
			'no_found_rows'    => false,
			'suppress_filters' => false,
			'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- A course post type holds hundreds of rows, and the alternative is reading them all and filtering in PHP.
				array(
					'key'     => CourseRepository::META_STATUS,
					'value'   => CourseRepository::STATUS_ARCHIVED,
					'compare' => '!=',
				),
			),
		);

		$arguments = array_merge( $arguments, $this->ordering( $set ) );

		$taxonomies = array_filter(
			array(
				PostType::TRAINER  => $set->trainers,
				PostType::ACTIVITY => $set->activities,
			)
		);

		foreach ( $taxonomies as $taxonomy => $ids ) {
			$arguments['tax_query'][] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- The point of the taxonomy is to filter by it.
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => $ids,
			);
		}

		if ( array() !== $set->rooms ) {
			// Rooms are matched on the remote id the classes carry, which the
			// synchronisation writes onto the course, rather than on a term
			// name that a person may since have renamed on the Rooms screen.
			$arguments['meta_query'][] = array(
				'key'     => CourseRepository::META_ROOM_ID,
				'value'   => $set->rooms,
				'compare' => 'IN',
			);
		}

		$rows  = array();
		$query = new \WP_Query( $arguments );

		$this->total = (int) $query->found_posts;

		foreach ( $query->posts as $post ) {
			$row = $this->course_row( $post );

			if ( $set->only_available && 'full' === Formatter::availability( (int) $row['available'], $set->few_places ) ) {
				continue;
			}

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Reads the classes a set asks for.
	 *
	 * @param DisplaySet $set Display set.
	 * @return array<int, array<string, mixed>>
	 */
	public function lessons( DisplaySet $set, ?ListingArgs $args = null ): array {
		global $wpdb;

		$args     = $args ?? ListingArgs::from_array( array() );
		$table    = Schema::lessons_table();
		$statuses = $this->statuses( $set );
		$window   = $this->window( $set, $args );

		$where  = array( 'stamp_from >= %d', 'stamp_from <= %d' );
		$values = array( $window['from'], $window['to'] );

		$where[]  = 'status IN ( ' . implode( ', ', array_fill( 0, count( $statuses ), '%s' ) ) . ' )';
		$values   = array_merge( $values, $statuses );
		$cancelled = $this->show_cancelled( $set );

		if ( ! $cancelled ) {
			$where[] = 'canceled = 0';
		}

		$rooms = $set->rooms;

		// A visitor may narrow to one room, but only to one the set already
		// covers: the set decides what a listing is about, and a query string
		// does not get to widen it.
		if ( 0 !== $args->room && ( array() === $rooms || in_array( $args->room, $rooms, true ) ) ) {
			$rooms = array( $args->room );
		}

		if ( array() !== $rooms ) {
			$where[] = 'id_tab IN ( ' . implode( ', ', array_fill( 0, count( $rooms ), '%d' ) ) . ' )';
			$values  = array_merge( $values, $rooms );
		}

		$hidden = $this->hidden_rooms();

		if ( array() !== $hidden ) {
			$where[] = 'id_tab NOT IN ( ' . implode( ', ', array_fill( 0, count( $hidden ), '%d' ) ) . ' )';
			$values  = array_merge( $values, $hidden );
		}

		$names = $this->term_names( PostType::TRAINER, $set->trainers );

		if ( array() !== $names ) {
			$where[] = 'trainer_name IN ( ' . implode( ', ', array_fill( 0, count( $names ), '%s' ) ) . ' )';
			$values  = array_merge( $values, $names );
		}

		$order  = $this->lesson_order( $set );
		$limit  = 0 === $set->per_page ? self::CEILING : min( self::CEILING, $set->per_page );
		$offset = ( $args->page - 1 ) * $limit;
		$clause = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- The plugin's own table: the table name comes from $wpdb->prefix and cannot be a placeholder, the WHERE clause is built from fixed fragments with placeholders, and every value is prepared.
		$this->total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$clause}", $values ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- The plugin's own table: the table name comes from $wpdb->prefix and cannot be a placeholder, the WHERE clause is built from fixed fragments with placeholders, and every value is prepared.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$clause} ORDER BY {$order} LIMIT %d OFFSET %d",
				array_merge( $values, array( $limit, $offset ) )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return is_array( $rows ) ? array_map( array( $this, 'lesson_row' ), $rows ) : array();
	}

	/**
	 * Returns the statuses a set covers.
	 *
	 * A make-up lesson belongs to the timetable whatever else it is: somebody
	 * is standing in a hall at that hour. Rentals are the choice a set makes.
	 *
	 * @param DisplaySet $set Display set.
	 * @return array<int, string>
	 */
	private function statuses( DisplaySet $set ): array {
		$statuses = array(
			LessonRepository::STATUS_MATCHED,
			LessonRepository::STATUS_MAKEUP,
			LessonRepository::STATUS_UNRESOLVED,
		);

		if ( $set->include_rentals ) {
			$statuses[] = LessonRepository::STATUS_EXTERNAL;
			$statuses[] = LessonRepository::STATUS_NOT_BOOKABLE;
		}

		return $statuses;
	}

	/**
	 * Returns the window of time a set covers, as timestamps.
	 *
	 * @param DisplaySet $set Display set.
	 * @return array{from: int, to: int}
	 */
	private function window( DisplaySet $set, ListingArgs $args ): array {
		$now = time() + ( $args->week * WEEK_IN_SECONDS );

		// Stepping to another week means the whole of that week, not the rest
		// of it: "next week" starting on Thursday would be a strange answer.
		if ( 0 !== $args->week && 'week' === $set->range ) {
			return array(
				'from' => $this->start_of_week( $now ),
				'to'   => $this->end_of_week( $now ),
			);
		}

		if ( 'custom' === $set->range ) {
			$from = $this->stamp( $set->date_from, '00:00:00' );
			$to   = $this->stamp( $set->date_to, '23:59:59' );

			return array(
				'from' => 0 === $from ? $now : $from,
				'to'   => 0 === $to ? $now + ( 365 * DAY_IN_SECONDS ) : $to,
			);
		}

		if ( 'week' === $set->range ) {
			// The week a visitor is in, not the next seven days: a timetable
			// headed "this week" that starts on Thursday and runs into next
			// Wednesday is not what anybody means by the week.
			return array(
				'from' => $now,
				'to'   => $this->end_of_week( $now ),
			);
		}

		if ( 'days' === $set->range ) {
			return array(
				'from' => $now,
				'to'   => $now + ( $set->range_days * DAY_IN_SECONDS ),
			);
		}

		$term_to = $this->stamp( (string) $this->plugin->settings()->get( 'semester_to' ), '23:59:59' );

		return array(
			'from' => $now,
			'to'   => 0 === $term_to ? $now + ( 180 * DAY_IN_SECONDS ) : $term_to,
		);
	}

	/**
	 * Turns a stored date into a timestamp in the site's own time zone.
	 *
	 * WordPress runs PHP in UTC, so `strtotime( '2026-09-11' )` is midnight in
	 * London rather than midnight in Prague. Two hours is enough to drop the
	 * first class of a term out of the window it belongs in.
	 *
	 * @param string $date Date, `Y-m-d`.
	 * @param string $time Time of day to take.
	 * @return int Timestamp, or 0 when there is no usable date.
	 */
	private function stamp( string $date, string $time ): int {
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return 0;
		}

		try {
			return ( new \DateTimeImmutable( $date . ' ' . $time, wp_timezone() ) )->getTimestamp();
		} catch ( \Exception $e ) {
			unset( $e );

			return 0;
		}
	}

	/**
	 * Returns the end of the week a timestamp falls in, locally.
	 *
	 * @param int $now Timestamp.
	 * @return int
	 */
	private function start_of_week( int $now ): int {
		try {
			$today = ( new \DateTimeImmutable( '@' . $now ) )->setTimezone( wp_timezone() );

			return $today->modify( 'monday this week' )->setTime( 0, 0, 0 )->getTimestamp();
		} catch ( \Exception $e ) {
			unset( $e );

			return $now;
		}
	}

	/**
	 * Returns the end of the week a timestamp falls in, locally.
	 *
	 * @param int $now Timestamp.
	 * @return int
	 */
	private function end_of_week( int $now ): int {
		try {
			$today = ( new \DateTimeImmutable( '@' . $now ) )->setTimezone( wp_timezone() );
			$end   = $today->modify( 'sunday this week' )->setTime( 23, 59, 59 );

			return $end->getTimestamp();
		} catch ( \Exception $e ) {
			unset( $e );

			return $now + ( 7 * DAY_IN_SECONDS );
		}
	}

	/**
	 * Whether cancelled classes belong in this listing.
	 *
	 * @param DisplaySet $set Display set.
	 * @return bool
	 */
	private function show_cancelled( DisplaySet $set ): bool {
		if ( 'show' === $set->cancelled ) {
			return true;
		}

		if ( 'hide' === $set->cancelled ) {
			return false;
		}

		return (bool) $this->plugin->settings()->get( 'show_canceled_lessons' );
	}

	/**
	 * Returns the rooms a person has hidden.
	 *
	 * @return array<int, int>
	 */
	private function hidden_rooms(): array {
		$hidden = array();

		foreach ( $this->plugin->rooms()->all() as $id => $room ) {
			if ( $room['hidden'] ) {
				$hidden[] = (int) $id;
			}
		}

		return $hidden;
	}

	/**
	 * Returns the names behind a list of term ids.
	 *
	 * The classes table holds names rather than term ids — it mirrors what the
	 * remote system sends — so a filter chosen as a term has to be translated
	 * before it can be applied to a class.
	 *
	 * @param string          $taxonomy Taxonomy name.
	 * @param array<int, int> $ids      Term ids.
	 * @return array<int, string>
	 */
	private function term_names( string $taxonomy, array $ids ): array {
		if ( array() === $ids ) {
			return array();
		}

		$names = array();

		foreach ( $ids as $id ) {
			$term = get_term( (int) $id, $taxonomy );

			if ( $term instanceof \WP_Term ) {
				$names[] = $term->name;
			}
		}

		return $names;
	}

	/**
	 * Returns the ORDER BY clause for a timetable.
	 *
	 * @param DisplaySet $set Display set.
	 * @return string
	 */
	private function lesson_order( DisplaySet $set ): string {
		$columns = array(
			'start' => 'stamp_from',
			'room'  => 'tab_name',
			'name'  => 'activity_name',
		);

		$column    = $columns[ $set->sort ] ?? 'stamp_from';
		$direction = 'desc' === $set->order ? 'DESC' : 'ASC';

		// Whatever the chosen order, a timetable reads in time within it.
		return 'stamp_from' === $column
			? $column . ' ' . $direction
			: $column . ' ' . $direction . ', stamp_from ASC';
	}

	/**
	 * Returns the ordering arguments for a course query.
	 *
	 * @param DisplaySet $set Display set.
	 * @return array<string, mixed>
	 */
	private function ordering( DisplaySet $set ): array {
		$direction = 'desc' === $set->order ? 'DESC' : 'ASC';

		$meta = array(
			'start'  => '_cscs_stamp_from',
			'price'  => '_cscs_price',
			'places' => '_cscs_available',
		);

		if ( ! isset( $meta[ $set->sort ] ) ) {
			return array(
				'orderby' => 'title',
				'order'   => $direction,
			);
		}

		return array(
			'orderby'  => 'meta_value_num',
			'order'    => $direction,
			'meta_key' => $meta[ $set->sort ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Sorting a few hundred posts by a numeric field is what this is for.
		);
	}

	/**
	 * Builds one course row.
	 *
	 * @param \WP_Post $post Course.
	 * @return array<string, mixed>
	 */
	public function course_row( \WP_Post $post ): array {
		$meta = get_post_meta( $post->ID );
		$read = static function ( string $key ) use ( $meta ) {
			return isset( $meta[ $key ][0] ) ? $meta[ $key ][0] : '';
		};

		return array(
			'post_id'     => $post->ID,
			'course_id'   => (int) $read( CourseRepository::META_ID ),
			'name'        => $post->post_title,
			'permalink'   => (string) get_permalink( $post ),
			'excerpt'     => $post->post_excerpt,
			'activity'    => (string) $read( '_cscs_activity_name' ),
			'trainer'     => (string) $read( '_cscs_trainer_name' ),
			'rooms'       => wp_get_object_terms( $post->ID, PostType::ROOM, array( 'fields' => 'names' ) ),
			'price'       => '' === $read( '_cscs_price' ) ? null : (string) $read( '_cscs_price' ),
			'date_from'   => (string) $read( '_cscs_date_from' ),
			'date_to'     => (string) $read( '_cscs_date_to' ),
			'lessons'     => (int) $read( '_cscs_number_lessons' ),
			'capacity'    => (int) $read( '_cscs_capacity' ),
			'available'   => (int) $read( '_cscs_available' ),
			'url'         => (string) $read( '_cscs_course_url' ),
			'button'      => (string) $read( CourseRepository::META_BUTTON ),
			'booking'     => '' === $read( '_cscs_booking_allowed' ) ? true : (bool) $read( '_cscs_booking_allowed' ),
			'contact'     => array(
				'name'  => (string) $read( CourseRepository::META_CONTACT_NAME ),
				'email' => (string) $read( CourseRepository::META_CONTACT_EMAIL ),
				'phone' => (string) $read( CourseRepository::META_CONTACT_PHONE ),
				'note'  => (string) $read( CourseRepository::META_CONTACT_NOTE ),
			),
			'is_manual'   => CourseRepository::is_manual( (int) $read( CourseRepository::META_ID ) ),
			'terms'       => (string) $read( '_cscs_terms' ),
		);
	}

	/**
	 * Builds one class row.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @return array<string, mixed>
	 */
	public function lesson_row( array $row ): array {
		$course_id = (int) ( $row['id_course'] ?? 0 );
		$post_id   = 0 === $course_id ? 0 : $this->plugin->courses()->find( $course_id );

		return array(
			'term_id'   => (int) ( $row['id_activity_term'] ?? 0 ),
			'course_id' => $course_id,
			'post_id'   => $post_id,
			'permalink' => 0 === $post_id ? '' : (string) get_permalink( $post_id ),
			'course'    => 0 === $post_id ? '' : (string) get_the_title( $post_id ),
			'name'      => (string) ( $row['activity_name'] ?? '' ),
			'date'      => (string) ( $row['lesson_date'] ?? '' ),
			'stamp'     => (int) ( $row['stamp_from'] ?? 0 ),
			'time_from' => (string) ( $row['time_from'] ?? '' ),
			'time_to'   => (string) ( $row['time_to'] ?? '' ),
			'room_id'   => (int) ( $row['id_tab'] ?? 0 ),
			'room'      => (string) ( $row['tab_name'] ?? '' ),
			'trainer'   => (string) ( $row['trainer_name'] ?? '' ),
			'price'     => null === ( $row['price'] ?? null ) ? null : (string) $row['price'],
			'capacity'  => (int) ( $row['capacity'] ?? 0 ),
			'available' => (int) ( $row['available'] ?? 0 ),
			'cancelled' => (bool) ( $row['canceled'] ?? false ),
			'booking'   => (bool) ( $row['booking_allowed'] ?? false ),
			'status'    => (string) ( $row['status'] ?? '' ),
		);
	}

	/**
	 * Reads the classes of one course, from now on.
	 *
	 * @param int $course_id Course id.
	 * @param int $limit     Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function course_schedule( int $course_id, int $limit = 200 ): array {
		$rows = $this->plugin->lessons()->for_course( $course_id, $limit, time() );

		return array_map( array( $this, 'lesson_row' ), $rows );
	}

	/**
	 * Reads the make-up lessons somebody tied to one course.
	 *
	 * @param int $course_id Course id.
	 * @return array<int, array<string, mixed>>
	 */
	public function course_makeup( int $course_id ): array {
		$repository = $this->plugin->lessons();
		$rows       = $repository->find_many( $repository->makeup_terms_for_course( $course_id ) );

		$upcoming = array_filter(
			$rows,
			static function ( array $row ): bool {
				return (int) ( $row['stamp_from'] ?? 0 ) >= time();
			}
		);

		return array_map( array( $this, 'lesson_row' ), array_values( $upcoming ) );
	}

	/**
	 * Returns when each course actually meets, as weekday and time.
	 *
	 * A course record carries a list of timestamps and no notion of "Tuesdays
	 * at half past three", which is the only form anybody reads a timetable in.
	 * The classes know, so they are asked — once for the whole listing rather
	 * than once per row, because a page of forty courses should cost one query
	 * and not forty.
	 *
	 * @param array<int, int> $course_ids Course ids.
	 * @return array<int, array<int, array{day: int, time: string}>>
	 */
	public function course_times( array $course_ids ): array {
		global $wpdb;

		$course_ids = array_values( array_unique( array_filter( array_map( 'intval', $course_ids ) ) ) );

		if ( array() === $course_ids ) {
			return array();
		}

		$table        = Schema::lessons_table();
		$placeholders = implode( ', ', array_fill( 0, count( $course_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- The plugin's own table: the table name comes from $wpdb->prefix and cannot be a placeholder, and every bound value is prepared.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id_course, WEEKDAY(lesson_date) AS weekday, time_from, COUNT(*) AS lessons
				FROM {$table}
				WHERE id_course IN ( {$placeholders} ) AND lesson_date IS NOT NULL AND canceled = 0
				GROUP BY id_course, weekday, time_from
				ORDER BY weekday ASC, time_from ASC",
				$course_ids
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$times = array();

		foreach ( $rows as $row ) {
			// A slot that happened once is a substitution or a mistake, not the
			// day the course runs on, and printing it beside the real one is
			// how a timetable stops being trusted.
			if ( (int) ( $row['lessons'] ?? 0 ) < 2 ) {
				continue;
			}

			$times[ (int) $row['id_course'] ][] = array(
				'day'  => (int) $row['weekday'],
				'time' => substr( (string) $row['time_from'], 0, 5 ),
			);
		}

		return $times;
	}

	/**
	 * Returns how many rows the last listing query matched in total.
	 *
	 * @return int
	 */
	public function total(): int {
		return $this->total;
	}

	/**
	 * Reads a setting, for templates that need one.
	 *
	 * @return Settings
	 */
	public function settings(): Settings {
		return $this->plugin->settings();
	}
}
