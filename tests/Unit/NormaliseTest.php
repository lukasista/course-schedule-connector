<?php
/**
 * Tests for type normalisation.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Support\Normalise;
use PHPUnit\Framework\TestCase;

/**
 * Exercises every coercion the API payload demands.
 *
 * @covers \CSCS\Support\Normalise
 */
final class NormaliseTest extends TestCase {

	/**
	 * Capacities arrive as strings while availability arrives as an integer.
	 *
	 * @return void
	 */
	public function test_integers_are_coerced_consistently(): void {
		$this->assertSame( 1, Normalise::to_int( '1' ) );
		$this->assertSame( 1, Normalise::to_int( 1 ) );
		$this->assertSame( 0, Normalise::to_int( '' ) );
		$this->assertSame( 0, Normalise::to_int( null ) );
		$this->assertSame( 0, Normalise::to_int( 'twelve' ) );
		$this->assertSame( 12, Normalise::to_int( ' 12 ' ) );
	}

	/**
	 * A missing number must stay missing rather than becoming zero.
	 *
	 * @return void
	 */
	public function test_nullable_integers_preserve_absence(): void {
		$this->assertNull( Normalise::to_nullable_int( null ) );
		$this->assertNull( Normalise::to_nullable_int( '' ) );
		$this->assertNull( Normalise::to_nullable_int( 'abc' ) );
		$this->assertSame( 0, Normalise::to_nullable_int( '0' ) );
	}

	/**
	 * A null price carries meaning and must never collapse to zero.
	 *
	 * @return void
	 */
	public function test_prices_keep_null_distinct_from_zero(): void {
		$this->assertNull( Normalise::to_price( null ) );
		$this->assertNull( Normalise::to_price( '' ) );
		$this->assertSame( '0.00', Normalise::to_price( '0' ) );
		$this->assertSame( '1960.00', Normalise::to_price( '1960.00' ) );
		$this->assertSame( '1960.50', Normalise::to_price( 1960.5 ) );
	}

	/**
	 * Colours end up inside a style attribute, so anything odd is rejected.
	 *
	 * @return void
	 */
	public function test_colours_are_rejected_unless_they_are_hex(): void {
		$this->assertSame( 'bf496c', Normalise::to_hex_colour( 'BF496C' ) );
		$this->assertSame( 'fff', Normalise::to_hex_colour( '#FFF' ) );
		$this->assertNull( Normalise::to_hex_colour( 'red' ) );
		$this->assertNull( Normalise::to_hex_colour( 'ffffff;background:url(x)' ) );
		$this->assertNull( Normalise::to_hex_colour( 'fffff' ) );
		$this->assertNull( Normalise::to_hex_colour( '' ) );
	}

	/**
	 * Only http and https URLs survive.
	 *
	 * @return void
	 */
	public function test_urls_are_restricted_to_web_schemes(): void {
		$this->assertSame( 'https://example.com/x', Normalise::to_url( 'https://example.com/x' ) );
		$this->assertNull( Normalise::to_url( 'javascript:alert(1)' ) );
		$this->assertNull( Normalise::to_url( 'not a url' ) );
		$this->assertNull( Normalise::to_url( '' ) );
	}

	/**
	 * Flags arrive as "0" and "1", never as booleans.
	 *
	 * @return void
	 */
	public function test_flags_are_read_as_booleans(): void {
		$this->assertTrue( Normalise::to_bool( '1' ) );
		$this->assertTrue( Normalise::to_bool( 1 ) );
		$this->assertFalse( Normalise::to_bool( '0' ) );
		$this->assertFalse( Normalise::to_bool( '' ) );
		$this->assertFalse( Normalise::to_bool( null ) );
	}

	/**
	 * The endpoints disagree about spacing, which is the whole reason the match
	 * key removes whitespace outright instead of collapsing it.
	 *
	 * @return void
	 */
	public function test_match_key_survives_the_spacing_difference_between_endpoints(): void {
		$from_course = Normalise::match_key( '113- Deskové hry 9-13 let I. pololetí' );
		$from_lesson = Normalise::match_key( '113-Deskové hry 9-13 let I. pololetí' );

		$this->assertSame( $from_course, $from_lesson );
	}

	/**
	 * A non-breaking space must not survive either.
	 *
	 * @return void
	 */
	public function test_match_key_removes_non_breaking_spaces(): void {
		$this->assertSame(
			Normalise::match_key( 'Gymnastika 9-11' ),
			Normalise::match_key( "Gymnastika\u{00A0}9-11" )
		);
	}

	/**
	 * Multi-byte characters must not be corrupted while whitespace is removed.
	 *
	 * @return void
	 */
	public function test_match_key_keeps_diacritics_intact(): void {
		$this->assertSame( 'gymnastikadívky', Normalise::match_key( 'Gymnastika dívky' ) );
	}

	/**
	 * The loose key is the fallback when an accent or a hyphen differs.
	 *
	 * @return void
	 */
	public function test_loose_match_key_strips_diacritics_and_punctuation(): void {
		$this->assertSame(
			Normalise::match_key_loose( '113- Deskové hry 9-13 let I. pololetí' ),
			Normalise::match_key_loose( '113 Deskove hry 913 let I pololeti' )
		);
	}

	/**
	 * Tags arrive as an object keyed by id.
	 *
	 * @return void
	 */
	public function test_tags_are_read_as_an_id_to_name_map(): void {
		$this->assertSame( array( 9 => 'Pronájem haly' ), Normalise::to_tags( array( '9' => 'Pronájem haly' ) ) );
		$this->assertSame( array(), Normalise::to_tags( array() ) );
		$this->assertSame( array(), Normalise::to_tags( 'nonsense' ) );
	}

	/**
	 * Dates and times are validated, not merely passed through.
	 *
	 * @return void
	 */
	public function test_dates_and_times_are_validated(): void {
		$this->assertSame( '2026-09-11', Normalise::to_date( '2026-09-11' ) );
		$this->assertNull( Normalise::to_date( '2026-02-30' ) );
		$this->assertNull( Normalise::to_date( '11.09.2026' ) );
		$this->assertSame( '16:00', Normalise::to_time( '16:00' ) );
		$this->assertNull( Normalise::to_time( '25:00' ) );
	}
}
