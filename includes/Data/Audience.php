<?php
/**
 * Who a course is for, and at what level.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Data;

defined( 'ABSPATH' ) || exit;

use CSCS\Support\Normalise;

/**
 * Reads the audience, the level and the ages out of a course's name.
 *
 * iSport publishes neither. There is no field for "girls" and none for
 * "advanced": both are words inside the course's name — "Gymnastika 9-11 let
 * dívky", "Lezení od 10 let mix mírně pokročilí" — and that is where the
 * website they replace reads them from too. So they are read from the name,
 * and an administrator can overrule the reading on any course; a course whose
 * name says nothing simply has neither, which is also true of a good number of
 * them.
 *
 * Reading rather than guessing: the vocabulary is a fixed list, matched whole
 * word against a name with its accents and its case flattened, so "mix" cannot
 * be found inside another word and a name written without diacritics is read
 * the same as one with them. Two filters open the lists, because a gym that
 * words things differently should not have to patch a plugin to say so.
 *
 * The stored value is a key, never a word. Which word it becomes is the
 * renderer's business, and in Czech it depends on who the course is for:
 * a group of girls has "začátečnice" where a mixed group has "začátečníci".
 * That is what the two-argument {@see self::level_label()} is for, and why the
 * English strings carry a context rather than standing alone.
 */
final class Audience {

	/**
	 * Audience keys.
	 */
	public const GIRLS = 'girls';
	public const BOYS  = 'boys';
	public const MIXED = 'mixed';
	public const WOMEN = 'women';
	public const MEN   = 'men';

	/**
	 * Level keys.
	 */
	public const BEGINNER    = 'beginner';
	public const IMPROVER    = 'improver';
	public const ADVANCED    = 'advanced';
	public const COMPETITIVE = 'competitive';

	/**
	 * Returns the audience keys, without translating anything.
	 *
	 * Validation asks what a key may be, not what it is called, and a set
	 * being checked on a REST request has no business loading a text domain.
	 *
	 * @return array<int, string>
	 */
	public static function gender_keys(): array {
		return array( self::GIRLS, self::BOYS, self::MIXED, self::WOMEN, self::MEN );
	}

	/**
	 * Returns the level keys, without translating anything.
	 *
	 * @return array<int, string>
	 */
	public static function level_keys(): array {
		return array( self::BEGINNER, self::IMPROVER, self::ADVANCED, self::COMPETITIVE );
	}

	/**
	 * Returns every audience, keyed by what is stored.
	 *
	 * @return array<string, string> Key to label.
	 */
	public static function genders(): array {
		return array(
			self::GIRLS => __( 'Girls', 'course-schedule-connector' ),
			self::BOYS  => __( 'Boys', 'course-schedule-connector' ),
			self::MIXED => __( 'Mixed', 'course-schedule-connector' ),
			self::WOMEN => __( 'Women', 'course-schedule-connector' ),
			self::MEN   => __( 'Men', 'course-schedule-connector' ),
		);
	}

	/**
	 * Returns every level, keyed by what is stored.
	 *
	 * The labels here are the ones a mixed or male group takes. A list of
	 * levels on its own — a settings screen, a filter — has no group to agree
	 * with, and this is the form Czech uses when there is none.
	 *
	 * @return array<string, string> Key to label.
	 */
	public static function levels(): array {
		return array(
			self::BEGINNER    => self::level_label( self::BEGINNER, self::MIXED ),
			self::IMPROVER    => self::level_label( self::IMPROVER, self::MIXED ),
			self::ADVANCED    => self::level_label( self::ADVANCED, self::MIXED ),
			self::COMPETITIVE => self::level_label( self::COMPETITIVE, self::MIXED ),
		);
	}

	/**
	 * Reads all three out of a course's name.
	 *
	 * @param string $name Course name.
	 * @return array{gender: string, level: string, age_from: string, age_to: string} Any of which may be empty.
	 */
	public static function read( string $name ): array {
		$plain = Normalise::plain( $name );
		$age   = self::read_age( $name );

		return array(
			'gender'   => self::first( $plain, self::gender_terms() ),
			'level'    => self::first( $plain, self::level_terms() ),
			'age_from' => $age['from'],
			'age_to'   => $age['to'],
		);
	}

	/**
	 * Reads the ages a course is for out of its name.
	 *
	 * Three shapes, asked in this order, because a name that fits two of them
	 * means the first: "9-11 let" is a range, "od 10 let" is a floor with no
	 * ceiling, and "4 roky" is one age. The unit is what tells an age from the
	 * course's own number — "101-Lezení od 10 let" has two numbers in it and
	 * only one of them is an age — so nothing without "let" or "rok" after it
	 * counts.
	 *
	 * Halves are real here: the gym runs courses for two-and-a-half-year-olds,
	 * written "2,5-3 roky". They are kept as a decimal with a full stop, which
	 * is what a database can compare; the comma is the renderer's business.
	 *
	 * @param string $name Course name.
	 * @return array{from: string, to: string} Either of which may be empty.
	 */
	public static function read_age( string $name ): array {
		$plain  = Normalise::plain( $name );
		$number = '(\d+(?:[.,]\d+)?)';
		$unit   = '(?:let|rok[uy]?)';

		if ( 1 === preg_match( '/' . $number . '\s*[-–—]\s*' . $number . '\s*' . $unit . '(?![a-z0-9])/u', $plain, $found ) ) {
			return array(
				'from' => self::number( $found[1] ),
				'to'   => self::number( $found[2] ),
			);
		}

		if ( 1 === preg_match( '/(?<![a-z0-9])od\s+' . $number . '\s*' . $unit . '(?![a-z0-9])/u', $plain, $found ) ) {
			return array(
				'from' => self::number( $found[1] ),
				'to'   => '',
			);
		}

		if ( 1 === preg_match( '/' . $number . '\s*' . $unit . '(?![a-z0-9])/u', $plain, $found ) ) {
			return array(
				'from' => self::number( $found[1] ),
				'to'   => self::number( $found[1] ),
			);
		}

		return array(
			'from' => '',
			'to'   => '',
		);
	}

	/**
	 * Returns an age as the one spelling everything else compares.
	 *
	 * @param string $value Number as the name wrote it.
	 * @return string
	 */
	private static function number( string $value ): string {
		$value = number_format( (float) str_replace( ',', '.', $value ), 1, '.', '' );

		return rtrim( rtrim( $value, '0' ), '.' );
	}

	/**
	 * Returns what one audience is called.
	 *
	 * @param string $gender Audience key.
	 * @return string
	 */
	public static function gender_label( string $gender ): string {
		$labels = self::genders();

		return $labels[ $gender ] ?? '';
	}

	/**
	 * Returns what one level is called, worded for the group it belongs to.
	 *
	 * Czech agrees the word with the group: a course for girls is one of
	 * "začátečnice", a mixed one of "začátečníci". English has one word for
	 * both, so the two readings are told apart by their context rather than by
	 * their text — which is exactly what a gettext context is for.
	 *
	 * @param string $level  Level key.
	 * @param string $gender Audience key the course carries, if any.
	 * @return string
	 */
	public static function level_label( string $level, string $gender = '' ): string {
		if ( self::feminine( $gender ) ) {
			$labels = array(
				self::BEGINNER    => _x( 'Beginners', 'level of a course for girls or women', 'course-schedule-connector' ),
				self::IMPROVER    => _x( 'Improvers', 'level of a course for girls or women', 'course-schedule-connector' ),
				self::ADVANCED    => _x( 'Advanced', 'level of a course for girls or women', 'course-schedule-connector' ),
				self::COMPETITIVE => _x( 'Competitive training', 'level of a course for girls or women', 'course-schedule-connector' ),
			);

			return $labels[ $level ] ?? '';
		}

		$labels = array(
			self::BEGINNER    => _x( 'Beginners', 'level of a course for a mixed or male group', 'course-schedule-connector' ),
			self::IMPROVER    => _x( 'Improvers', 'level of a course for a mixed or male group', 'course-schedule-connector' ),
			self::ADVANCED    => _x( 'Advanced', 'level of a course for a mixed or male group', 'course-schedule-connector' ),
			self::COMPETITIVE => _x( 'Competitive training', 'level of a course for a mixed or male group', 'course-schedule-connector' ),
		);

		return $labels[ $level ] ?? '';
	}

	/**
	 * Returns whether a group takes the feminine wording.
	 *
	 * @param string $gender Audience key.
	 * @return bool
	 */
	private static function feminine( string $gender ): bool {
		return in_array( $gender, array( self::GIRLS, self::WOMEN ), true );
	}

	/**
	 * Returns the first key whose words appear in a name.
	 *
	 * The lists are ordered, and the order is the point: "mírně pokročilí" has
	 * to be looked for before "pokročilí", or every improver is read as
	 * advanced.
	 *
	 * @param string                        $plain Name, flattened.
	 * @param array<string, array<int, string>> $terms Key to the words that mean it.
	 * @return string
	 */
	private static function first( string $plain, array $terms ): string {
		foreach ( $terms as $key => $words ) {
			foreach ( (array) $words as $word ) {
				$pattern = '/(?<![a-z0-9])' . preg_quote( (string) $word, '/' ) . '(?![a-z0-9])/u';

				if ( 1 === preg_match( $pattern, $plain ) ) {
					return (string) $key;
				}
			}
		}

		return '';
	}

	/**
	 * Returns every word that names an audience, flattened.
	 *
	 * For anything that needs the vocabulary rather than the reading — where
	 * the kind of course stops being the kind, for one.
	 *
	 * @return array<int, string>
	 */
	public static function gender_words(): array {
		return self::flatten( self::gender_terms() );
	}

	/**
	 * Returns every word that names a level, flattened.
	 *
	 * @return array<int, string>
	 */
	public static function level_words(): array {
		return self::flatten( self::level_terms() );
	}

	/**
	 * Returns the words out of a keyed vocabulary.
	 *
	 * @param array<string, array<int, string>> $terms Key to its words.
	 * @return array<int, string>
	 */
	private static function flatten( array $terms ): array {
		$words = array();

		foreach ( $terms as $group ) {
			foreach ( (array) $group as $word ) {
				$words[] = (string) $word;
			}
		}

		return $words;
	}

	/**
	 * Returns the words that name an audience.
	 *
	 * @return array<string, array<int, string>>
	 */
	private static function gender_terms(): array {
		$terms = array(
			self::GIRLS => array( 'divky', 'divci' ),
			self::BOYS  => array( 'kluci', 'chlapci', 'chlapecke' ),
			self::WOMEN => array( 'zeny', 'damy' ),
			self::MEN   => array( 'muzi', 'pani' ),
			self::MIXED => array( 'mix', 'smiseny', 'smisene' ),
		);

		/**
		 * Filters the words that say who a course is for.
		 *
		 * The words are compared against the course's name with its accents
		 * removed and its case flattened, so they are written that way here.
		 *
		 * @since 0.6.0
		 *
		 * @param array<string, array<int, string>> $terms Audience key to its words.
		 */
		return (array) apply_filters( 'cscs_course_gender_terms', $terms );
	}

	/**
	 * Returns the words that name a level, longest reading first.
	 *
	 * @return array<string, array<int, string>>
	 */
	private static function level_terms(): array {
		$terms = array(
			self::COMPETITIVE => array( 'zavodni pruprava', 'zavodni' ),
			self::IMPROVER    => array( 'mirne pokrocili', 'mirne pokrocile' ),
			self::ADVANCED    => array( 'pokrocili', 'pokrocile' ),
			self::BEGINNER    => array( 'zacatecnici', 'zacatecnice' ),
		);

		/**
		 * Filters the words that say what level a course is.
		 *
		 * Order matters: the first key whose words are found wins, which is why
		 * "mírně pokročilí" is looked for before "pokročilí".
		 *
		 * @since 0.6.0
		 *
		 * @param array<string, array<int, string>> $terms Level key to its words.
		 */
		return (array) apply_filters( 'cscs_course_level_terms', $terms );
	}
}
