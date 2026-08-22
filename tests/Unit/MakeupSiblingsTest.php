<?php
/**
 * Tests for proposing a make-up lesson's course from its repeats.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Sync\MakeupSiblings;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the rule that turns "somebody already said" into an offer.
 *
 * @covers \CSCS\Sync\MakeupSiblings
 */
final class MakeupSiblingsTest extends TestCase {

	/**
	 * Builds a stored row.
	 *
	 * @param int    $term_id Occurrence id.
	 * @param string $date    Date, Y-m-d.
	 * @param string $time    Start time.
	 * @param string $room    Room name.
	 * @param string $trainer Trainer name.
	 * @param string $name    Activity name.
	 * @return array<string, string|int>
	 */
	private function row( int $term_id, string $date, string $time, string $room, string $trainer = '', string $name = 'Náhradní lekce 4-6 let I.pololetí' ): array {
		return array(
			'id_activity_term' => $term_id,
			'activity_name'    => $name,
			'lesson_date'      => $date,
			'time_from'        => $time,
			'tab_name'         => $room,
			'trainer_name'     => $trainer,
		);
	}

	/**
	 * The next Thursday at half past three in hall one is the same slot as the
	 * last one, so the course somebody recorded there is offered here.
	 *
	 * @return void
	 */
	public function test_a_repeat_of_an_assigned_slot_is_offered_that_course(): void {
		$rows = array(
			$this->row( 55368, '2026-09-17', '15:30:00', 'Gymnastická hala 1' ),
			$this->row( 55359, '2026-10-01', '15:30:00', 'Gymnastická hala 1' ),
		);

		$this->assertSame(
			array( 55359 => 1072 ),
			( new MakeupSiblings() )->propose( $rows, array( 55368 => 1072 ) )
		);
	}

	/**
	 * The trainer is left out of the comparison on purpose: the timetable names
	 * one some weeks and not others, and a proposal that vanished for that
	 * reason would be worse than no proposal.
	 *
	 * @return void
	 */
	public function test_a_trainer_named_on_one_week_only_changes_nothing(): void {
		$rows = array(
			$this->row( 55368, '2026-09-17', '15:30:00', 'Gymnastická hala 1' ),
			$this->row( 55359, '2026-10-01', '15:30:00', 'Gymnastická hala 1', 'Lukáš Moutelík' ),
		);

		$this->assertSame(
			array( 55359 => 1072 ),
			( new MakeupSiblings() )->propose( $rows, array( 55368 => 1072 ) )
		);
	}

	/**
	 * A different weekday is a different slot, and a different slot may well
	 * serve a different course. Nothing is offered across one.
	 *
	 * @return void
	 */
	public function test_another_weekday_is_another_slot(): void {
		$rows = array(
			$this->row( 55368, '2026-09-17', '15:30:00', 'Gymnastická hala 1' ),
			$this->row( 55301, '2026-09-23', '15:30:00', 'Gymnastická hala 1' ),
		);

		$this->assertSame( array(), ( new MakeupSiblings() )->propose( $rows, array( 55368 => 1072 ) ) );
	}

	/**
	 * A different room, or a different hour, is likewise a different slot.
	 *
	 * @return void
	 */
	public function test_another_room_or_hour_is_another_slot(): void {
		$siblings = new MakeupSiblings();

		$this->assertSame(
			array(),
			$siblings->propose(
				array(
					$this->row( 55368, '2026-09-17', '15:30:00', 'Gymnastická hala 1' ),
					$this->row( 55359, '2026-10-01', '15:30:00', 'Gymnastická hala 2' ),
				),
				array( 55368 => 1072 )
			)
		);

		$this->assertSame(
			array(),
			$siblings->propose(
				array(
					$this->row( 55368, '2026-09-17', '15:30:00', 'Gymnastická hala 1' ),
					$this->row( 55359, '2026-10-01', '16:30:00', 'Gymnastická hala 1' ),
				),
				array( 55368 => 1072 )
			)
		);
	}

	/**
	 * Where two occurrences of one series were assigned to two different
	 * courses, the series is not a series and nothing is offered. This is the
	 * case the whole idea has to survive: the gym reuses one name across the
	 * age group, so a series can turn out to be two.
	 *
	 * @return void
	 */
	public function test_a_series_that_disagrees_offers_nothing(): void {
		$rows = array(
			$this->row( 55368, '2026-09-17', '15:30:00', 'Gymnastická hala 1' ),
			$this->row( 55359, '2026-10-01', '15:30:00', 'Gymnastická hala 1' ),
			$this->row( 55360, '2026-10-08', '15:30:00', 'Gymnastická hala 1' ),
		);

		$this->assertSame(
			array(),
			( new MakeupSiblings() )->propose(
				$rows,
				array(
					55368 => 1072,
					55359 => 1099,
				)
			)
		);
	}

	/**
	 * A series nobody has assigned anything in has nothing to copy.
	 *
	 * @return void
	 */
	public function test_a_series_with_no_assignment_offers_nothing(): void {
		$rows = array(
			$this->row( 55368, '2026-09-17', '15:30:00', 'Gymnastická hala 1' ),
			$this->row( 55359, '2026-10-01', '15:30:00', 'Gymnastická hala 1' ),
		);

		$this->assertSame( array(), ( new MakeupSiblings() )->propose( $rows, array() ) );
	}

	/**
	 * Two names that differ only in spacing are one name here, the same way
	 * they are one name everywhere else in the plugin.
	 *
	 * @return void
	 */
	public function test_spacing_in_the_name_does_not_split_a_series(): void {
		$rows = array(
			$this->row( 55368, '2026-09-17', '15:30:00', 'Gymnastická hala 1', '', 'Náhradní lekce 4-6 let I.pololetí' ),
			$this->row( 55359, '2026-10-01', '15:30:00', 'Gymnastická hala 1', '', 'Náhradní  lekce 4-6 let I. pololetí' ),
		);

		$this->assertSame(
			array( 55359 => 1072 ),
			( new MakeupSiblings() )->propose( $rows, array( 55368 => 1072 ) )
		);
	}

	/**
	 * A row without a usable date cannot be placed in a series, and is left
	 * alone rather than guessed at.
	 *
	 * @return void
	 */
	public function test_a_row_without_a_date_is_left_alone(): void {
		$siblings = new MakeupSiblings();

		$this->assertSame( '', $siblings->series_key( $this->row( 55368, '', '15:30:00', 'Gymnastická hala 1' ) ) );
		$this->assertSame( '', $siblings->series_key( $this->row( 55368, '2026-09', '15:30:00', 'Gymnastická hala 1' ) ) );
		$this->assertSame( '', $siblings->series_key( $this->row( 55368, '2026-09-17', '', 'Gymnastická hala 1' ) ) );
	}
}
