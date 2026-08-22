<?php
/**
 * Tests for course-to-occurrence matching.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Api\Mapper;
use CSCS\Sync\Assignment;
use CSCS\Sync\Matcher;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the only part of the plugin that has to infer a relationship the
 * API does not express.
 *
 * @covers \CSCS\Sync\Matcher
 * @covers \CSCS\Sync\MatchResult
 */
final class MatcherTest extends TestCase {

	/**
	 * Mapper used to turn fixture arrays into typed records.
	 *
	 * @var Mapper
	 */
	private Mapper $mapper;

	/**
	 * Matcher under test.
	 *
	 * @var Matcher
	 */
	private Matcher $matcher;

	/**
	 * Sets up collaborators.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->mapper  = new Mapper();
		$this->matcher = new Matcher();
	}

	/**
	 * Builds a course record shaped like the API's.
	 *
	 * @param int                  $id        Course id.
	 * @param string               $name      Activity name.
	 * @param array<int, int>      $stamps    Term timestamps.
	 * @param array<string, mixed> $overrides Extra fields.
	 * @return array<string, mixed>
	 */
	private function course_data( int $id, string $name, array $stamps, array $overrides = array() ): array {
		return array_merge(
			array(
				'id_course'     => (string) $id,
				'course_name'   => $name,
				'activity_name' => $name,
				'date_from'     => '2026-09-11',
				'date_to'       => '2027-01-22',
				'stamp_from'    => (string) ( array() === $stamps ? 0 : min( $stamps ) ),
				'stamp_to'      => (string) ( array() === $stamps ? 0 : max( $stamps ) ),
				'terms'         => array_map(
					static fn( int $stamp ): array => array( 'stamp' => (string) $stamp ),
					$stamps
				),
			),
			$overrides
		);
	}

	/**
	 * Builds a class occurrence record shaped like the API's.
	 *
	 * @param int                  $term      Occurrence id.
	 * @param string               $name      Activity name.
	 * @param int                  $stamp     Start timestamp.
	 * @param array<string, mixed> $overrides Extra fields.
	 * @return array<string, mixed>
	 */
	private function lesson_data( int $term, string $name, int $stamp, array $overrides = array() ): array {
		return array_merge(
			array(
				'id_activity_term' => (string) $term,
				'activity_name'    => $name,
				'stamp_from'       => (string) $stamp,
				'date'             => '2026-09-11',
				'id_tab'           => '12',
				'tab_name'         => 'Gymnastická hala 2',
			),
			$overrides
		);
	}

	/**
	 * Runs the matcher over raw fixture arrays.
	 *
	 * @param array<int, array<string, mixed>> $courses Course records.
	 * @param array<int, array<string, mixed>> $lessons Occurrence records.
	 * @param array<int, int>                  $manual  Manual assignments.
	 * @return \CSCS\Sync\MatchResult
	 */
	private function run( array $courses, array $lessons, array $manual = array() ) {
		return $this->matcher->match(
			$this->mapper->map_courses( $courses ),
			$this->mapper->map_lessons( $lessons ),
			$manual
		);
	}

	/**
	 * Loads one of the shared fixtures.
	 *
	 * @param string $name File name without extension.
	 * @return mixed
	 */
	private function fixture( string $name ) {
		return json_decode( (string) file_get_contents( __DIR__ . '/../fixtures/' . $name . '.json' ), true );
	}

	/**
	 * The real-world case: the two endpoints spell the name differently and the
	 * timestamps agree.
	 *
	 * @return void
	 */
	public function test_real_fixtures_match_despite_the_spacing_difference(): void {
		$result = $this->matcher->match(
			$this->mapper->map_courses( $this->fixture( 'courses' ) ),
			$this->mapper->map_lessons( $this->fixture( 'activities' ) )
		);

		$this->assertArrayHasKey( 55918, $result->assignments );
		$this->assertSame( 1070, $result->assignments[55918]->course_id );
		$this->assertSame( Assignment::STRICT, $result->assignments[55918]->method );
	}

	/**
	 * A hall rental belongs to no course and must not count as a failure.
	 *
	 * @return void
	 */
	public function test_a_rental_is_reported_as_external_not_as_a_failure(): void {
		$result = $this->matcher->match(
			$this->mapper->map_courses( $this->fixture( 'courses' ) ),
			$this->mapper->map_lessons( $this->fixture( 'activities' ) )
		);

		$this->assertSame( 'no_candidate', $result->unmatched[55921]['reason'] );
		$this->assertSame( 1, $result->external() );
		$this->assertSame( 0, $result->problematic() );
		$this->assertSame( 100.0, $result->rate() );
	}

	/**
	 * A course that runs in two rooms collects both, which is the whole reason
	 * rooms are derived from occurrences instead of read from the course record.
	 *
	 * @return void
	 */
	public function test_rooms_are_collected_from_every_occurrence(): void {
		$result = $this->run(
			array( $this->course_data( 10, 'Parkour', array( 1000, 2000 ) ) ),
			array(
				$this->lesson_data(
					1,
					'Parkour',
					1000,
					array(
						'id_tab'   => '12',
						'tab_name' => 'Hala 2',
					)
				),
				$this->lesson_data(
					2,
					'Parkour',
					2000,
					array(
						'id_tab'   => '13',
						'tab_name' => 'Hala 3',
					)
				),
			)
		);

		$this->assertSame(
			array(
				12 => 'Hala 2',
				13 => 'Hala 3',
			),
			$result->rooms[10]
		);
	}

	/**
	 * Two courses sharing a name and a timestamp cannot be told apart, and
	 * guessing would be worse than admitting it.
	 *
	 * @return void
	 */
	public function test_an_ambiguous_occurrence_is_left_for_a_human(): void {
		$result = $this->run(
			array(
				$this->course_data( 10, 'Parkour', array( 1000 ) ),
				$this->course_data( 11, 'Parkour', array( 1000 ) ),
			),
			array( $this->lesson_data( 1, 'Parkour', 1000 ) )
		);

		$this->assertSame( array(), $result->assignments );
		$this->assertSame( 'ambiguous', $result->unmatched[1]['reason'] );
		$this->assertSame( 1, $result->problematic() );
		$this->assertSame( 0.0, $result->rate() );
	}

	/**
	 * A manual assignment overrides everything and is reported as such.
	 *
	 * @return void
	 */
	public function test_a_manual_assignment_wins(): void {
		$result = $this->run(
			array(
				$this->course_data( 10, 'Parkour', array( 1000 ) ),
				$this->course_data( 11, 'Something else', array( 1000 ) ),
			),
			array( $this->lesson_data( 1, 'Parkour', 1000 ) ),
			array( 1 => 11 )
		);

		$this->assertSame( 11, $result->assignments[1]->course_id );
		$this->assertSame( Assignment::MANUAL, $result->assignments[1]->method );
	}

	/**
	 * A manual assignment pointing at a course that no longer exists is a
	 * problem to surface, not to silently ignore.
	 *
	 * @return void
	 */
	public function test_a_stale_manual_assignment_is_surfaced(): void {
		$result = $this->run(
			array( $this->course_data( 10, 'Parkour', array( 1000 ) ) ),
			array( $this->lesson_data( 1, 'Parkour', 1000 ) ),
			array( 1 => 999 )
		);

		$this->assertSame( 'manual_course_missing', $result->unmatched[1]['reason'] );
		$this->assertSame( 1, $result->problematic() );
	}

	/**
	 * When the name matches one course and the occurrence falls inside its
	 * dates, an extra term the course list did not mention is still its own.
	 *
	 * @return void
	 */
	public function test_an_extra_term_inside_the_course_dates_is_accepted(): void {
		$result = $this->run(
			array( $this->course_data( 10, 'Parkour', array( 1000, 3000 ) ) ),
			array( $this->lesson_data( 1, 'Parkour', 2000 ) )
		);

		$this->assertSame( 10, $result->assignments[1]->course_id );
		$this->assertSame( Assignment::RANGE, $result->assignments[1]->method );
	}

	/**
	 * An occurrence outside the course's dates is not quietly attached to it.
	 *
	 * @return void
	 */
	public function test_an_occurrence_outside_the_course_dates_is_refused(): void {
		$result = $this->run(
			array( $this->course_data( 10, 'Parkour', array( 1000, 3000 ) ) ),
			array( $this->lesson_data( 1, 'Parkour', 9999 ) )
		);

		$this->assertSame( array(), $result->assignments );
		$this->assertSame( 'stamp_mismatch', $result->unmatched[1]['reason'] );
	}

	/**
	 * A missing accent falls back to the looser key rather than orphaning the
	 * occurrence.
	 *
	 * @return void
	 */
	public function test_a_missing_accent_still_matches_through_the_loose_key(): void {
		$result = $this->run(
			array( $this->course_data( 10, 'Gymnastika dívky', array( 1000 ) ) ),
			array( $this->lesson_data( 1, 'Gymnastika divky', 1000 ) )
		);

		$this->assertSame( 10, $result->assignments[1]->course_id );
		$this->assertSame( Assignment::LOOSE, $result->assignments[1]->method );
	}

	/**
	 * The report breaks assignments down by how they were reached, so a run
	 * leaning on the weaker methods is visible rather than merely successful.
	 *
	 * @return void
	 */
	public function test_methods_are_counted_separately(): void {
		$result = $this->run(
			array( $this->course_data( 10, 'Parkour', array( 1000, 3000 ) ) ),
			array(
				$this->lesson_data( 1, 'Parkour', 1000 ),
				$this->lesson_data( 2, 'Parkour', 2000 ),
			)
		);

		$this->assertSame(
			array(
				Assignment::RANGE  => 1,
				Assignment::STRICT => 1,
			),
			$result->methods()
		);
	}

	/**
	 * An occurrence with a trainer and a price that matched nothing is not a
	 * rental. It is a course lesson whose course is missing, and it counts.
	 *
	 * @return void
	 */
	public function test_an_orphaned_course_lesson_is_not_excused_as_external(): void {
		$result = $this->run(
			array(),
			array(
				$this->lesson_data(
					1,
					'Parkour',
					1000,
					array(
						'trainer_name' => 'Lukáš Moutelík',
						'price'        => '4160.00',
						'tags'         => array( '4' => 'Kurz' ),
					)
				),
			)
		);

		$this->assertSame( 'orphan', $result->unmatched[1]['reason'] );
		$this->assertSame( 0, $result->external() );
		$this->assertSame( 1, $result->problematic() );
		$this->assertSame( 0.0, $result->rate() );
	}

	/**
	 * A rental carries neither a trainer nor a price, and is set aside.
	 *
	 * @return void
	 */
	public function test_an_occurrence_without_trainer_or_price_is_treated_as_a_rental(): void {
		$result = $this->run(
			array(),
			array( $this->lesson_data( 1, 'Sokol Radotín', 1000, array( 'price' => null ) ) )
		);

		$this->assertSame( 'no_candidate', $result->unmatched[1]['reason'] );
		$this->assertSame( 1, $result->external() );
		$this->assertSame( 0, $result->problematic() );
	}

	/**
	 * A weekly course that matched nothing produces one name, not many problems.
	 *
	 * @return void
	 */
	public function test_unresolved_occurrences_are_grouped_by_activity_name(): void {
		$trainer = array(
			'trainer_name' => 'Lukáš Moutelík',
			'price'        => '4160.00',
		);

		$result = $this->run(
			array(),
			array(
				$this->lesson_data( 1, 'Parkour', 1000, $trainer ),
				$this->lesson_data( 2, 'Parkour', 2000, $trainer ),
				$this->lesson_data( 3, 'Parkour', 3000, $trainer ),
				$this->lesson_data( 4, 'Lezení', 1000, $trainer ),
				$this->lesson_data( 5, 'Veřejnost', 1000, array( 'price' => null ) ),
			)
		);

		$this->assertSame(
			array(
				'Parkour' => 3,
				'Lezení'  => 1,
			),
			$result->unresolved_by_name()
		);
	}

	/**
	 * The timetable may use either of a course's two names, so both are indexed.
	 * The API documents that activity_name may be an alternative name, and this
	 * cost six courses on the first live run.
	 *
	 * @return void
	 */
	public function test_a_course_is_found_under_either_of_its_names(): void {
		$result = $this->run(
			array(
				$this->course_data(
					10,
					'Interní název kurzu',
					array( 1000 ),
					array( 'course_name' => '32-Gymnastika 7-10 let dívky pokročilé I. pololetí' )
				),
			),
			array( $this->lesson_data( 1, '32-Gymnastika 7-10 let dívky pokročilé I. pololetí', 1000 ) )
		);

		$this->assertSame( 10, $result->assignments[1]->course_id );
		$this->assertSame( Assignment::STRICT, $result->assignments[1]->method );
	}

	/**
	 * A course indexed under two names must not become ambiguous with itself.
	 *
	 * @return void
	 */
	public function test_a_course_with_two_names_does_not_collide_with_itself(): void {
		$result = $this->run(
			array( $this->course_data( 10, 'Parkour', array( 1000 ), array( 'course_name' => 'Parkour' ) ) ),
			array( $this->lesson_data( 1, 'Parkour', 1000 ) )
		);

		$this->assertSame( 10, $result->assignments[1]->course_id );
	}

	/**
	 * A drop-in class or a make-up lesson carries a trainer and a price but
	 * belongs to no course by design. The tag says so, and the tag is believed.
	 *
	 * @return void
	 */
	public function test_a_lesson_tagged_as_something_other_than_a_course_is_not_a_problem(): void {
		$result = $this->run(
			array(),
			array(
				$this->lesson_data(
					1,
					'Náhradní lekce 7-9 let I.pololetí',
					1000,
					array(
						'trainer_name' => 'Lukáš Moutelík',
						'price'        => '260.00',
						'tags'         => array( '11' => 'Náhradní lekce' ),
					)
				),
			)
		);

		$this->assertSame( 'no_candidate', $result->unmatched[1]['reason'] );
		$this->assertSame( 0, $result->problematic() );
		$this->assertSame( 100.0, $result->rate() );
	}

	/**
	 * A rental is tagged as one, and is set aside on that evidence rather than
	 * on the absence of other fields.
	 *
	 * @return void
	 */
	public function test_a_rental_is_recognised_by_its_tag(): void {
		$result = $this->run(
			array(),
			array(
				$this->lesson_data(
					1,
					'Gym Dobřichovice',
					1000,
					array( 'tags' => array( '9' => 'Pronájem haly' ) )
				),
			)
		);

		$this->assertSame( 'no_candidate', $result->unmatched[1]['reason'] );
		$this->assertSame( 1, $result->external() );
	}

	/**
	 * Make-up lessons and outside lecturers' courses are tagged as courses,
	 * because that is what they are, but no course record will ever exist for
	 * them. They belong in the timetable and in neither the success nor the
	 * failure column.
	 *
	 * @return void
	 */
	public function test_an_activity_that_takes_no_bookings_is_neither_matched_nor_a_failure(): void {
		$matcher = new Matcher( array( 'Náhradní lekce', 'Judo' ) );

		$result = $matcher->match(
			array(),
			$this->mapper->map_lessons(
				array(
					$this->lesson_data( 1, 'Náhradní lekce 4-6 let I.pololetí', 1000, array( 'tags' => array( '4' => 'Kurz' ) ) ),
					$this->lesson_data( 2, 'Judo pro děti 3-5 let', 2000, array( 'tags' => array( '4' => 'Kurz' ) ) ),
				)
			)
		);

		$this->assertSame( 'not_bookable', $result->unmatched[1]['reason'] );
		$this->assertSame( 'not_bookable', $result->unmatched[2]['reason'] );
		$this->assertSame( 2, $result->not_bookable() );
		$this->assertSame( 0, $result->problematic() );
		$this->assertSame( 0, $result->external() );
		$this->assertSame( 100.0, $result->rate() );
	}

	/**
	 * The list is compared loosely, because the timetable and the price list do
	 * not spell these the same way.
	 *
	 * @return void
	 */
	public function test_the_non_bookable_list_matches_loosely(): void {
		$matcher = new Matcher( array( 'Zdravé cvičení' ) );

		$result = $matcher->match(
			array(),
			$this->mapper->map_lessons(
				array( $this->lesson_data( 1, 'Zdrave cviceni s overbaly', 1000, array( 'tags' => array( '4' => 'Kurz' ) ) ) )
			)
		);

		$this->assertSame( 'not_bookable', $result->unmatched[1]['reason'] );
	}

	/**
	 * A real course must not be swallowed by an over-broad entry in the list.
	 *
	 * @return void
	 */
	public function test_a_matching_course_still_wins_over_the_list(): void {
		$matcher = new Matcher( array( 'Judo' ) );

		$result = $matcher->match(
			$this->mapper->map_courses( array( $this->course_data( 10, 'Judo pro děti 3-5 let', array( 1000 ) ) ) ),
			$this->mapper->map_lessons( array( $this->lesson_data( 1, 'Judo pro děti 3-5 let', 1000 ) ) )
		);

		$this->assertSame( 10, $result->assignments[1]->course_id );
	}

	/**
	 * With nothing to match, the rate is a hundred rather than a division by zero.
	 *
	 * @return void
	 */
	public function test_an_empty_run_does_not_divide_by_zero(): void {
		$result = $this->run( array(), array() );

		$this->assertSame( 100.0, $result->rate() );
		$this->assertSame( 0, $result->matched() );
	}
}
