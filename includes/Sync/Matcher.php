<?php
/**
 * Matching of class occurrences to courses.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Sync;

use CSCS\Api\Dto\Course;
use CSCS\Api\Dto\Lesson;
use CSCS\Support\Normalise;

/**
 * Ties each class occurrence to the course it belongs to.
 *
 * The API offers no shared identifier. The courses endpoint carries no activity
 * id and the activities endpoint carries no course id, and the activity id is
 * not the identity of a course at all: on 11 September 2026 the same activity
 * id appears once as one course at 14:00 and again as a different course at
 * 15:00. It identifies a slot in the timetable, not a course.
 *
 * What the two endpoints do share is a name and a timestamp. Names are not
 * byte-identical between them — one side writes "113- Deskove hry" and the
 * other "113-Deskove hry" — so the name is reduced to a key with all whitespace
 * removed, and the timestamp is then required to line up with one of the
 * course's own terms. Two independent signals agreeing makes a false positive
 * effectively impossible.
 *
 * This class touches neither WordPress nor the database, so its behaviour can
 * be proven in tests rather than asserted in a comment.
 */
final class Matcher {

	/**
	 * Matches occurrences to courses.
	 *
	 * @param array<int, Course> $courses Courses keyed by course id.
	 * @param array<int, Lesson> $lessons Class occurrences keyed by occurrence id.
	 * @param array<int, int>    $manual  Permanent manual assignments, occurrence id to course id.
	 * @return MatchResult
	 */
	public function match( array $courses, array $lessons, array $manual = array() ): MatchResult {
		$by_strict = $this->index( $courses, false );
		$by_loose  = $this->index( $courses, true );

		$assignments = array();
		$unmatched   = array();
		$rooms       = array();

		foreach ( $lessons as $lesson ) {
			$assignment = $this->match_one( $lesson, $courses, $by_strict, $by_loose, $manual );

			if ( $assignment instanceof Assignment ) {
				$assignments[ $lesson->id_term ] = $assignment;

				if ( 0 !== $lesson->id_tab ) {
					$rooms[ $assignment->course_id ][ $lesson->id_tab ] = $lesson->tab_name;
				}

				continue;
			}

			$unmatched[ $lesson->id_term ] = array(
				'reason' => $assignment,
				'name'   => $lesson->activity_name,
			);
		}

		foreach ( $rooms as &$room_list ) {
			ksort( $room_list );
		}

		unset( $room_list );

		return new MatchResult( $assignments, $unmatched, $rooms );
	}

	/**
	 * Matches one occurrence.
	 *
	 * @param Lesson                         $lesson    Occurrence.
	 * @param array<int, Course>             $courses   Courses keyed by id.
	 * @param array<string, array<int, int>> $by_strict Strict key to course ids.
	 * @param array<string, array<int, int>> $by_loose  Loose key to course ids.
	 * @param array<int, int>                $manual    Manual assignments.
	 * @return Assignment|string An assignment, or a reason code explaining the failure.
	 */
	private function match_one( Lesson $lesson, array $courses, array $by_strict, array $by_loose, array $manual ) {
		if ( isset( $manual[ $lesson->id_term ] ) ) {
			$course_id = $manual[ $lesson->id_term ];

			return isset( $courses[ $course_id ] )
				? new Assignment( $lesson->id_term, $course_id, Assignment::MANUAL )
				: 'manual_course_missing';
		}

		$candidates = $by_strict[ $lesson->match_key ] ?? array();
		$loose      = false;

		if ( array() === $candidates ) {
			$candidates = $by_loose[ Normalise::match_key_loose( $lesson->activity_name ) ] ?? array();
			$loose      = true;
		}

		if ( array() === $candidates ) {
			return $this->looks_external( $lesson ) ? 'no_candidate' : 'orphan';
		}

		$on_stamp = array_values(
			array_filter(
				$candidates,
				fn( int $course_id ): bool => $this->has_term( $courses[ $course_id ], $lesson->stamp_from )
			)
		);

		if ( 1 === count( $on_stamp ) ) {
			return new Assignment(
				$lesson->id_term,
				$on_stamp[0],
				$loose ? Assignment::LOOSE : Assignment::STRICT
			);
		}

		if ( count( $on_stamp ) > 1 ) {
			return 'ambiguous';
		}

		// The name matched but no course lists this exact term. A course whose
		// dates still contain the occurrence is the only remaining reading, and
		// only when exactly one course qualifies.
		$in_range = array_values(
			array_filter(
				$candidates,
				fn( int $course_id ): bool => $this->within_dates( $courses[ $course_id ], $lesson->stamp_from )
			)
		);

		if ( 1 === count( $in_range ) ) {
			return new Assignment( $lesson->id_term, $in_range[0], Assignment::RANGE );
		}

		return count( $in_range ) > 1 ? 'ambiguous' : 'stamp_mismatch';
	}

	/**
	 * Whether an occurrence that matched no course was ever meant to.
	 *
	 * Hall rentals and open public sessions carry neither a trainer nor a price,
	 * because nobody books a place in them through the course system. A leftover
	 * that does carry both is a different animal: it looks exactly like a course
	 * lesson whose course is missing, and that deserves someone's attention
	 * rather than being filed away as "not our problem".
	 *
	 * @param Lesson $lesson Occurrence.
	 * @return bool
	 */
	private function looks_external( Lesson $lesson ): bool {
		$external = '' === $lesson->trainer_name && null === $lesson->price;

		/**
		 * Filters whether an unmatched occurrence counts as an external booking.
		 *
		 * @since 0.2.0
		 *
		 * @param bool   $external Whether the occurrence is external.
		 * @param Lesson $lesson   The occurrence.
		 */
		return (bool) apply_filters( 'cscs_is_external_lesson', $external, $lesson );
	}

	/**
	 * Builds a name key to course id index.
	 *
	 * @param array<int, Course> $courses Courses keyed by id.
	 * @param bool               $loose   Build the accent-stripped index.
	 * @return array<string, array<int, int>>
	 */
	private function index( array $courses, bool $loose ): array {
		$index = array();

		foreach ( $courses as $course ) {
			$key = $loose ? Normalise::match_key_loose( $course->activity_name ) : $course->match_key;

			if ( '' === $key ) {
				continue;
			}

			$index[ $key ][] = $course->id;
		}

		return $index;
	}

	/**
	 * Whether a course lists a term at exactly this timestamp.
	 *
	 * @param Course   $course Course.
	 * @param int|null $stamp  Timestamp.
	 * @return bool
	 */
	private function has_term( Course $course, ?int $stamp ): bool {
		if ( null === $stamp ) {
			return false;
		}

		return in_array( $stamp, $course->term_stamps(), true );
	}

	/**
	 * Whether a timestamp falls inside a course's own dates.
	 *
	 * @param Course   $course Course.
	 * @param int|null $stamp  Timestamp.
	 * @return bool
	 */
	private function within_dates( Course $course, ?int $stamp ): bool {
		if ( null === $stamp ) {
			return false;
		}

		$terms = $course->term_stamps();
		$from  = $course->stamp_from ?? ( array() === $terms ? null : min( $terms ) );
		$to    = $course->stamp_to ?? ( array() === $terms ? null : max( $terms ) );

		if ( null === $from || null === $to ) {
			return false;
		}

		return $stamp >= $from && $stamp <= $to;
	}
}
