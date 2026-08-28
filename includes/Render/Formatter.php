<?php
/**
 * Turning stored values into the words a visitor reads.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

defined( 'ABSPATH' ) || exit;

/**
 * The small decisions that make a listing readable, in one place.
 *
 * Every one of these was a question the site owner answered, and each answer is
 * kept here rather than in a template so that the shortcode, the block and the
 * builder module cannot drift apart: a price shown one way in one of them and
 * another way in the next is the kind of thing nobody reports and everybody
 * notices.
 *
 * Nothing here touches WordPress or the database, so the rules can be tested
 * as rules.
 */
final class Formatter {

	/**
	 * The space that must never be the place a line breaks.
	 *
	 * "1 960 Kč" broken across two lines is a different number at a glance.
	 */
	public const NBSP = "\u{00A0}";

	/**
	 * Renders a price the way the site owner asked for it.
	 *
	 * A thousands separator, no rounding, and no trailing `.00` — the courses
	 * cost round hundreds, and "1 960,00 Kč" reads like an invoice rather than
	 * a price. Non-zero decimals are kept, because dropping them would be a lie
	 * rather than a tidy-up.
	 *
	 * @param string|null $value    Stored price, or null when there is none.
	 * @param string      $empty    What to say when there is no price at all.
	 * @param string      $currency Currency suffix.
	 * @return string
	 */
	public static function price( ?string $value, string $empty, string $currency = 'Kč' ): string {
		$value = null === $value ? '' : trim( $value );

		if ( '' === $value || ! is_numeric( $value ) ) {
			return $empty;
		}

		$number = (float) $value;

		// A price of nothing is not a price of zero. iSport writes 0 where a
		// course is not sold at all, and "0 Kč" reads like a bargain rather
		// than like the absence the record means.
		if ( 0.0 === round( $number, 2 ) ) {
			return $empty;
		}

		$decimals = self::decimals( $number );
		$whole    = number_format( $number, $decimals, ',', self::NBSP );

		return '' === $currency ? $whole : $whole . self::NBSP . $currency;
	}

	/**
	 * How many decimal places a stored price is worth showing.
	 *
	 * Two or none: "1 960,5 Kč" is not how a price is written, so a price with
	 * any fraction at all is written with both places.
	 *
	 * @param float $value Stored price.
	 * @return int
	 */
	private static function decimals( float $value ): int {
		return 0.0 === round( $value - (float) (int) $value, 2 ) ? 0 : 2;
	}

	/**
	 * Describes how full a course is.
	 *
	 * Returns a state rather than a sentence, so that the wording stays in the
	 * template where a translator can reach it and the decision stays here.
	 *
	 * @param int $available Places left.
	 * @param int $few       Threshold at or below which few places are left, 0 to never say so.
	 * @return string One of `full`, `few` or `free`.
	 */
	public static function availability( int $available, int $few ): string {
		if ( $available <= 0 ) {
			return 'full';
		}

		return 0 !== $few && $available <= $few ? 'few' : 'free';
	}

	/**
	 * Decides whether a course shows its booking button.
	 *
	 * Three levels, in the order the site owner described them: the course's own
	 * answer wins over the site-wide default, and both give way to the facts —
	 * a full course or one iSport says takes no registrations shows no button,
	 * whatever anybody set. A button that leads to a booking that cannot happen
	 * is worse than no button.
	 *
	 * @param string $override        The course's own setting: `always`, `never` or `default`.
	 * @param bool   $default_on      The site-wide default.
	 * @param bool   $booking_allowed Whether iSport accepts registrations.
	 * @param int    $available       Places left.
	 * @return bool
	 */
	public static function shows_button( string $override, bool $default_on, bool $booking_allowed, int $available ): bool {
		if ( 'never' === $override ) {
			return false;
		}

		if ( ! $booking_allowed || $available <= 0 ) {
			return false;
		}

		return 'always' === $override ? true : $default_on;
	}

	/**
	 * Renders a time range.
	 *
	 * @param string $from Start, `HH:MM`.
	 * @param string $to   End, `HH:MM`.
	 * @return string
	 */
	public static function time_range( string $from, string $to ): string {
		$from = substr( trim( $from ), 0, 5 );
		$to   = substr( trim( $to ), 0, 5 );

		if ( '' === $from ) {
			return '';
		}

		return '' === $to ? $from : $from . self::NBSP . '–' . self::NBSP . $to;
	}

	/**
	 * Renders the ages a course is for.
	 *
	 * Three readings, three shapes: "9 – 11", "od 10" where there is no
	 * ceiling, and "4" where both ends are the same age. The decimal point the
	 * numbers are stored with becomes whatever the language writes — Czech
	 * writes two and a half as "2,5" — because this is the only place the
	 * number is read by a person.
	 *
	 * @param string $from Youngest age.
	 * @param string $to   Oldest age, empty where there is none.
	 * @return string
	 */
	public static function age_range( string $from, string $to ): string {
		$from = self::age( $from );
		$to   = self::age( $to );

		if ( '' === $from ) {
			return $to;
		}

		if ( '' === $to ) {
			/* translators: %s: the youngest age a course is for. */
			return sprintf( __( 'from %s', 'course-schedule-connector' ), $from );
		}

		if ( $from === $to ) {
			return $from;
		}

		return $from . self::NBSP . '–' . self::NBSP . $to;
	}

	/**
	 * Renders one age the way the language writes a number.
	 *
	 * @param string $value Age as it is stored, with a full stop.
	 * @return string
	 */
	private static function age( string $value ): string {
		$value = trim( $value );

		if ( '' === $value || ! is_numeric( $value ) ) {
			return '';
		}

		$decimals = ( (float) $value === floor( (float) $value ) ) ? 0 : 1;

		return function_exists( 'number_format_i18n' )
			? number_format_i18n( (float) $value, $decimals )
			: number_format( (float) $value, $decimals, ',', '' );
	}

	/**
	 * Renders a range of dates as one string, dropping what repeats.
	 *
	 * @param string $from First day, already formatted.
	 * @param string $to   Last day, already formatted.
	 * @return string
	 */
	public static function date_range( string $from, string $to ): string {
		$from = trim( $from );
		$to   = trim( $to );

		if ( '' === $from ) {
			return $to;
		}

		if ( '' === $to || $from === $to ) {
			return $from;
		}

		return $from . self::NBSP . '–' . self::NBSP . $to;
	}
}
