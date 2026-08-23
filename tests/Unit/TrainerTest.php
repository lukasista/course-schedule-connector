<?php
/**
 * Tests for pairing a course with a trainer.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Data\TrainerRepository;
use CSCS\Data\TrainerType;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the two decisions a trainer page rests on.
 *
 * The key is the whole pairing: get it wrong and a course points at somebody
 * else's page, or at none. The list sanitiser is what stands between a form
 * with a spare empty row on it and a page printing empty bullet points.
 *
 * @covers \CSCS\Data\TrainerRepository
 * @covers \CSCS\Data\TrainerType
 */
final class TrainerTest extends TestCase {

	/**
	 * Spacing and capitals must not make two pages out of one person.
	 *
	 * @return void
	 */
	public function test_the_key_ignores_spacing_and_capitals(): void {
		$expected = TrainerRepository::key( 'Jana Nováková' );

		$this->assertNotSame( '', $expected );
		$this->assertSame( $expected, TrainerRepository::key( 'Jana  Nováková' ) );
		$this->assertSame( $expected, TrainerRepository::key( ' jana nováková ' ) );
		$this->assertSame( $expected, TrainerRepository::key( "Jana\tNováková" ) );
	}

	/**
	 * Diacritics are part of the name, so two spellings stay two people.
	 *
	 * Losing them would be tidier and wrong: "Nováková" and "Novakova" are
	 * different names, and a plugin that merged them would be guessing.
	 *
	 * @return void
	 */
	public function test_the_key_keeps_diacritics(): void {
		$this->assertNotSame(
			TrainerRepository::key( 'Jana Nováková' ),
			TrainerRepository::key( 'Jana Novakova' )
		);
	}

	/**
	 * A name that is only whitespace is not a name.
	 *
	 * @return void
	 */
	public function test_an_empty_name_has_no_key(): void {
		$this->assertSame( '', TrainerRepository::key( '' ) );
		$this->assertSame( '', TrainerRepository::key( '   ' ) );
	}

	/**
	 * The spare row the form always offers must not become an empty bullet.
	 *
	 * @return void
	 */
	public function test_empty_lines_are_dropped_and_order_is_kept(): void {
		$this->assertSame(
			array( 'Trenér II. třídy', 'Instruktor fitness' ),
			TrainerType::sanitise_list( array( 'Trenér II. třídy', '', '  ', 'Instruktor fitness' ) )
		);
	}

	/**
	 * A single value is a list of one, not a reason to fail.
	 *
	 * @return void
	 */
	public function test_a_lone_value_becomes_a_list(): void {
		$this->assertSame( array( 'Jóga' ), TrainerType::sanitise_list( 'Jóga' ) );
		$this->assertSame( array(), TrainerType::sanitise_list( null ) );
	}
}
