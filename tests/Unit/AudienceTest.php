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
				'gender' => Audience::GIRLS,
				'level'  => '',
			),
			Audience::read( '37-Gymnastika 9-11 let dívky' )
		);

		$this->assertSame(
			array(
				'gender' => Audience::MIXED,
				'level'  => Audience::IMPROVER,
			),
			Audience::read( '101-Lezení od 10 let mix mírně pokročilí' )
		);

		$this->assertSame(
			array(
				'gender' => '',
				'level'  => Audience::COMPETITIVE,
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
				'gender' => '',
				'level'  => '',
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
