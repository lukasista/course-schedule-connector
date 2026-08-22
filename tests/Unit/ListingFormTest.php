<?php
/**
 * Tests for what the room filter has to carry across.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Render\ListingArgs;
use PHPUnit\Framework\TestCase;

/**
 * A form that loses half the address is a filter that undoes the rest of the
 * page, so what it carries is a rule worth pinning down.
 *
 * @covers \CSCS\Render\ListingArgs::carried
 */
final class ListingFormTest extends TestCase {

	/**
	 * With nothing else in the address the form carries nothing.
	 *
	 * @return void
	 */
	public function test_a_plain_address_carries_nothing(): void {
		$args = ListingArgs::from_array( array() );

		$this->assertSame( array(), $args->carried( array() ) );
	}

	/**
	 * The week being looked at survives choosing a room.
	 *
	 * @return void
	 */
	public function test_the_week_survives(): void {
		$args = ListingArgs::from_array( array( 'week' => 2 ) );

		$this->assertSame( array( 'cscs_week' => '2' ), $args->carried( array() ) );
	}

	/**
	 * The page does not: another room starts at the first page.
	 *
	 * @return void
	 */
	public function test_the_page_is_dropped(): void {
		$args = ListingArgs::from_array(
			array(
				'page' => 4,
				'week' => 1,
			)
		);

		$this->assertSame( array( 'cscs_week' => '1' ), $args->carried( array() ) );
	}

	/**
	 * The room is left to the form itself.
	 *
	 * @return void
	 */
	public function test_the_room_is_left_to_the_form(): void {
		$args = ListingArgs::from_array( array( 'room' => 12 ) );

		$this->assertSame( array(), $args->carried( array( 'cscs_room' => '12' ) ) );
	}

	/**
	 * Whatever else the address said is put back.
	 *
	 * @return void
	 */
	public function test_other_parameters_are_kept(): void {
		$args = ListingArgs::from_array( array() );

		$this->assertSame(
			array(
				'utm_source' => 'newsletter',
				'lang'       => 'cs',
			),
			$args->carried(
				array(
					'utm_source' => 'newsletter',
					'lang'       => 'cs',
					'cscs_page'  => '3',
				)
			)
		);
	}

	/**
	 * Anything that is not a plain value is not a hidden field.
	 *
	 * @return void
	 */
	public function test_arrays_are_not_carried(): void {
		$args = ListingArgs::from_array( array() );

		$this->assertSame( array(), $args->carried( array( 'filter' => array( 'a', 'b' ) ) ) );
	}
}
