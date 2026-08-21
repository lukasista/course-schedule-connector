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
				$assignment = $outcome instanceof MatchResult ? ( $outcome->assignments[ $lesson->id_term ] ?? null ) : null;
				$reason     = $outcome instanceof MatchResult ? ( $outcome->unmatched[ $lesson->id_term ]['reason'] ?? '' ) : '';

				$placeholders[] = '(%d,%d,%s,%s,%s,%s,%d,%d,%s,%s,%s,%d,%s,%d,%s,%d,%s,%s,%d,%d,%d,%d,%d,%d,%d,%d,%s,%d)';

				array_push(
					$rows,
					$lesson->id_term,
					$lesson->id_activity,
					$assignment instanceof Assignment ? (string) $assignment->course_id : null,
					$assignment instanceof Assignment ? $assignment->method : '',
					$lesson->activity_name,
					$lesson->match_key,
					(int) $lesson->stamp_from,
					(int) $lesson->stamp_to,
					$lesson->date,
					(string) $lesson->time_from,
					(string) $lesson->time_to,
					$lesson->id_tab,
					$lesson->tab_name,
					$lesson->id_lane,
					$lesson->lane_name,
					$lesson->id_trainer,
					$lesson->trainer_name,
					$lesson->price,
					$lesson->capacity,
					$lesson->capacity_waiting,
					$lesson->occupied,
					$lesson->available,
					$lesson->available_waiting,
					$lesson->canceled ? 1 : 0,
					$lesson->booking_allowed ? 1 : 0,
					'no_candidate' === $reason ? 1 : 0,
					LessonPayload::encode( $lesson ),
					$now
				);
			}

			$sql = 'INSERT INTO ' . $table . ' (
				id_activity_term, id_activity, id_course, match_method, activity_name, match_key,
				stamp_from, stamp_to, lesson_date, time_from, time_to, id_tab, tab_name,
				id_lane, lane_name, id_trainer, trainer_name, price, capacity, capacity_waiting,
				occupied, available, available_waiting, canceled, booking_allowed, is_external,
				payload, synced_at
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

		$table = Schema::lessons_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from $wpdb->prefix; the bound value is prepared.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE stamp_from < %d", $before ) );
	}

	/**
	 * Returns occurrences that belong to no course and are not external bookings.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function unmatched( int $limit = 200 ): array {
		global $wpdb;

		$table = Schema::lessons_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from $wpdb->prefix; the bound value is prepared.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- The plugin's own table: there is no core API for it, the table name comes from $wpdb->prefix and cannot be a placeholder, and the results are already served from the object cache one layer up.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id_activity_term, activity_name, lesson_date, time_from, tab_name, trainer_name
				FROM {$table}
				WHERE id_course IS NULL AND is_external = 0
				ORDER BY stamp_from ASC
				LIMIT %d",
				max( 1, $limit )
			),
			ARRAY_A
		);

		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return is_array( $rows ) ? $rows : array();
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
				SUM(id_course IS NOT NULL) AS matched,
				SUM(is_external = 1) AS external,
				SUM(id_course IS NULL AND is_external = 0) AS unmatched,
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
}
