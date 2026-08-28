<?php
/**
 * Tests for reading who a course is for out of its name.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Data\Audience;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the reader against the names Jojo Gym actually publishes.
 *
 * @covers \CSCS\Data\Audience
 */
final class AudienceTest extends TestCase {

	/**
	 * The names in the live catalogue, read the way the website reads them.
	 *
	 * @return void
	 */
	public function test_real_course_names_are_read_the_way_the_website_reads_them(): void {
		$this->assertSame(
			array(
				'gender'   => Audience::GIRLS,
				'level'    => '',
				'age_from' => '9',
				'age_to'   => '11',
			),
			Audience::read( '37-Gymnastika 9-11 let dívky' )
		);

		$this->assertSame(
			array(
				'gender'   => Audience::MIXED,
				'level'    => Audience::IMPROVER,
				'age_from' => '10',
				'age_to'   => '',
			),
			Audience::read( '101-Lezení od 10 let mix mírně pokročilí' )
		);

		$this->assertSame(
			array(
				'gender'   => '',
				'level'    => Audience::COMPETITIVE,
				'age_from' => '',
				'age_to'   => '',
			),
			Audience::read( '82-Gymnastika závodní průprava' )
		);
	}

	/**
	 * A name that says nothing must produce nothing, not a guess.
	 *
	 * Most of the catalogue is like this, and a course wrongly labelled "mixed"
	 * is worse than a course labelled nothing: the reader is the only thing
	 * standing between a name and a fact published on a page.
	 *
	 * @return void
	 */
	public function test_a_name_that_says_nothing_yields_nothing(): void {
		$this->assertSame(
			array(
				'gender'   => '',
				'level'    => '',
				'age_from' => '',
				'age_to'   => '',
			),
			Audience::read( '79-Cvičení ve všech sálách dospělí' )
		);
	}

	/**
	 * The words are matched whole, so one hiding inside another is not a match.
	 *
	 * @return void
	 */
	public function test_a_word_inside_another_word_is_not_a_reading(): void {
		$this->assertSame( '', Audience::read( 'Mixáž zvuku' )['gender'] );
		$this->assertSame( '', Audience::read( 'Muzikál pro děti' )['gender'] );
	}

	/**
	 * A name written without diacritics reads the same as one with them.
	 *
	 * @return void
	 */
	public function test_diacritics_and_case_make_no_difference(): void {
		$with    = Audience::read( 'Gymnastika DÍVKY začátečnice' );
		$without = Audience::read( 'gymnastika divky zacatecnice' );

		$this->assertSame( $with, $without );
		$this->assertSame( Audience::GIRLS, $with['gender'] );
		$this->assertSame( Audience::BEGINNER, $with['level'] );
	}

	/**
	 * "Mírně pokročilí" must be read before "pokročilí", or every improver is
	 * filed as advanced.
	 *
	 * @return void
	 */
	public function test_the_longer_level_is_read_first(): void {
		$this->assertSame( Audience::IMPROVER, Audience::read( 'Lezení mírně pokročilí' )['level'] );
		$this->assertSame( Audience::ADVANCED, Audience::read( 'Lezení pokročilí' )['level'] );
	}

	/**
	 * Czech agrees the level with the group, which is why the label takes two
	 * arguments and the English strings carry a context.
	 *
	 * @return void
	 */
	public function test_the_level_label_agrees_with_the_group(): void {
		$this->assertSame( 'Beginners', Audience::level_label( Audience::BEGINNER, Audience::GIRLS ) );
		$this->assertSame( 'Beginners', Audience::level_label( Audience::BEGINNER, Audience::MIXED ) );
		$this->assertSame( '', Audience::level_label( 'nonsense', Audience::MIXED ) );
		$this->assertSame( '', Audience::level_label( '' ) );
	}

	/**
	 * The ages a course is for, in the three shapes the catalogue writes them.
	 *
	 * @return void
	 */
	public function test_ages_are_read_in_all_three_shapes(): void {
		$this->assertSame( array( 'from' => '9', 'to' => '11' ), Audience::read_age( '37-Gymnastika 9-11 let dívky' ) );
		$this->assertSame( array( 'from' => '10', 'to' => '' ), Audience::read_age( '101-Lezení od 10 let mix' ) );
		$this->assertSame( array( 'from' => '4', 'to' => '4' ), Audience::read_age( 'Hravé cvičení 4 roky' ) );
		$this->assertSame( array( 'from' => '', 'to' => '' ), Audience::read_age( '103-Lezení pro dospělé' ) );
	}

	/**
	 * Half a year is a real age here: the gym runs courses for two-and-a-half
	 * year olds, and rounding one to two would put a toddler in the wrong room.
	 *
	 * @return void
	 */
	public function test_halves_survive_and_the_comma_becomes_a_full_stop(): void {
		$this->assertSame( array( 'from' => '2.5', 'to' => '3' ), Audience::read_age( '03-Rodiče a děti 2,5-3 roky' ) );
		$this->assertSame( array( 'from' => '1.5', 'to' => '2' ), Audience::read_age( '01-Cvičení pro batolata 1,5-2 roky' ) );
		$this->assertSame( array( 'from' => '5', 'to' => '6.5' ), Audience::read_age( '16-Jojo přípravka 5-6,5 let mix' ) );
	}

	/**
	 * The course's own number is a number in the same name, and the unit is
	 * the only thing telling the two apart.
	 *
	 * @return void
	 */
	public function test_the_course_number_is_not_an_age(): void {
		$this->assertSame( array( 'from' => '10', 'to' => '16' ), Audience::read_age( '106- Kondiční posilovací trénink 10-16 let dívky' ) );
		$this->assertSame( array( 'from' => '8', 'to' => '' ), Audience::read_age( '114-Základy skoků na trampolíně od 8 let' ) );
		$this->assertSame( array( 'from' => '', 'to' => '' ), Audience::read_age( '110-Funkční kruhový trénink I. pololetí' ) );
	}

	/**
	 * Every key the reader can return must have a label, in both genders, or a
	 * course ends up publishing an empty fact.
	 *
	 * @return void
	 */
	public function test_every_key_has_a_label(): void {
		foreach ( array_keys( Audience::genders() ) as $gender ) {
			$this->assertNotSame( '', Audience::gender_label( (string) $gender ) );

			foreach ( array_keys( Audience::levels() ) as $level ) {
				$this->assertNotSame( '', Audience::level_label( (string) $level, (string) $gender ) );
			}
		}

		$this->assertSame( '', Audience::gender_label( 'nonsense' ) );
	}
}
