<?php
/**
 * Tests for pairing a kind of course with the page written about it.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Data\KindRepository;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the key a page and a term are tied by.
 *
 * @covers \CSCS\Data\KindRepository
 */
final class KindPageTest extends TestCase {

	/**
	 * A page keeps its pairing when somebody retitles it in the ways a person
	 * retitles things: a capital, a space, a stray hyphen.
	 *
	 * @return void
	 */
	public function test_the_key_survives_the_ways_a_person_retypes_a_name(): void {
		$key = KindRepository::key( 'Gymnastika pro radost' );

		$this->assertSame( $key, KindRepository::key( 'gymnastika pro radost' ) );
		$this->assertSame( $key, KindRepository::key( '  Gymnastika  pro  radost ' ) );
		$this->assertSame( $key, KindRepository::key( 'Gymnastika pro radost!' ) );
	}

	/**
	 * Two kinds that differ by more than punctuation stay two kinds. This is
	 * the pair that has to stay apart, on this site above all.
	 *
	 * @return void
	 */
	public function test_two_kinds_do_not_collapse_into_one(): void {
		$this->assertNotSame(
			KindRepository::key( 'Gymnastika' ),
			KindRepository::key( 'Gymnastika pro radost' )
		);

		$this->assertNotSame(
			KindRepository::key( 'Lezení' ),
			KindRepository::key( 'Hravé lezení' )
		);
	}

	/**
	 * A name with nothing in it pairs with nothing, rather than with every
	 * other empty name.
	 *
	 * @return void
	 */
	public function test_a_name_with_no_letters_has_no_key(): void {
		$this->assertSame( '', KindRepository::key( '' ) );
		$this->assertSame( '', KindRepository::key( '   ' ) );
		$this->assertSame( '', KindRepository::key( '—' ) );
	}
}
