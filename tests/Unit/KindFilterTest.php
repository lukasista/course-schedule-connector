<?php
/**
 * Tests for narrowing a kind's timetable.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Render\KindDetail;
use PHPUnit\Framework\TestCase;

/**
 * One kind of course, shown as two tables.
 *
 * The gym publishes "Gymnastika" as one card, and wants the girls' hours and
 * the boys' as two. Refiling twenty-two courses under a kind invented to hold
 * half of them says nothing the courses do not already say, so the block asks
 * instead — and these are the questions it asks.
 *
 * @covers \CSCS\Render\KindDetail
 */
final class KindFilterTest extends TestCase {

	/**
	 * Calls one of the private helpers.
	 *
	 * @param string                           $method   Method.
	 * @param array<int, array<string, mixed>> $rows     Rows.
	 * @param array<string, mixed>             $settings Settings.
	 * @return array<int, array<string, mixed>>
	 */
	private function call( string $method, array $rows, array $settings ): array {
		$callable = new \ReflectionMethod( KindDetail::class, $method );
		$callable->setAccessible( true );

		return (array) $callable->invoke( null, $rows, $settings );
	}

	/**
	 * The rows of a small kind.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function rows(): array {
		return array(
			array(
				'name'      => '28-Gymnastika 6-9 let dívky začátečníci',
				'gender'    => 'girls',
				'level'     => 'beginner',
				'age_from'  => '6',
				'age_to'    => '9',
				'price'     => '5160.00',
				'available' => 6,
				'date_from' => '2026-09-09',
			),
			array(
				'name'      => '47-Gymnastika 7-9 let kluci',
				'gender'    => 'boys',
				'level'     => '',
				'age_from'  => '7',
				'age_to'    => '9',
				'price'     => '4160.00',
				'available' => 8,
				'date_from' => '2026-09-07',
			),
			array(
				'name'      => '43-Gymnastika 12-15 let dívky',
				'gender'    => 'girls',
				'level'     => '',
				'age_from'  => '12',
				'age_to'    => '15',
				'price'     => '4160.00',
				'available' => 1,
				'date_from' => '2026-09-08',
			),
			array(
				'name'      => '110-Funkční kruhový trénink',
				'gender'    => '',
				'level'     => '',
				'age_from'  => '',
				'age_to'    => '',
				'price'     => '3000.00',
				'available' => 4,
				'date_from' => '2026-09-10',
			),
		);
	}

	/**
	 * Nothing asked, nothing removed.
	 *
	 * @return void
	 */
	public function test_a_module_that_asks_nothing_shows_the_whole_kind(): void {
		$this->assertCount( 4, $this->call( 'filtered', $this->rows(), array() ) );
	}

	/**
	 * The girls' hours are the girls' hours.
	 *
	 * @return void
	 */
	public function test_one_kind_becomes_two_tables(): void {
		$girls = $this->call( 'filtered', $this->rows(), array( 'filterGenders' => 'girls' ) );
		$boys  = $this->call( 'filtered', $this->rows(), array( 'filterGenders' => 'boys' ) );

		$this->assertCount( 2, $girls );
		$this->assertCount( 1, $boys );
		$this->assertSame( '47-Gymnastika 7-9 let kluci', $boys[0]['name'] );
	}

	/**
	 * A course that says nothing is not quietly counted as everything.
	 *
	 * @return void
	 */
	public function test_a_course_that_says_nothing_answers_no_question(): void {
		$asked = $this->call( 'filtered', $this->rows(), array( 'filterGenders' => 'mixed' ) );

		$this->assertSame( array(), $asked );
	}

	/**
	 * An age asked of a course with no age leaves it out.
	 *
	 * @return void
	 */
	public function test_the_age_cuts_at_both_ends(): void {
		$young = $this->call( 'filtered', $this->rows(), array( 'filterAgeMax' => '9' ) );
		$names = array_column( $young, 'name' );

		$this->assertCount( 2, $young );
		$this->assertNotContains( '43-Gymnastika 12-15 let dívky', $names );
		$this->assertNotContains( '110-Funkční kruhový trénink', $names );

		$old = $this->call( 'filtered', $this->rows(), array( 'filterAgeMin' => '10' ) );

		$this->assertCount( 1, $old );
		$this->assertSame( '43-Gymnastika 12-15 let dívky', $old[0]['name'] );
	}

	/**
	 * Two questions are both asked, not either.
	 *
	 * @return void
	 */
	public function test_the_questions_are_asked_together(): void {
		$asked = $this->call(
			'filtered',
			$this->rows(),
			array(
				'filterGenders' => 'girls',
				'filterLevels'  => 'beginner',
			)
		);

		$this->assertCount( 1, $asked );
		$this->assertSame( '28-Gymnastika 6-9 let dívky začátečníci', $asked[0]['name'] );
	}

	/**
	 * A word the vocabulary does not know asks nothing.
	 *
	 * @return void
	 */
	public function test_a_key_nobody_recognises_is_not_a_filter(): void {
		$asked = $this->call( 'filtered', $this->rows(), array( 'filterGenders' => 'nonsense' ) );

		$this->assertCount( 4, $asked );
	}

	/**
	 * Sorting is on the numbers where the values are numbers.
	 *
	 * @return void
	 */
	public function test_a_price_sorts_as_a_number_and_a_name_as_a_name(): void {
		$cheap = $this->call( 'sorted', $this->rows(), array( 'filterSort' => 'price' ) );

		$this->assertSame( '3000.00', $cheap[0]['price'] );
		$this->assertSame( '5160.00', $cheap[3]['price'] );

		$down = $this->call(
			'sorted',
			$this->rows(),
			array(
				'filterSort'  => 'places',
				'filterOrder' => 'desc',
			)
		);

		$this->assertSame( 8, $down[0]['available'] );
		$this->assertSame( 1, $down[3]['available'] );
	}

	/**
	 * An order nobody asked for is the order they were filed in.
	 *
	 * @return void
	 */
	public function test_no_order_asked_is_the_order_they_came_in(): void {
		$rows = $this->rows();

		$this->assertSame( $rows, $this->call( 'sorted', $rows, array() ) );
		$this->assertSame( $rows, $this->call( 'sorted', $rows, array( 'filterSort' => 'nonsense' ) ) );
	}
}
