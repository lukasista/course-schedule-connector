<?php
/**
 * Tests for make-up lesson suggestions.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Api\Mapper;
use CSCS\Sync\MakeupResolver;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the reading of a course name out of a make-up lesson's own name.
 *
 * @covers \CSCS\Sync\MakeupResolver
 */
final class MakeupResolverTest extends TestCase {

	/**
	 * Builds courses from names.
	 *
	 * @param array<int, string> $names Course names keyed by course id.
	 * @return array<int, \CSCS\Api\Dto\Course>
	 */
	private function courses( array $names ): array {
		$records = array();

		foreach ( $names as $id => $name ) {
			$records[] = array(
				'id_course'     => (string) $id,
				'course_name'   => $name,
				'activity_name' => $name,
			);
		}

		return ( new Mapper() )->map_courses( $records );
	}

	/**
	 * A make-up lesson naming its course is read straight off the name.
	 *
	 * @return void
	 */
	public function test_a_course_named_in_the_make_up_lesson_is_found(): void {
		$suggestions = ( new MakeupResolver() )->suggest(
			array( 'Náhradní lekce 37-Gymnastika 9-11 let dívky I. pololetí' ),
			$this->courses(
				array(
					1085 => '37-Gymnastika 9-11 let dívky I. pololetí',
					1070 => '113-Deskové hry 9-13 let I. pololetí',
				)
			)
		);

		$this->assertSame( array( 1085 ), array_values( $suggestions ) );
	}

	/**
	 * Today's names give nothing away, and nothing is invented.
	 *
	 * @return void
	 */
	public function test_a_name_that_only_gives_an_age_group_suggests_nothing(): void {
		$suggestions = ( new MakeupResolver() )->suggest(
			array( 'Náhradní lekce 4-6 let I.pololetí' ),
			$this->courses(
				array(
					1085 => '25-Gymnastika 4-6 let dívky pokročilé I. pololetí',
					1070 => '26-Jojo přípravka 4-6 let dívky I. pololetí',
				)
			)
		);

		$this->assertSame( array(), $suggestions );
	}

	/**
	 * Two courses fitting one name means the name is ambiguous, and a wrong
	 * guess shown beside the wrong course is worse than no guess.
	 *
	 * @return void
	 */
	public function test_an_ambiguous_name_suggests_nothing(): void {
		$suggestions = ( new MakeupResolver() )->suggest(
			array( 'Náhradní lekce Gymnastika 9-11 let a Gymnastika 7-9 let' ),
			$this->courses(
				array(
					10 => 'Gymnastika 9-11 let',
					11 => 'Gymnastika 7-9 let',
				)
			)
		);

		$this->assertSame( array(), $suggestions );
	}

	/**
	 * A course name short enough to turn up inside unrelated words is not
	 * looked for at all.
	 *
	 * @return void
	 */
	public function test_a_very_short_course_name_is_not_matched_on(): void {
		$suggestions = ( new MakeupResolver() )->suggest(
			array( 'Náhradní lekce barrella 6-8 let' ),
			$this->courses( array( 10 => 'Barre' ) )
		);

		$this->assertSame( array(), $suggestions );
	}

	/**
	 * The suggestion is keyed the same way the stored link is, so one can be
	 * turned into the other without translating anything.
	 *
	 * @return void
	 */
	public function test_the_suggestion_key_matches_the_stored_link_key(): void {
		$suggestions = ( new MakeupResolver() )->suggest(
			array( 'Náhradní lekce 113-Deskové hry 9-13 let I. pololetí' ),
			$this->courses( array( 1070 => '113-Deskové hry 9-13 let I. pololetí' ) )
		);

		$this->assertSame(
			array( \CSCS\Support\Normalise::match_key( 'Náhradní lekce 113-Deskové hry 9-13 let I. pololetí' ) => 1070 ),
			$suggestions
		);
	}
}
