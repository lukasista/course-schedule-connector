<?php
/**
 * What kind of course a course is.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Data;

use CSCS\Support\Normalise;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the kind of course — what a page would call the card — out of its name.
 *
 * The site this plugin replaces publishes one table per kind: "Gymnastika",
 * "Jojo přípravka", "Lezení", "Gymnastika pro radost". Nothing in the remote
 * data says which is which. iSport does send an activity, but it sends the
 * whole course name as one, so the activity taxonomy holds one term per course
 * and groups exactly nothing.
 *
 * What the names do have is a shape, kept by whoever types them:
 *
 *     113-Deskové hry 9-13 let I. pololetí
 *     ^^^ ^^^^^^^^^^^ ^^^^^^^^ ^^^^^^^^^^^
 *     nr. kind        age      term
 *
 * with an audience and a level allowed between the age and the term. So the
 * kind is everything before the first thing that is not the kind: the course's
 * own number is cut from the front, and the name is cut at the earliest of an
 * age, an audience word, a level word or the term — whichever comes first.
 *
 * The words are the ones {@see Audience} already knows, and the cut is made on
 * whole words against a name with its accents and its case flattened, so
 * "mix" does not cut "Mixáž" in half. What is left is trimmed of the dashes and
 * commas a cut can leave behind.
 *
 * Nothing is guessed beyond that. "Gymnastika pro dospělé" is its own kind
 * rather than "Gymnastika" for adults, because the name says so and because
 * merging by hand is possible where un-merging is not.
 */
final class CourseKind {

	/**
	 * Reads the kind out of a course's name.
	 *
	 * @param string $name Course name.
	 * @return string Kind, or an empty string when the name is only a number.
	 */
	public static function read( string $name ): string {
		// The course's own number, which iSport puts in front of every name and
		// which changes from term to term.
		$name  = (string) preg_replace( '/^\s*\d+\s*-\s*/u', '', trim( $name ) );
		$plain = Normalise::plain( $name );
		$cut   = self::first_cut( $plain );
		$kind  = mb_substr( $name, 0, mb_strlen( substr( $plain, 0, $cut ) ) );

		return trim( (string) preg_replace( '/[\s\-–—,;:]+$/u', '', $kind ) );
	}

	/**
	 * Returns where the kind stops in a flattened name.
	 *
	 * @param string $plain Name, flattened.
	 * @return int Offset in bytes.
	 */
	private static function first_cut( string $plain ): int {
		$cuts = array( strlen( $plain ) );

		foreach ( self::patterns() as $pattern ) {
			if ( 1 === preg_match( $pattern, $plain, $found, PREG_OFFSET_CAPTURE ) ) {
				$cuts[] = (int) $found[0][1];
			}
		}

		return min( $cuts );
	}

	/**
	 * Returns everything that marks the end of a kind.
	 *
	 * @return array<int, string>
	 */
	private static function patterns(): array {
		$number = '(?:\d+(?:[.,]\d+)?)';
		$unit   = '(?:let|rok[uy]?)';

		$patterns = array(
			// An age, in each of the three shapes a name writes one.
			'/' . $number . '\s*[-–—]\s*' . $number . '\s*' . $unit . '(?![a-z0-9])/u',
			'/(?<![a-z0-9])od\s+' . $number . '\s*' . $unit . '(?![a-z0-9])/u',
			'/' . $number . '\s*' . $unit . '(?![a-z0-9])/u',
			// The term, with or without the space nobody types consistently.
			'/(?<![a-z0-9])[ivx]+\.\s*pololeti(?![a-z0-9])/u',
			'/(?<![a-z0-9])pololeti(?![a-z0-9])/u',
		);

		foreach ( self::words() as $word ) {
			$patterns[] = '/(?<![a-z0-9])' . preg_quote( $word, '/' ) . '(?![a-z0-9])/u';
		}

		/**
		 * Filters what marks the end of the kind in a course's name.
		 *
		 * Each entry is a regular expression run against the name with its
		 * accents removed and its case flattened; the earliest match wins.
		 *
		 * @since 0.6.0
		 *
		 * @param array<int, string> $patterns Patterns.
		 */
		return (array) apply_filters( 'cscs_course_kind_patterns', $patterns );
	}

	/**
	 * Returns the words that are never part of a kind.
	 *
	 * The audience and the level vocabularies, flattened into one list: they
	 * are already the words that follow a kind, and keeping one list means a
	 * gym that adds a word for "girls" does not have to add it twice.
	 *
	 * @return array<int, string>
	 */
	private static function words(): array {
		$words = array();

		foreach ( array( Audience::gender_words(), Audience::level_words() ) as $group ) {
			foreach ( $group as $word ) {
				$words[] = $word;
			}
		}

		return $words;
	}
}
