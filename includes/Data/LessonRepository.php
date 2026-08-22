<?php
/**
 * Storage for class occurrences.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Data;

use CSCS\Api\Dto\Lesson;
use CSCS\Api\Mapper;
use CSCS\Support\Normalise;
use CSCS\Sync\Assignment;
use CSCS\Sync\MatchResult;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the class occurrence table.
 *
 * Writes are idempotent: the same window can be synchronised any number of
 * times and produces the same rows, because the occurrence id from the remote
 * system is the primary key. Nothing here deletes on the strength of an absent
 * record — a record missing from one window is not evidence that it is gone.
 */
final class LessonRepository {

	/**
	 * Option holding permanent manual assignments, occurrence id to course id.
	 */
	public const MANUAL_OPTION = 'cscs_manual_matches';

	/**
	 * Option tying make-up occurrences to the courses they replace a class for.
	 *
	 * Keyed by occurrence id, the same as the manual assignments above.
	 */
	public const MAKEUP_OPTION = 'cscs_makeup_links';

	/**
	 * Row is tied to a course.
	 */
	public const STATUS_MATCHED = 'matched';

	/**
	 * Row is a hall rental or an open public session.
	 */
	public const STATUS_EXTERNAL = 'external';

	/**
	 * Row belongs to an activity that takes no bookings.
	 */
	public const STATUS_NOT_BOOKABLE = 'not_bookable';

	/**
	 * Row is a make-up lesson for a class somebody missed.
	 */
	public const STATUS_MAKEUP = 'makeup';

	/**
	 * Row should have found a course and did not.
	 */
	public const STATUS_UNRESOLVED = 'unresolved';

	/**
	 * How many rows are written per statement.
	 */
	private const CHUNK = 100;

	/**
	 * Stores a batch of occurrences, preserving any manual assignment already made.
	 *
	 * @param array<int, Lesson> $lessons Occurrences keyed by occurrence id.
	 * @param MatchResult|null   $outcome Matching outcome, when one is available.
	 * @return int Number of rows written.
	 */
	public function save( array $lessons, ?MatchResult $outcome = null ): int {
		if ( array() === $lessons ) {
			return 0;
		}

		global $wpdb;

		$table   = Schema::lessons_table();
		$now     = time();
		$written = 0;

		foreach ( array_chunk( $lessons, self::CHUNK, true ) as $chunk ) {
			$rows         = array();
			$placeholders = array();

			foreach ( $chunk as $lesson ) {
				$placeholders[] = '(%d,%d,%d,%s,%s,%s,%d,%d,%s,%s,%s,%d,%s,%d,%s,%d,%s,%s,%d,%d,%d,%d,%d,%d,%d,%d,%s,%s,%d)';

				array_push( $rows, ...array_values( self::row_values( $lesson, $outcome, $now ) ) );
			}

			$sql = 'INSERT INTO ' . $table . ' (
				id_activity_term, id_activity, id_course, match_method, activity_name, match_key,
				stamp_from, stamp_to, lesson_date, time_from, time_to, id_tab, tab_name,
				id_lane, lane_name, id_trainer, trainer_name, price, capacity, capacity_waiting,
				occupied, available, available_waiting, canceled, booking_allowed, is_external,
				status, payload, synced_at
			) VALUES ' . implode( ',', $placeholders ) . '
			ON DUPLICATE KEY UPDATE
				id_activity = VALUES(id_activity),
				id_course = VALUES(id_course),
				match_method = VALUES(match_method),
				activity_name = VALUES(activity_name),
				match_key = VALUES(match_key),
				stamp_from = VALUES(stamp_from),
				stamp_to = VALUES(stamp_to),
				lesson_date = VALUES(lesson_date),
				time_from = VALUES(time_from),
				time_to = VALUES(time_to),
				id_tab = VALUES(id_tab),
				tab_name = VALUES(tab_name),
				id_lane = VALUES(id_lane),
				lane_name = VALUES(lane_name),
				id_trainer = VALUES(id_trainer),
				trainer_name = VALUES(trainer_name),
				price = VALUES(price),
				capacity = VALUES(capacity),
				capacity_waiting = VALUES(capacity_waiting),
				occupied = VALUES(occupied),
				available = VALUES(available),
				available_waiting = VALUES(available_waiting),
				canceled = VALUES(canceled),
				booking_allowed = VALUES(booking_allowed),
				is_external = VALUES(is_external),
				status = VALUES(status),
				payload = VALUES(payload),
				synced_at = VALUES(synced_at)';

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Placeholders are generated above and the values are passed through prepare().
			$result = $wpdb->query( $wpdb->prepare( $sql, $rows ) );

			if ( is_int( $result ) ) {
				$written += count( $chunk );
			}
		}

		return $written;
	}

	/**
	 * Builds the column values for one occurrence.
	 *
	 * Kept separate and free of the database so that the value of id_course for
	 * an unmatched occurrence can be asserted in a test. It has to be zero, not
	 * null: $wpdb->prepare() turns a null bound to %d into 0, so storing null
	 * was never possible, and every query that looked for NULL silently matched
	 * nothing.
	 *
	 * @param Lesson           $lesson  Occurrence.
	 * @param MatchResult|null $outcome Matching outcome, when one is available.
	 * @param int              $now     Timestamp to record.
	 * @return array<string, mixed> Values in column order.
	 */
	public static function row_values( Lesson $lesson, ?MatchResult $outcome, int $now ): array {
		$assignment = $outcome instanceof MatchResult ? ( $outcome->assignments[ $lesson->id_term ] ?? null ) : null;
		$reason     = $outcome instanceof MatchResult ? ( $outcome->unmatched[ $lesson->id_term ]['reason'] ?? '' ) : '';

		return array(
			'id_activity_term'  => $lesson->id_term,
			'id_activity'       => $lesson->id_activity,
			'id_course'         => $assignment instanceof Assignment ? $assignment->course_id : 0,
			'match_method'      => $assignment instanceof Assignment ? $assignment->method : '',
			'activity_name'     => $lesson->activity_name,
			'match_key'         => $lesson->match_key,
			'stamp_from'        => (int) $lesson->stamp_from,
			'stamp_to'          => (int) $lesson->stamp_to,
			'lesson_date'       => $lesson->date,
			'time_from'         => (string) $lesson->time_from,
			'time_to'           => (string) $lesson->time_to,
			'id_tab'            => $lesson->id_tab,
			'tab_name'          => $lesson->tab_name,
			'id_lane'           => $lesson->id_lane,
			'lane_name'         => $lesson->lane_name,
			'id_trainer'        => $lesson->id_trainer,
			'trainer_name'      => $lesson->trainer_name,
			'price'             => $lesson->price,
			'capacity'          => $lesson->capacity,
			'capacity_waiting'  => $lesson->capacity_waiting,
			'occupied'          => $lesson->occupied,
			'available'         => $lesson->available,
			'available_waiting' => $lesson->available_waiting,
			'canceled'          => $lesson->canceled ? 1 : 0,
			'booking_allowed'   => $lesson->booking_allowed ? 1 : 0,
			'is_external'       => in_array( $reason, array( 'no_candidate', 'not_bookable', 'makeup' ), true ) ? 1 : 0,
			'status'            => self::status_for( $assignment, $reason ),
			'payload'           => LessonPayload::encode( $lesson ),
			'synced_at'         => $now,
		);
	}

	/**
	 * Translates a matching outcome into the stored status.
	 *
	 * @param Assignment|null $assignment Assignment, when one was made.
	 * @param string          $reason     Reason code when none was.
	 * @return string
	 */
	private static function status_for( ?Assignment $assignment, string $reason ): string {
		if ( $assignment instanceof Assignment ) {
			return self::STATUS_MATCHED;
		}

		if ( 'no_candidate' === $reason ) {
			return self::STATUS_EXTERNAL;
		}

		if ( 'not_bookable' === $reason ) {
			return self::STATUS_NOT_BOOKABLE;
		}

		if ( 'makeup' === $reason ) {
			return self::STATUS_MAKEUP;
		}

		return '' === $reason ? '' : self::STATUS_UNRESOLVED;
	}

	/**
	 * Rebuilds occurrence objects from what is stored.
	 *
	 * The original record is kept alongside the columns precisely so that
	 * matching can be re-run later without asking the remote system again.
	 *
	 * @param int|null $since Only rows starting at or after this timestamp.
	 * @return array<int, Lesson>
	 */
	public function all( ?int $since = null ): array {
		global $wpdb;

		$table = Schema::lessons_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from $wpdb->prefix; the bound value is prepared.
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT payload FROM {$table} WHERE stamp_from >= %d", (int) $since ) );

		$records = array();

		foreach ( $rows as $payload ) {
			$decoded = json_decode( (string) $payload, true );

			if ( is_array( $decoded ) ) {
				$records[] = LessonPayload::to_api_shape( $decoded );
			}
		}

		return ( new Mapper() )->map_lessons( $records );
	}

	/**
	 * Deletes occurrences that started before a cut-off.
	 *
	 * @param int $before Timestamp.
	 * @return int Rows removed.
	 */
	public function purge_before( int $before ): int {
		global $wpdb;

		$table  = Schema::lessons_table();
		$doomed = $this->linked_before( $before );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from $wpdb->prefix; the bound value is prepared.
		$removed = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE stamp_from < %d", $before ) );

		$this->forget_links( $doomed );

		return $removed;
	}

	/**
	 * Returns the occurrences about to be purged that somebody linked by hand.
	 *
	 * Only the linked ones are looked up, and only when there is a link to lose,
	 * so retention costs one extra query on installations where a person has
	 * assigned something and none at all where nobody has.
	 *
	 * @param int $before Timestamp.
	 * @return array<int, int> Occurrence ids.
	 */
	private function linked_before( int $before ): array {
		global $wpdb;

		$linked = array_merge( array_keys( $this->manual_assignments() ), array_keys( $this->makeup_links() ) );

		if ( array() === $linked ) {
			return array();
		}

		$table        = Schema::lessons_table();
		$placeholders = implode( ', ', array_fill( 0, count( $linked ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- The plugin's own table: the table name comes from $wpdb->prefix and cannot be a placeholder, and every bound value is prepared.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id_activity_term FROM {$table} WHERE stamp_from < %d AND id_activity_term IN ( {$placeholders} )",
				array_merge( array( $before ), $linked )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	/**
	 * Drops the manual assignments and make-up links of purged occurrences.
	 *
	 * A link outlives the row it points at unless somebody removes it, and a
	 * purged occurrence is not coming back. Only the ids actually deleted are
	 * dropped: an id missing from the table may simply not have been retrieved
	 * yet, and forgetting that link would undo somebody's work.
	 *
	 * @param array<int, int> $term_ids Occurrence ids that were removed.
	 * @return void
	 */
	private function forget_links( array $term_ids ): void {
		if ( array() === $term_ids ) {
			return;
		}

		foreach ( array( self::MANUAL_OPTION, self::MAKEUP_OPTION ) as $option ) {
			$stored = get_option( $option, array() );

			if ( ! is_array( $stored ) || array() === $stored ) {
				continue;
			}

			$kept = array_diff_key( $stored, array_flip( $term_ids ) );

			if ( count( $kept ) !== count( $stored ) ) {
				update_option( $option, $kept, false );
			}
		}
	}

	/**
	 * Returns occurrences that should have found a course and did not.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function unmatched( int $limit = 200 ): array {
		return $this->by_status( self::STATUS_UNRESOLVED, $limit );
	}

	/**
	 * Returns occurrences with a given status.
	 *
	 * @param string $status One of the status constants.
	 * @param int    $limit  Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function by_status( string $status, int $limit = 200 ): array {
		global $wpdb;

		$table = Schema::lessons_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from $wpdb->prefix; the bound value is prepared.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- The plugin's own table: there is no core API for it, the table name comes from $wpdb->prefix and cannot be a placeholder, and the results are already served from the object cache one layer up.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id_activity_term, activity_name, lesson_date, time_from, tab_name, trainer_name, payload
				FROM {$table}
				WHERE status = %s
				ORDER BY stamp_from ASC
				LIMIT %d",
				$status,
				max( 1, $limit )
			),
			ARRAY_A
		);

		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		if ( ! is_array( $rows ) ) {
			return array();
		}

		// The tags say what the record is, which is the first thing anybody
		// looking at an unresolved occurrence wants to know.
		foreach ( $rows as $index => $row ) {
			$decoded = json_decode( (string) ( $row['payload'] ?? '' ), true );
			$tags    = is_array( $decoded ) && is_array( $decoded['tags'] ?? null ) ? $decoded['tags'] : array();

			$rows[ $index ]['tags'] = implode( ', ', array_map( 'strval', $tags ) );

			unset( $rows[ $index ]['payload'] );
		}

		return $rows;
	}

	/**
	 * Returns the ids of the make-up occurrences nobody has tied to a course.
	 *
	 * Kept separate from the listing because the admin menu asks for this on
	 * every page load to show a count, and the listing decodes payloads and
	 * reads names to do work this does not need.
	 *
	 * @return array<int, int> Occurrence ids.
	 */
	public function unlinked_makeup_terms(): array {
		global $wpdb;

		$table = Schema::lessons_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- The plugin's own table: the table name comes from $wpdb->prefix and cannot be a placeholder, and the bound value is prepared.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id_activity_term FROM {$table} WHERE status = %s ORDER BY stamp_from ASC",
				self::STATUS_MAKEUP
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		if ( ! is_array( $ids ) ) {
			return array();
		}

		return array_values( array_diff( array_map( 'intval', $ids ), array_keys( $this->makeup_links() ) ) );
	}

	/**
	 * Returns one stored occurrence, or an empty array when there is no such id.
	 *
	 * @param int $term_id Occurrence id.
	 * @return array<string, mixed>
	 */
	public function find( int $term_id ): array {
		global $wpdb;

		$table = Schema::lessons_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- The plugin's own table: the table name comes from $wpdb->prefix and cannot be a placeholder, and the bound value is prepared.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id_activity_term, id_course, activity_name, lesson_date, time_from, time_to, tab_name, trainer_name, price, status
				FROM {$table}
				WHERE id_activity_term = %d",
				$term_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return is_array( $row ) ? $row : array();
	}

	/**
	 * Returns the occurrences of one course.
	 *
	 * @param int      $course_id Course id.
	 * @param int      $limit     Maximum rows.
	 * @param int|null $since     Earliest start, or null for everything stored.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_course( int $course_id, int $limit = 200, ?int $since = null ): array {
		global $wpdb;

		$table = Schema::lessons_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- The plugin's own table: the table name comes from $wpdb->prefix and cannot be a placeholder, and every bound value is prepared.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id_course = %d AND stamp_from >= %d ORDER BY stamp_from ASC LIMIT %d",
				$course_id,
				null === $since ? 0 : $since,
				max( 1, $limit )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Returns several occurrences by id, in the order they run.
	 *
	 * @param array<int, int> $term_ids Occurrence ids.
	 * @return array<int, array<string, mixed>>
	 */
	public function find_many( array $term_ids ): array {
		global $wpdb;

		$term_ids = array_values( array_unique( array_filter( array_map( 'intval', $term_ids ) ) ) );

		if ( array() === $term_ids ) {
			return array();
		}

		$table        = Schema::lessons_table();
		$placeholders = implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- The plugin's own table: the table name comes from $wpdb->prefix and cannot be a placeholder, and every bound value is prepared.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id_activity_term IN ( {$placeholders} ) ORDER BY stamp_from ASC",
				$term_ids
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Returns every room the stored timetable mentions, keyed by its remote id.
	 *
	 * Rooms are not synchronised as a list of their own — the API has no such
	 * endpoint — so the only honest source is the timetable itself. A room that
	 * hosted nothing this term does not exist as far as the site is concerned.
	 *
	 * @return array<int, string> Room id to the name the timetable uses.
	 */
	public function rooms(): array {
		global $wpdb;

		$table = Schema::lessons_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- The plugin's own table: the table name comes from $wpdb->prefix and cannot be a placeholder, and no user input is interpolated.
		$rows = $wpdb->get_results(
			"SELECT id_tab, tab_name FROM {$table} WHERE id_tab > 0 GROUP BY id_tab, tab_name ORDER BY tab_name ASC",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$rooms = array();

		foreach ( $rows as $row ) {
			$id = (int) ( $row['id_tab'] ?? 0 );

			if ( 0 !== $id && ! isset( $rooms[ $id ] ) ) {
				$rooms[ $id ] = (string) ( $row['tab_name'] ?? '' );
			}
		}

		return $rooms;
	}

	/**
	 * Returns counts used by the overview screen.
	 *
	 * @return array<string, int>
	 */
	public function stats(): array {
		global $wpdb;

		$table = Schema::lessons_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from $wpdb->prefix; no user input is interpolated.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- The plugin's own table: there is no core API for it, the table name comes from $wpdb->prefix and cannot be a placeholder, and the results are already served from the object cache one layer up.
		$row = $wpdb->get_row(
			"SELECT
				COUNT(*) AS total,
				SUM(status = 'matched') AS matched,
				SUM(status = 'external') AS external,
				SUM(status = 'not_bookable') AS not_bookable,
				SUM(status = 'makeup') AS makeup,
				SUM(status = 'unresolved') AS unresolved,
				SUM(canceled = 1) AS canceled
			FROM {$table}",
			ARRAY_A
		);

		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return array_map( 'intval', is_array( $row ) ? $row : array() );
	}

	/**
	 * Returns the permanent manual assignments.
	 *
	 * @return array<int, int> Occurrence id to course id.
	 */
	public function manual_assignments(): array {
		$stored = get_option( self::MANUAL_OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$clean = array();

		foreach ( $stored as $term_id => $course_id ) {
			$clean[ (int) $term_id ] = (int) $course_id;
		}

		return $clean;
	}

	/**
	 * Records or clears a permanent manual assignment.
	 *
	 * @param int $term_id   Occurrence id.
	 * @param int $course_id Course id, or 0 to clear.
	 * @return void
	 */
	public function assign_manually( int $term_id, int $course_id ): void {
		$manual = $this->manual_assignments();

		if ( 0 === $course_id ) {
			unset( $manual[ $term_id ] );
		} else {
			$manual[ $term_id ] = $course_id;
		}

		update_option( self::MANUAL_OPTION, $manual, false );
	}

	/**
	 * Returns the course each make-up occurrence stands in for.
	 *
	 * Keyed by occurrence, not by name, and that distinction was learned the
	 * hard way. "Náhradní lekce 4-6 let I. pololetí" is not one make-up lesson
	 * repeating weekly: the gym reuses that name for the make-up slot of any
	 * course in the age group, and it runs twelve of them. Two occurrences
	 * sharing a name can belong to two different courses, so a link recorded
	 * against the name would be right once and wrong the rest of the time.
	 *
	 * @return array<int, int> Occurrence id to course id.
	 */
	public function makeup_links(): array {
		$stored = get_option( self::MAKEUP_OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$links = array();

		foreach ( $stored as $term_id => $course_id ) {
			$term_id   = (int) $term_id;
			$course_id = (int) $course_id;

			if ( 0 !== $term_id && 0 !== $course_id ) {
				$links[ $term_id ] = $course_id;
			}
		}

		return $links;
	}

	/**
	 * Records or clears the course one make-up occurrence stands in for.
	 *
	 * @param int $term_id   Occurrence id.
	 * @param int $course_id Course id, or 0 to clear the link.
	 * @return bool False when the occurrence id is unusable.
	 */
	public function link_makeup( int $term_id, int $course_id ): bool {
		if ( 0 === $term_id ) {
			return false;
		}

		$links = $this->makeup_links();

		if ( 0 === $course_id ) {
			unset( $links[ $term_id ] );
		} else {
			$links[ $term_id ] = $course_id;
		}

		update_option( self::MAKEUP_OPTION, $links, false );

		return true;
	}

	/**
	 * Returns the make-up occurrences that stand in for a course.
	 *
	 * @param int $course_id Course id.
	 * @return array<int, int> Occurrence ids.
	 */
	public function makeup_terms_for_course( int $course_id ): array {
		return array_keys(
			array_filter(
				$this->makeup_links(),
				static fn( int $id ): bool => $id === $course_id
			)
		);
	}
}
