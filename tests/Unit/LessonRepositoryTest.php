<?php
/**
 * Tests for the values written to the occurrence table.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Api\Mapper;
use CSCS\Data\LessonRepository;
use CSCS\Sync\Assignment;
use CSCS\Sync\MatchResult;
use PHPUnit\Framework\TestCase;

/**
 * Guards the column values, and in particular the one that decides whether an
 * occurrence can ever be found again.
 *
 * @covers \CSCS\Data\LessonRepository
 */
final class LessonRepositoryTest extends TestCase {

	/**
	 * Returns the fixture occurrences.
	 *
	 * @return array<int, \CSCS\Api\Dto\Lesson>
	 */
	private function lessons(): array {
		return ( new Mapper() )->map_lessons(
			json_decode( (string) file_get_contents( __DIR__ . '/../fixtures/activities.json' ), true )
		);
	}

	/**
	 * An unmatched occurrence stores zero, not null.
	 *
	 * $wpdb->prepare() turns a null bound to %d into 0, so an occurrence with no
	 * course was always stored as zero while every query looked for NULL. The
	 * synchronisation reported unresolved occurrences and the listing command
	 * found none; both were reading the same rows.
	 *
	 * @return void
	 */
	public function test_an_unmatched_occurrence_stores_zero_rather_than_null(): void {
		$lessons = $this->lessons();
		$values  = LessonRepository::row_values( $lessons[55921], null, 1000 );

		$this->assertSame( 0, $values['id_course'] );
		$this->assertSame( '', $values['match_method'] );
	}

	/**
	 * A matched occurrence stores the course it belongs to and how it got there.
	 *
	 * @return void
	 */
	public function test_a_matched_occurrence_stores_its_course(): void {
		$lessons = $this->lessons();

		$outcome = new MatchResult(
			array( 55918 => new Assignment( 55918, 1070, Assignment::STRICT ) ),
			array(),
			array()
		);

		$values = LessonRepository::row_values( $lessons[55918], $outcome, 1000 );

		$this->assertSame( 1070, $values['id_course'] );
		$this->assertSame( Assignment::STRICT, $values['match_method'] );
	}

	/**
	 * Only a rental is flagged external. An orphaned course lesson stays visible
	 * as unresolved, which is the whole point of the distinction.
	 *
	 * @return void
	 */
	public function test_only_a_rental_is_flagged_external(): void {
		$lessons = $this->lessons();

		$rental = new MatchResult(
			array(),
			array(
				55921 => array(
					'reason' => 'no_candidate',
					'name'   => 'Gym Dobřichovice',
				),
			),
			array()
		);

		$orphan = new MatchResult(
			array(),
			array(
				55918 => array(
					'reason' => 'orphan',
					'name'   => '113-Deskové hry 9-13 let I. pololetí',
				),
			),
			array()
		);

		$this->assertSame( 1, LessonRepository::row_values( $lessons[55921], $rental, 1000 )['is_external'] );
		$this->assertSame( 0, LessonRepository::row_values( $lessons[55918], $orphan, 1000 )['is_external'] );
	}

	/**
	 * Every outcome writes a status, so a row always says what it is.
	 *
	 * A boolean could not tell a hall rental from an activity that takes no
	 * bookings, which left a classifier that moved dozens of rows between the
	 * two impossible for anyone to check.
	 *
	 * @return void
	 */
	public function test_every_outcome_records_what_the_row_is(): void {
		$lessons = $this->lessons();

		$matched = new MatchResult(
			array( 55918 => new Assignment( 55918, 1070, Assignment::STRICT ) ),
			array(),
			array()
		);

		$this->assertSame( 'matched', LessonRepository::row_values( $lessons[55918], $matched, 1 )['status'] );

		foreach ( array(
			'no_candidate'   => 'external',
			'not_bookable'   => 'not_bookable',
			'orphan'         => 'unresolved',
			'ambiguous'      => 'unresolved',
			'stamp_mismatch' => 'unresolved',
		) as $reason => $expected ) {
			$outcome = new MatchResult(
				array(),
				array(
					55918 => array(
						'reason' => $reason,
						'name'   => 'x',
					),
				),
				array()
			);

			$this->assertSame(
				$expected,
				LessonRepository::row_values( $lessons[55918], $outcome, 1 )['status'],
				'Reason ' . $reason . ' produced the wrong status.'
			);
		}
	}

	/**
	 * A make-up lesson is tied to exactly one course, recorded against the single
	 * occurrence rather than the name, because the gym reuses one name across the
	 * make-up slots of every course in an age group.
	 *
	 * @return void
	 */
	public function test_a_make_up_occurrence_is_tied_to_one_course(): void {
		cscs_reset_test_state();

		$repository = new LessonRepository();

		$this->assertTrue( $repository->link_makeup( 55368, 1072 ) );

		$this->assertSame( array( 55368 => 1072 ), $repository->makeup_links() );
		$this->assertSame( array( 55368 ), $repository->makeup_terms_for_course( 1072 ) );
		$this->assertSame( array(), $repository->makeup_terms_for_course( 9999 ) );
	}

	/**
	 * Two occurrences sharing a name may stand in for two different courses, which
	 * is the whole reason the link hangs off the occurrence. Linking one must not
	 * decide anything about the other.
	 *
	 * @return void
	 */
	public function test_two_make_up_occurrences_of_one_name_may_differ(): void {
		cscs_reset_test_state();

		$repository = new LessonRepository();

		$repository->link_makeup( 55368, 1072 );
		$repository->link_makeup( 55301, 1075 );

		$this->assertSame(
			array(
				55368 => 1072,
				55301 => 1075,
			),
			$repository->makeup_links()
		);
	}

	/**
	 * Passing no course clears the link rather than storing a nonsense zero, and
	 * an occurrence id of zero is refused instead of being recorded.
	 *
	 * @return void
	 */
	public function test_a_make_up_link_can_be_cleared(): void {
		cscs_reset_test_state();

		$repository = new LessonRepository();

		$repository->link_makeup( 55368, 1072 );
		$repository->link_makeup( 55368, 0 );

		$this->assertSame( array(), $repository->makeup_links() );
		$this->assertFalse( $repository->link_makeup( 0, 1072 ) );
	}

	/**
	 * The value list matches the column list the insert statement declares, so a
	 * column added on one side and forgotten on the other cannot slip through.
	 *
	 * @return void
	 */
	public function test_the_value_list_matches_the_declared_columns(): void {
		$lessons = $this->lessons();
		$values  = LessonRepository::row_values( $lessons[55918], null, 1000 );

		$this->assertCount( 29, $values );
		$this->assertSame(
			array(
				'id_activity_term',
				'id_activity',
				'id_course',
				'match_method',
				'activity_name',
				'match_key',
				'stamp_from',
				'stamp_to',
				'lesson_date',
				'time_from',
				'time_to',
				'id_tab',
				'tab_name',
				'id_lane',
				'lane_name',
				'id_trainer',
				'trainer_name',
				'price',
				'capacity',
				'capacity_waiting',
				'occupied',
				'available',
				'available_waiting',
				'canceled',
				'booking_allowed',
				'is_external',
				'status',
				'payload',
				'synced_at',
			),
			array_keys( $values )
		);
	}
}
