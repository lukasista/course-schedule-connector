<?php
/**
 * Tests for display sets.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Data\DisplaySet;
use CSCS\Data\DisplaySetRepository;
use PHPUnit\Framework\TestCase;

/**
 * Guards the shape of a set, because every listing on the site reads one.
 *
 * @covers \CSCS\Data\DisplaySet
 * @covers \CSCS\Data\DisplaySetRepository
 */
final class DisplaySetTest extends TestCase {

	/**
	 * An empty set is a usable set: every column, sensible defaults, nothing
	 * filtered out. A new set that rendered nothing would look broken.
	 *
	 * @return void
	 */
	public function test_a_set_made_from_nothing_is_still_usable(): void {
		$set = DisplaySet::from_array( array() );

		$this->assertSame( DisplaySet::TYPE_COURSES, $set->type );
		$this->assertSame( DisplaySet::catalogue( DisplaySet::TYPE_COURSES ), $set->columns );
		$this->assertSame( 'name', $set->sort );
		$this->assertSame( 'asc', $set->order );
		$this->assertSame( 'term', $set->range );
		$this->assertSame( 'inherit', $set->cancelled );
		$this->assertFalse( $set->include_rentals );
		$this->assertSame( 0, $set->per_page );
	}

	/**
	 * The order columns are given in is the order they are kept in, and a
	 * column nobody recognises is dropped rather than rendered empty.
	 *
	 * @return void
	 */
	public function test_columns_keep_their_order_and_drop_the_unknown(): void {
		$set = DisplaySet::from_array(
			array(
				'type'    => DisplaySet::TYPE_COURSES,
				'columns' => array( 'price', 'name', 'made_up', 'price' ),
			)
		);

		$this->assertSame( array( 'price', 'name' ), $set->columns );
	}

	/**
	 * Ticking nothing at all falls back to every column, for the same reason an
	 * empty set does.
	 *
	 * @return void
	 */
	public function test_a_set_with_no_columns_falls_back_to_all_of_them(): void {
		$set = DisplaySet::from_array(
			array(
				'type'    => DisplaySet::TYPE_SCHEDULE,
				'columns' => array( 'nonsense' ),
			)
		);

		$this->assertSame( DisplaySet::catalogue( DisplaySet::TYPE_SCHEDULE ), $set->columns );
	}

	/**
	 * A label belongs to a column that is shown. One left over from a column
	 * somebody unticked is not carried around for ever.
	 *
	 * @return void
	 */
	public function test_labels_follow_the_columns_that_survived(): void {
		$set = DisplaySet::from_array(
			array(
				'columns' => array( 'name', 'price' ),
				'labels'  => array(
					'name'    => "  Kurz \n ",
					'price'   => '',
					'trainer' => 'Lektor',
				),
			)
		);

		$this->assertSame( array( 'name' => 'Kurz' ), $set->labels );
	}

	/**
	 * A sort key that belongs to the other kind of listing is refused rather
	 * than stored, because a listing sorted by a column it does not have is a
	 * bug reported as "the order is random".
	 *
	 * @return void
	 */
	public function test_a_sort_key_from_the_other_listing_is_refused(): void {
		$set = DisplaySet::from_array(
			array(
				'type' => DisplaySet::TYPE_SCHEDULE,
				'sort' => 'places',
			)
		);

		$this->assertSame( 'start', $set->sort );
	}

	/**
	 * Numbers are held to a range rather than trusted.
	 *
	 * @return void
	 */
	public function test_numbers_are_held_to_a_range(): void {
		$set = DisplaySet::from_array(
			array(
				'per_page'   => 100000,
				'few_places' => -4,
				'range_days' => 0,
			)
		);

		$this->assertSame( 500, $set->per_page );
		$this->assertSame( 0, $set->few_places );
		$this->assertSame( 1, $set->range_days );
	}

	/**
	 * Text fields are one line of plain text, whatever was pasted in.
	 *
	 * @return void
	 */
	public function test_wording_is_reduced_to_one_line_of_plain_text(): void {
		$set = DisplaySet::from_array( array( 'heading' => "<b>Kurzy</b>\n<script>alert(1)</script> pro děti" ) );

		$this->assertSame( 'Kurzy pro děti', $set->heading );
	}

	/**
	 * A set survives a round trip through storage unchanged.
	 *
	 * @return void
	 */
	public function test_a_set_survives_being_stored_and_read_back(): void {
		cscs_reset_test_state();

		$repository = new DisplaySetRepository();

		$set = DisplaySet::from_array(
			array(
				'name'            => 'Kurzy pro děti',
				'type'            => DisplaySet::TYPE_COURSES,
				'columns'         => array( 'name', 'days', 'price' ),
				'rooms'           => array( 4, 7 ),
				'include_rentals' => true,
				'per_page'        => 12,
			)
		);

		$id = $repository->save( $set );

		$this->assertNotSame( '', $id );

		$read = $repository->find( $id );

		$this->assertInstanceOf( DisplaySet::class, $read );
		$this->assertSame( $set->to_array(), $read->to_array() );
	}

	/**
	 * A set with no name is not stored at all. An unnamed set cannot be chosen
	 * in a module, so it would be a row nobody could ever use.
	 *
	 * @return void
	 */
	public function test_a_set_without_a_name_is_refused(): void {
		cscs_reset_test_state();

		$repository = new DisplaySetRepository();

		$this->assertSame( '', $repository->save( DisplaySet::from_array( array( 'name' => '' ) ) ) );
		$this->assertSame( array(), $repository->all() );
	}

	/**
	 * Two sets named alike get two identifiers, because the identifier is what
	 * a shortcode is written in terms of.
	 *
	 * @return void
	 */
	public function test_two_sets_of_one_name_do_not_collide(): void {
		cscs_reset_test_state();

		$repository = new DisplaySetRepository();

		$first  = $repository->save( DisplaySet::from_array( array( 'name' => 'Rozvrh' ) ) );
		$second = $repository->save( DisplaySet::from_array( array( 'name' => 'Rozvrh' ) ) );

		$this->assertNotSame( $first, $second );
		$this->assertCount( 2, $repository->all() );
	}

	/**
	 * Deleting says whether there was anything to delete, so a screen can tell
	 * the difference between a deletion and a stale link.
	 *
	 * @return void
	 */
	public function test_deleting_reports_whether_there_was_anything_to_delete(): void {
		cscs_reset_test_state();

		$repository = new DisplaySetRepository();
		$id         = $repository->save( DisplaySet::from_array( array( 'name' => 'Rozvrh' ) ) );

		$this->assertTrue( $repository->delete( $id ) );
		$this->assertFalse( $repository->delete( $id ) );
	}
}
