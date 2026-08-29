<?php
/**
 * Type normalisation for untrusted API data.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Converts values received from the iSport System API into predictable PHP types.
 *
 * The API is inconsistent by nature: capacities arrive as strings ("1") while
 * availability arrives as an integer, prices arrive as decimal strings or null,
 * and colours arrive as bare hex without a leading hash. Nothing in this class
 * touches WordPress, so it is unit-testable in isolation and safe to call before
 * WordPress is fully loaded.
 */
final class Normalise {

	/**
	 * Pattern matching every character removed when building a match key.
	 *
	 * Covers ordinary whitespace plus the non-breaking and zero-width spaces that
	 * appear in some iSport activity names. Applied with the /u modifier so that
	 * multi-byte characters are never split.
	 */
	private const WHITESPACE_PATTERN = '/[\s\x{00A0}\x{200B}\x{FEFF}]+/u';

	/**
	 * Casts a value to an integer, treating anything non-numeric as zero.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function to_int( $value ): int {
		if ( is_int( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) && is_numeric( trim( $value ) ) ) {
			return (int) trim( $value );
		}

		if ( is_float( $value ) ) {
			return (int) $value;
		}

		return 0;
	}

	/**
	 * Casts a value to an integer, or null when it is absent or non-numeric.
	 *
	 * @param mixed $value Raw value.
	 * @return int|null
	 */
	public static function to_nullable_int( $value ): ?int {
		if ( null === $value ) {
			return null;
		}

		if ( is_string( $value ) && '' === trim( $value ) ) {
			return null;
		}

		if ( ! is_int( $value ) && ! is_float( $value ) && ! ( is_string( $value ) && is_numeric( trim( $value ) ) ) ) {
			return null;
		}

		return self::to_int( $value );
	}

	/**
	 * Casts a value to a trimmed string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function to_string( $value ): string {
		if ( is_string( $value ) ) {
			return trim( $value );
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		return '';
	}

	/**
	 * Interprets an API flag as a boolean.
	 *
	 * The API expresses flags as "0"/"1", 0/1 or, occasionally, an empty string.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		$string = strtolower( self::to_string( $value ) );

		return in_array( $string, array( '1', 'true', 'yes' ), true );
	}

	/**
	 * Normalises a price into a decimal string, or null when there is no price.
	 *
	 * A null price is meaningful in this API and must not collapse to zero: for a
	 * course it means the course is free, while for a class it usually means the
	 * entry is a hall rental with no public price. The distinction is made by the
	 * caller, not here.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null Decimal string such as "1960.00", or null.
	 */
	public static function to_price( $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		$string = self::to_string( $value );

		if ( '' === $string || ! is_numeric( $string ) ) {
			return null;
		}

		return number_format( (float) $string, 2, '.', '' );
	}

	/**
	 * Validates a colour received from the API.
	 *
	 * Colours arrive as bare hex without a leading hash. Anything that is not a
	 * valid 3, 4, 6 or 8 digit hex value is rejected rather than passed through,
	 * because the value ends up inside a style attribute.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null Lowercase hex without a leading hash, or null.
	 */
	public static function to_hex_colour( $value ): ?string {
		$string = ltrim( self::to_string( $value ), '#' );

		if ( 1 !== preg_match( '/^[0-9a-fA-F]{3,8}$/', $string ) ) {
			return null;
		}

		if ( ! in_array( strlen( $string ), array( 3, 4, 6, 8 ), true ) ) {
			return null;
		}

		return strtolower( $string );
	}

	/**
	 * Validates a URL received from the API.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null The URL when it is a well-formed http or https URL, otherwise null.
	 */
	public static function to_url( $value ): ?string {
		$string = self::to_string( $value );

		if ( '' === $string ) {
			return null;
		}

		if ( false === filter_var( $string, FILTER_VALIDATE_URL ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- This class stays free of WordPress so it can be unit-tested in isolation.
		$scheme = strtolower( (string) parse_url( $string, PHP_URL_SCHEME ) );

		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return null;
		}

		return $string;
	}

	/**
	 * Validates a Unix timestamp.
	 *
	 * @param mixed $value Raw value.
	 * @return int|null
	 */
	public static function to_timestamp( $value ): ?int {
		$timestamp = self::to_nullable_int( $value );

		if ( null === $timestamp || $timestamp <= 0 ) {
			return null;
		}

		return $timestamp;
	}

	/**
	 * Validates a Y-m-d date string.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	public static function to_date( $value ): ?string {
		$string = self::to_string( $value );

		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $string, $matches ) ) {
			return null;
		}

		if ( ! checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
			return null;
		}

		return $string;
	}

	/**
	 * Validates an H:i time string.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	public static function to_time( $value ): ?string {
		$string = self::to_string( $value );

		if ( 1 !== preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $string ) ) {
			return null;
		}

		return $string;
	}

	/**
	 * Normalises the tags structure, which arrives as an object keyed by tag id.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int, string> Tag id to tag name.
	 */
	public static function to_tags( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$tags = array();

		foreach ( $value as $id => $name ) {
			$tag_id   = self::to_int( $id );
			$tag_name = self::to_string( $name );

			if ( 0 !== $tag_id && '' !== $tag_name ) {
				$tags[ $tag_id ] = $tag_name;
			}
		}

		return $tags;
	}

	/**
	 * Builds the key used to match a class occurrence to its course.
	 *
	 * The two endpoints do not agree on spacing: a course may be named
	 * "113- Deskove hry" while its classes are named "113-Deskove hry". Collapsing
	 * runs of whitespace is not enough, because the difference is a single space
	 * that exists on one side and not the other. All whitespace is therefore
	 * removed outright, and the result is lowercased.
	 *
	 * @param string $name Activity or course name.
	 * @return string
	 */
	public static function match_key( string $name ): string {
		$name = (string) preg_replace( self::WHITESPACE_PATTERN, '', $name );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $name, 'UTF-8' ) : strtolower( $name );
	}

	/**
	 * Builds a looser fallback match key with diacritics and punctuation removed.
	 *
	 * Used only when the strict key finds no candidate, so that a stray accent or
	 * hyphen cannot orphan an otherwise obvious match.
	 *
	 * @param string $name Activity or course name.
	 * @return string
	 */
	public static function match_key_loose( string $name ): string {
		$key = self::match_key( $name );
		$key = self::strip_diacritics( $key );

		return (string) preg_replace( '/[^a-z0-9]/', '', $key );
	}

	/**
	 * Returns a string with its accents removed and its case flattened.
	 *
	 * Unlike the match keys above this keeps the spaces and the punctuation:
	 * it is for reading words out of a name, where "mix" inside "mixáž" must
	 * not count and "mírně pokročilí" is two words that belong together.
	 *
	 * @param string $value Input string.
	 * @return string
	 */
	public static function plain( string $value ): string {
		$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );

		return self::strip_diacritics( $value );
	}

	/**
	 * Removes diacritics from a UTF-8 string.
	 *
	 * @param string $value Input string.
	 * @return string
	 */
	private static function strip_diacritics( string $value ): string {
		if ( function_exists( 'transliterator_transliterate' ) ) {
			$converted = transliterator_transliterate( 'NFD; [:Nonspacing Mark:] Remove; NFC', $value );

			if ( is_string( $converted ) ) {
				return $converted;
			}
		}

		$map = array(
			'á' => 'a',
			'ä' => 'a',
			'č' => 'c',
			'ď' => 'd',
			'é' => 'e',
			'ě' => 'e',
			'í' => 'i',
			'ĺ' => 'l',
			'ľ' => 'l',
			'ň' => 'n',
			'ó' => 'o',
			'ô' => 'o',
			'ö' => 'o',
			'ř' => 'r',
			'ŕ' => 'r',
			'š' => 's',
			'ť' => 't',
			'ú' => 'u',
			'ů' => 'u',
			'ü' => 'u',
			'ý' => 'y',
			'ž' => 'z',
		);

		return strtr( $value, $map );
	}
}
