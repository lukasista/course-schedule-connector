<?php
/**
 * Matching of class occurrences to courses.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Sync;

defined( 'ABSPATH' ) || exit;

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
	 * Loose keys of activities that never have a course to belong to.
	 *
	 * @var array<int, string>
	 */
	private array $non_bookable;

	/**
	 * Tag label to category, lowercased.
	 *
	 * @var array<string, string>
	 */
	private array $tag_categories;

	/**
	 * Loose keys of activities that are make-up lessons.
	 *
	 * @var array<int, string>
	 */
	private array $makeup;

	/**
	 * Constructor.
	 *
	 * @param array<int, string>    $non_bookable   Names of activities that take no bookings. Compared as
	 *                                              substrings of the accent-stripped key, so "Zdravé cvičení"
	 *                                              also covers "Zdravé cvičení s overbaly".
	 * @param array<string, string> $tag_categories Tag label to category: course, external_course, makeup or rental.
	 * @param array<int, string>    $makeup         Names that mark a make-up lesson, compared the same loose way.
	 */
	public function __construct( array $non_bookable = array(), array $tag_categories = array(), array $makeup = array() ) {
		$this->tag_categories = $tag_categories;
		$this->makeup         = $this->to_keys( $makeup );
		$this->non_bookable   = $this->to_keys( $non_bookable );
	}

	/**
	 * Reduces a list of names to comparable keys.
	 *
	 * @param array<int, string> $names Names.
	 * @return array<int, string>
	 */
	private function to_keys( array $names ): array {
		return array_values(
			array_filter(
				array_map(
					static fn( $name ): string => Normalise::match_key_loose( (string) $name ),
					$names
				),
				static fn( string $key ): bool => '' !== $key
			)
		);
	}

	/**
	 * Matches occurrences to courses.
	 *
	 * @param array<int, Course> $courses Courses keyed by course id.
	 * @param array<int, Lesson> $lessons Class occurrences keyed by occurrence id.
	 * @param array<int, int>    $manual  Permanent manual assignments, occurrence id to course id.
	 * @param array<int, int>    $known   Ids of courses that exist outside the API list — the ones a
	 *                                    person created by hand. They are honoured for a manual
	 *                                    assignment and deliberately not indexed by name, because
	 *                                    they carry no term list and would fail every timestamp check.
	 * @return MatchResult
	 */
	public function match( array $courses, array $lessons, array $manual = array(), array $known = array() ): MatchResult {
		$known = array_flip( array_map( 'intval', $known ) );

		$by_strict = $this->index( $courses, false );
		$by_loose  = $this->index( $courses, true );

		$assignments = array();
		$unmatched   = array();
		$rooms       = array();

		foreach ( $lessons as $lesson ) {
			$assignment = $this->match_one( $lesson, $courses, $by_strict, $by_loose, $manual, $known );

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
	 * @param array<int, int>                $known     Ids of hand-made courses, as a lookup.
	 * @return Assignment|string An assignment, or a reason code explaining the failure.
	 */
	private function match_one( Lesson $lesson, array $courses, array $by_strict, array $by_loose, array $manual, array $known ) {
		if ( isset( $manual[ $lesson->id_term ] ) ) {
			$course_id = $manual[ $lesson->id_term ];

			return isset( $courses[ $course_id ] ) || isset( $known[ $course_id ] )
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
			return $this->classify_unmatched( $lesson );
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
	 * Decides what an occurrence with no matching course actually is.
	 *
	 * The curated list of names is asked first, and deliberately so. In this
	 * installation the tags do not separate these cases: an outside lecturer's
	 * course carries "Pronájem haly" in one place and "Open lekce" in another,
	 * and the gym also rents halls to the public under the same label. A tag
	 * that means two things cannot overrule a list that means one.
	 *
	 * The tag map then handles everything the list does not name, and would
	 * take over entirely if the remote system were ever tagged consistently.
	 * Only when both are silent does the last-resort guess from a trainer and a
	 * price decide.
	 *
	 * @param Lesson $lesson Occurrence.
	 * @return string Reason code.
	 */
	private function classify_unmatched( Lesson $lesson ): string {
		// Asked before the non-bookable list, because a make-up lesson is also
		// something nobody books, and the more specific answer is the useful one.
		if ( $this->matches_any( $lesson, $this->makeup ) ) {
			return 'makeup';
		}

		if ( $this->matches_any( $lesson, $this->non_bookable ) ) {
			return 'not_bookable';
		}

		switch ( $this->category_from_tags( $lesson ) ) {
			case 'rental':
				return 'no_candidate';
			case 'external_course':
				return 'not_bookable';
			case 'makeup':
				return 'makeup';
		}

		return $this->expects_course( $lesson ) ? 'orphan' : 'no_candidate';
	}

	/**
	 * Returns the configured category of an occurrence's tags.
	 *
	 * @param Lesson $lesson Occurrence.
	 * @return string Category, or an empty string when no tag is configured.
	 */
	private function category_from_tags( Lesson $lesson ): string {
		foreach ( $lesson->tags as $tag ) {
			$label = $this->lower( trim( $tag ) );

			if ( isset( $this->tag_categories[ $label ] ) ) {
				return $this->tag_categories[ $label ];
			}
		}

		return '';
	}

	/**
	 * Whether an activity is one that takes no bookings.
	 *
	 * Some activities occupy a slot in the timetable without accepting payments
	 * or registrations: courses run by outside lecturers, make-up lessons, and
	 * individual training arranged directly. They are tagged as courses, because
	 * that is what they are, but no course record will ever exist for them, so
	 * counting them as failures buries the ones that matter.
	 *
	 * They cannot be told apart from the data alone, so the list is maintained
	 * by the site owner. Comparison is by substring of the accent-stripped key,
	 * because the timetable spells them loosely: the schedule says "Zdravé
	 * cvičení s overbaly" where the price list says "Zdravé cvičení (overbaly)".
	 *
	 * @param Lesson             $lesson  Occurrence.
	 * @param array<int, string> $needles Loose keys to look for.
	 * @return bool
	 */
	private function matches_any( Lesson $lesson, array $needles ): bool {
		if ( array() === $needles ) {
			return false;
		}

		$key = Normalise::match_key_loose( $lesson->activity_name );

		foreach ( $needles as $needle ) {
			if ( str_contains( $key, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether an occurrence that matched no course was ever meant to.
	 *
	 * The API already answers this. Every course lesson carries a tag labelled
	 * "Kurz", and hall rentals carry "Pronajem haly" instead, so the tag says
	 * outright what the record is. Guessing from the absence of a trainer and a
	 * price, which is what this used to do, misfiles a drop-in class or a
	 * make-up lesson as a problem: those have both, and belong to no course by
	 * design.
	 *
	 * The tag identifier is not stable between installations, so the label is
	 * what is compared. Where a record carries no tags at all, the old heuristic
	 * still decides.
	 *
	 * @param Lesson $lesson Occurrence.
	 * @return bool True when the occurrence should have found a course.
	 */
	private function expects_course( Lesson $lesson ): bool {
		/**
		 * Filters the tag labels that mark an occurrence as part of a course.
		 *
		 * @since 0.2.0
		 *
		 * @param array<int, string> $labels Lowercase labels.
		 */
		$labels = apply_filters( 'cscs_course_lesson_tags', array( 'kurz', 'course' ) );

		if ( array() !== $lesson->tags ) {
			foreach ( $lesson->tags as $tag ) {
				if ( in_array( $this->lower( $tag ), $labels, true ) ) {
					return true;
				}
			}

			return false;
		}

		return '' !== $lesson->trainer_name && null !== $lesson->price;
	}

	/**
	 * Lowercases a string, multibyte-safe.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function lower( string $value ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
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
			foreach ( $this->keys_for( $course, $loose ) as $key ) {
				if ( in_array( $course->id, $index[ $key ] ?? array(), true ) ) {
					continue;
				}

				$index[ $key ][] = $course->id;
			}
		}

		return $index;
	}

	/**
	 * Returns every key a course can be recognised by.
	 *
	 * @param Course $course Course.
	 * @param bool   $loose  Build accent-stripped keys.
	 * @return array<int, string>
	 */
	private function keys_for( Course $course, bool $loose ): array {
		$names = array( $course->activity_name, $course->name );
		$keys  = array();

		foreach ( $names as $name ) {
			if ( '' === $name ) {
				continue;
			}

			$key = $loose ? Normalise::match_key_loose( $name ) : Normalise::match_key( $name );

			if ( '' !== $key && ! in_array( $key, $keys, true ) ) {
				$keys[] = $key;
			}
		}

		return $keys;
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
