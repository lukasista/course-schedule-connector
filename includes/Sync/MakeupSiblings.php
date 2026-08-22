<?php
/**
 * Proposes the course of a make-up lesson from its identical siblings.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Sync;

use CSCS\Support\Normalise;

defined( 'ABSPATH' ) || exit;

/**
 * Reads one make-up occurrence's course off the ones that repeat with it.
 *
 * A make-up slot is a weekly fixture: the same name, the same weekday, the same
 * hour, the same room, week after week. When somebody has already said which
 * course one of those belongs to, the rest of the series is very probably the
 * same course — and saying so beats picking a course out of a hundred-item list
 * a dozen times over.
 *
 * "Very probably" is the whole reason nothing here writes anything. It hands a
 * proposal to a person, who presses the button or does not. Two rules keep it
 * honest:
 *
 * - the siblings must agree. Where two occurrences of one series were assigned
 *   to two different courses, the series is not a series and nothing is
 *   proposed.
 * - the trainer is deliberately not part of the key. The timetable leaves it
 *   blank on some weeks and fills it in on others, and a proposal that
 *   disappeared because a name was typed in one week would be worse than
 *   useless. It is shown on screen instead, where a person can weigh it.
 *
 * This class touches neither WordPress nor the database.
 */
final class MakeupSiblings {

	/**
	 * Proposes a course for each unassigned occurrence.
	 *
	 * @param array<int, array<string, mixed>> $occurrences Stored make-up rows.
	 * @param array<int, int>                  $links       Occurrence id to course id.
	 * @return array<int, int> Occurrence id to the course its siblings agree on.
	 */
	public function propose( array $occurrences, array $links ): array {
		$series = array();

		foreach ( $occurrences as $row ) {
			$term_id = (int) ( $row['id_activity_term'] ?? 0 );
			$key     = $this->series_key( $row );

			if ( 0 === $term_id || '' === $key ) {
				continue;
			}

			$course_id = (int) ( $links[ $term_id ] ?? 0 );

			if ( 0 === $course_id ) {
				$series[ $key ]['open'][] = $term_id;

				continue;
			}

			$series[ $key ]['courses'][ $course_id ] = $course_id;
		}

		$proposals = array();

		foreach ( $series as $group ) {
			$courses = $group['courses'] ?? array();

			if ( 1 !== count( $courses ) ) {
				continue;
			}

			foreach ( $group['open'] ?? array() as $term_id ) {
				$proposals[ $term_id ] = (int) reset( $courses );
			}
		}

		return $proposals;
	}

	/**
	 * Builds the key that decides which occurrences belong to one series.
	 *
	 * Weekday rather than date, because that is what makes a weekly fixture the
	 * same fixture. Two different weekdays under one name are two slots, and
	 * two slots may well serve two courses.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @return string Empty when the row cannot be placed in a series.
	 */
	public function series_key( array $row ): string {
		$name = Normalise::match_key( (string) ( $row['activity_name'] ?? '' ) );
		$date = (string) ( $row['lesson_date'] ?? '' );
		$time = substr( (string) ( $row['time_from'] ?? '' ), 0, 5 );
		$room = Normalise::match_key( (string) ( $row['tab_name'] ?? '' ) );

		if ( '' === $name || '' === $time || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return '';
		}

		// Built in UTC from the parts, and read back in UTC. A timestamp made
		// in one zone and read in another lands on the wrong weekday for a few
		// hours a day, which is exactly the kind of bug nobody reproduces.
		$parts = array_map( 'intval', explode( '-', $date ) );
		$stamp = gmmktime( 12, 0, 0, $parts[1], $parts[2], $parts[0] );

		if ( false === $stamp ) {
			return '';
		}

		return implode( '|', array( $name, gmdate( 'N', $stamp ), $time, $room ) );
	}
}
