<?php
/**
 * Tests for the part of a listing a visitor chooses.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Render\ListingArgs;
use PHPUnit\Framework\TestCase;

/**
 * Every value here arrives from a URL, which is to say from anybody at all.
 *
 * @covers \CSCS\Render\ListingArgs
 */
final class ListingArgsTest extends TestCase {

	/**
	 * Nothing asked for is the listing as the set describes it.
	 *
	 * @return void
	 */
	public function test_nothing_asked_for_is_the_listing_itself(): void {
		$args = ListingArgs::from_array( array() );

		$this->assertSame( 1, $args->page );
		$this->assertSame( 0, $args->week );
		$this->assertSame( 0, $args->room );
		$this->assertSame( array(), $args->to_query() );
	}

	/**
	 * A page of nought, or of minus one, is the first page.
	 *
	 * @return void
	 */
	public function test_a_page_below_the_first_is_the_first(): void {
		$this->assertSame( 1, ListingArgs::from_array( array( 'page' => 0 ) )->page );
		$this->assertSame( 1, ListingArgs::from_array( array( 'page' => -7 ) )->page );
		$this->assertSame( 1, ListingArgs::from_array( array( 'page' => 'nonsense' ) )->page );
	}

	/**
	 * A term is weeks long, not centuries. A request for week nine hundred is
	 * not a person browsing, and answering it would mean a query over a range
	 * nothing in the table covers.
	 *
	 * @return void
	 */
	public function test_paging_and_stepping_are_held_to_a_range(): void {
		$this->assertSame( 60, ListingArgs::from_array( array( 'page' => 9000 ) )->page );
		$this->assertSame( 60, ListingArgs::from_array( array( 'week' => 900 ) )->week );
		$this->assertSame( -60, ListingArgs::from_array( array( 'week' => -900 ) )->week );
	}

	/**
	 * A room is an id or it is every room.
	 *
	 * @return void
	 */
	public function test_a_room_is_an_id_or_all_of_them(): void {
		$this->assertSame( 12, ListingArgs::from_array( array( 'room' => '12' ) )->room );
		$this->assertSame( 0, ListingArgs::from_array( array( 'room' => -3 ) )->room );
		$this->assertSame( 0, ListingArgs::from_array( array( 'room' => 'kitchen' ) )->room );
	}

	/**
	 * Changing the week or the room starts again at the first page: page four
	 * of last week has nothing to do with page four of this one.
	 *
	 * @return void
	 */
	public function test_changing_the_week_or_room_returns_to_the_first_page(): void {
		$args = ListingArgs::from_array( array( 'page' => 4, 'week' => 1, 'room' => 12 ) );

		$this->assertSame( 1, $args->with( 'week', 2 )->page );
		$this->assertSame( 1, $args->with( 'room', 7 )->page );
		$this->assertSame( 5, $args->with( 'page', 5 )->page );

		// What was not changed is kept.
		$this->assertSame( 12, $args->with( 'week', 2 )->room );
		$this->assertSame( 1, $args->with( 'room', 7 )->week );
	}

	/**
	 * The query it builds carries only what differs from the listing itself.
	 *
	 * @return void
	 */
	public function test_the_query_carries_only_what_was_chosen(): void {
		$args = ListingArgs::from_array( array( 'page' => 3, 'week' => -1, 'room' => 0 ) );

		$this->assertSame(
			array(
				'cscs_page' => 3,
				'cscs_week' => -1,
			),
			$args->to_query()
		);
	}

	/**
	 * The prefixed names are what a page URL carries, so that a listing cannot
	 * collide with whatever else the page reads from its query string.
	 *
	 * @return void
	 */
	public function test_a_request_is_read_under_prefixed_names(): void {
		$args = ListingArgs::from_request( array( 'cscs_page' => '2', 'cscs_week' => '1', 'page' => '9' ) );

		$this->assertSame( 2, $args->page );
		$this->assertSame( 1, $args->week );
	}
}
