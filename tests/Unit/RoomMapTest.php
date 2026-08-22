<?php
/**
 * Tests for room naming and ordering.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Data\RoomMap;
use PHPUnit\Framework\TestCase;

/**
 * Guards what the website calls a room and in what order it lists them.
 *
 * @covers \CSCS\Data\RoomMap
 */
final class RoomMapTest extends TestCase {

	/**
	 * A room nobody has configured keeps the name iSport gives it.
	 *
	 * @return void
	 */
	public function test_an_unconfigured_room_keeps_its_remote_name(): void {
		$rooms = RoomMap::apply( array( 4 => 'Gymnastická hala 1 a veřejnost' ), array() );

		$this->assertSame( 'Gymnastická hala 1 a veřejnost', $rooms[0]['name'] );
		$this->assertSame( 'Gymnastická hala 1 a veřejnost', $rooms[0]['remote'] );
		$this->assertFalse( $rooms[0]['hidden'] );
		$this->assertSame( '', $rooms[0]['colour'] );
	}

	/**
	 * The label is keyed by the remote id, so renaming a room in iSport does
	 * not throw away what the site decided to call it.
	 *
	 * @return void
	 */
	public function test_a_label_survives_a_rename_in_isport(): void {
		$map = array( 4 => RoomMap::row( array( 'label' => 'Hala 1' ) ) );

		$rooms = RoomMap::apply( array( 4 => 'Gymnastická hala 1 — nový název' ), $map );

		$this->assertSame( 'Hala 1', $rooms[0]['name'] );
		$this->assertSame( 'Gymnastická hala 1 — nový název', $rooms[0]['remote'] );
	}

	/**
	 * Rooms sharing an order fall back to alphabetical, so leaving every order
	 * at nought produces a sensible list rather than the database's whim.
	 *
	 * @return void
	 */
	public function test_rooms_of_equal_order_are_alphabetical(): void {
		$rooms = RoomMap::apply(
			array(
				7 => 'Sál B',
				4 => 'Sál A',
				9 => 'Sál C',
			),
			array( 9 => RoomMap::row( array( 'order' => 1 ) ) )
		);

		$this->assertSame( array( 'Sál A', 'Sál B', 'Sál C' ), array_column( $rooms, 'name' ) );

		$rooms = RoomMap::apply(
			array(
				7 => 'Sál B',
				4 => 'Sál A',
			),
			array( 7 => RoomMap::row( array( 'order' => 0 ) ), 4 => RoomMap::row( array( 'order' => 5 ) ) )
		);

		$this->assertSame( array( 'Sál B', 'Sál A' ), array_column( $rooms, 'name' ) );
	}

	/**
	 * A hidden room stays in the list this screen edits. One that vanished the
	 * moment it was hidden could never be shown again.
	 *
	 * @return void
	 */
	public function test_a_hidden_room_is_marked_rather_than_dropped(): void {
		$rooms = RoomMap::apply(
			array( 4 => 'Sál A' ),
			array( 4 => RoomMap::row( array( 'hidden' => '1' ) ) )
		);

		$this->assertCount( 1, $rooms );
		$this->assertTrue( $rooms[0]['hidden'] );
	}

	/**
	 * A colour is a colour or it is nothing, and it is stored the way the API
	 * stores one — six characters, no hash — so that the two never have to be
	 * told apart further down.
	 *
	 * @return void
	 */
	public function test_a_colour_is_a_colour_or_nothing(): void {
		$this->assertSame( 'ff0000', RoomMap::row( array( 'colour' => '#FF0000' ) )['colour'] );
		$this->assertSame( 'ff0000', RoomMap::row( array( 'colour' => 'ff0000' ) )['colour'] );
		$this->assertSame( '', RoomMap::row( array( 'colour' => 'červená' ) )['colour'] );
	}

	/**
	 * A row that says nothing is not stored, or the option would grow by one
	 * every time a new room turned up in the timetable and mean nothing.
	 *
	 * @return void
	 */
	public function test_rooms_nobody_configured_are_not_stored(): void {
		cscs_reset_test_state();

		$map = new RoomMap();

		$stored = $map->save(
			array(
				4 => array( 'label' => '', 'order' => 0, 'colour' => '', 'hidden' => false ),
				7 => array( 'label' => 'Hala 2' ),
			)
		);

		$this->assertSame( 1, $stored );
		$this->assertSame( array( 7 ), array_keys( $map->all() ) );
	}
}
