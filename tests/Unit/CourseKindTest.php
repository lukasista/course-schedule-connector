<?php
/**
 * Tests for reading the kind of course out of its name.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Data\CourseKind;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the reader against the names Jojo Gym actually publishes.
 *
 * @covers \CSCS\Data\CourseKind
 */
final class CourseKindTest extends TestCase {

	/**
	 * The number in front and everything from the age onwards are not the kind.
	 *
	 * @return void
	 */
	public function test_the_kind_is_what_stands_before_the_age(): void {
		$this->assertSame( 'Parkour', CourseKind::read( '66-Parkour 12-15 let I. pololetí' ) );
		$this->assertSame( 'Deskové hry', CourseKind::read( '113-Deskové hry 9-13 let I. pololetí' ) );
		$this->assertSame( 'Kondiční posilovací trénink', CourseKind::read( '106- Kondiční posilovací trénink 10-16 let dívky I. pololetí' ) );
		$this->assertSame( 'Cvičení pro batolata', CourseKind::read( '01-Cvičení pro batolata 1,5-2 roky začátečníci I. pololetí' ) );
	}

	/**
	 * A kind of several words stays whole. This is the one that matters: the
	 * first attempt cut at the first space and put "Gymnastika pro radost" in
	 * with "Gymnastika", which is two different courses on one card.
	 *
	 * @return void
	 */
	public function test_a_kind_of_several_words_is_not_cut_at_the_first_space(): void {
		$this->assertSame( 'Gymnastika pro radost', CourseKind::read( '56-Gymnastika pro radost 7-11 let dívky I. pololetí' ) );
		$this->assertSame( 'Gymnastika', CourseKind::read( '38-Gymnastika 9-11 let dívky I. pololetí' ) );
		$this->assertSame( 'Jojo přípravka', CourseKind::read( '22-Jojo přípravka 4-6 let dívky I. pololetí' ) );
		$this->assertSame( 'Vzdušná akrobacie na kruzích', CourseKind::read( '80-Vzdušná akrobacie na kruzích 10-14 let I. pololetí' ) );
		$this->assertSame( 'Základy skoků na trampolíně', CourseKind::read( '114-Základy skoků na trampolíně od 8 let I. pololetí' ) );
	}

	/**
	 * Where there is no age, the audience, the level or the term ends the kind.
	 *
	 * @return void
	 */
	public function test_the_kind_also_stops_at_a_group_a_level_or_the_term(): void {
		$this->assertSame( 'Funkční kruhový trénink', CourseKind::read( '110-Funkční kruhový trénink I. pololetí' ) );
		$this->assertSame( 'Mámy ve formě', CourseKind::read( '109-Mámy ve formě I. pololetí' ) );
		$this->assertSame( 'Lezení pro dospělé', CourseKind::read( '104-Lezení pro dospělé pokročilí I. pololetí' ) );
		$this->assertSame( 'Rodiče a děti', CourseKind::read( '05-Rodiče a děti 3-4 roky pokročilí I.pololetí' ) );
	}

	/**
	 * Punctuation inside a kind belongs to it; punctuation left behind by the
	 * cut does not.
	 *
	 * @return void
	 */
	public function test_brackets_survive_and_a_trailing_dash_does_not(): void {
		$this->assertSame( 'Bouldrování (lezení)', CourseKind::read( '86-Bouldrování (lezení) 8-10 let mix I. pololetí' ) );
		$this->assertSame( 'Lezení', CourseKind::read( '99-Lezení - od 10 let mix I. pololetí' ) );
	}

	/**
	 * A name that is only a number leaves nothing to file the course under,
	 * and nothing is what it gets — an empty kind is not a kind called "".
	 *
	 * @return void
	 */
	public function test_a_name_with_no_kind_in_it_yields_nothing(): void {
		$this->assertSame( '', CourseKind::read( '113-' ) );
		$this->assertSame( '', CourseKind::read( '12-9-11 let dívky' ) );
		$this->assertSame( '', CourseKind::read( '' ) );
	}

	/**
	 * Every course of one kind must read as the same kind, or the card breaks
	 * into two with the same name on both.
	 *
	 * @return void
	 */
	public function test_courses_of_one_kind_agree(): void {
		$names = array(
			'38-Gymnastika 9-11 let dívky I. pololetí',
			'45-Gymnastika 6-9 let mix I. pololetí',
			'47-Gymnastika 7-9 let kluci I. pololetí',
			'28-Gymnastika 6-9 let dívky začátečníci I. pololetí',
			'43-Gymnastika 12-15 let dívky I. pololetí',
			'481-Gymnastika 7-9 let mix I. pololetí',
		);

		$kinds = array_unique( array_map( array( CourseKind::class, 'read' ), $names ) );

		$this->assertSame( array( 'Gymnastika' ), array_values( $kinds ) );
	}
}
