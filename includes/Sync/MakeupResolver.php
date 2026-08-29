<?php
/**
 * Suggests which course a make-up lesson replaces a class for.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Sync;

defined( 'ABSPATH' ) || exit;

use CSCS\Api\Dto\Course;
use CSCS\Support\Normalise;

/**
 * Reads a course name out of a make-up lesson's own name, where there is one.
 *
 * Today the timetable calls these things "Náhradní lekce 4-6 let", which names
 * an age group rather than a course, so nothing can be inferred and a person
 * records the link by hand. If they are ever renamed to carry the course —
 * "Náhradní lekce 37-Gymnastika 9-11 let dívky" — the answer is sitting in the
 * name, and this reads it.
 *
 * A suggestion is only offered when exactly one course fits. Two candidates
 * mean the name is ambiguous, and a wrong guess quietly shown next to the wrong
 * course is worse than no guess at all.
 *
 * Nothing here writes anything: a suggestion is shown to a person, who decides.
 * This class touches neither WordPress nor the database.
 */
final class MakeupResolver {

	/**
	 * Shortest course key that may be looked for inside a make-up lesson's name.
	 *
	 * A short name such as "Barre" would turn up inside unrelated words often
	 * enough to be worse than useless.
	 */
	private const MIN_KEY_LENGTH = 8;

	/**
	 * Suggests a course for each make-up lesson name.
	 *
	 * @param array<int, string> $activity_names Activity names of make-up lessons.
	 * @param array<int, Course> $courses        Courses keyed by course id.
	 * @return array<string, int> Match key to the single course that fits.
	 */
	public function suggest( array $activity_names, array $courses ): array {
		$names = array();

		foreach ( $courses as $course ) {
			$names[ $course->id ] = array( $course->activity_name, $course->name );
		}

		return $this->suggest_by_names( $activity_names, $names );
	}

	/**
	 * Suggests a course for each make-up lesson name, from names alone.
	 *
	 * The admin screens read the course list out of what is stored rather than
	 * from the remote system, so they hold names and ids and no records. That is
	 * all this needs.
	 *
	 * @param array<int, string>                          $activity_names Activity names of make-up lessons.
	 * @param array<int, string|array<int, string>>       $course_names   Course id to its name, or to its names.
	 * @return array<string, int> Match key of the lesson to the single course that fits.
	 */
	public function suggest_by_names( array $activity_names, array $course_names ): array {
		$suggestions = array();

		foreach ( $activity_names as $name ) {
			$key        = Normalise::match_key_loose( (string) $name );
			$candidates = array();

			if ( '' === $key ) {
				continue;
			}

			foreach ( $course_names as $course_id => $candidate_names ) {
				if ( $this->names_fit( $key, (array) $candidate_names ) ) {
					$candidates[] = (int) $course_id;
				}
			}

			$candidates = array_values( array_unique( $candidates ) );

			if ( 1 === count( $candidates ) ) {
				$suggestions[ Normalise::match_key( (string) $name ) ] = $candidates[0];
			}
		}

		return $suggestions;
	}

	/**
	 * Whether any of a course's names appears inside a make-up lesson's name.
	 *
	 * @param string             $makeup_key   Loose key of the make-up lesson.
	 * @param array<int, string> $course_names Names the course goes by.
	 * @return bool
	 */
	private function names_fit( string $makeup_key, array $course_names ): bool {
		foreach ( $course_names as $name ) {
			$course_key = Normalise::match_key_loose( (string) $name );

			if ( strlen( $course_key ) >= self::MIN_KEY_LENGTH && str_contains( $makeup_key, $course_key ) ) {
				return true;
			}
		}

		return false;
	}
}
