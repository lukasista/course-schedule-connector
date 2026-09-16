<?php
/**
 * Tests for working out which kind pages to keep a trainer paired with.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Data\KindRepository;
use PHPUnit\Framework\TestCase;

/**
 * The arithmetic behind {@see KindRepository::refresh_trainer_relationships()}.
 *
 * Reading and writing the relationship needs a database this suite does not
 * have; deciding what to add and what to drop, given what is stored and what
 * should be, does not — so that is the part kept pure and checked here.
 *
 * @covers \CSCS\Data\KindRepository
 */
final class TrainerKindRelationshipTest extends TestCase {

	/**
	 * What is stored already matches what is wanted: nothing to do.
	 *
	 * @return void
	 */
	public function test_agreement_needs_no_change(): void {
		$diff = $this->call( array( 5, 9 ), array( 9, 5 ) );

		$this->assertSame( array(), $diff['add'] );
		$this->assertSame( array(), $diff['remove'] );
	}

	/**
	 * A trainer newly teaching under a page gets the row added.
	 *
	 * @return void
	 */
	public function test_a_new_page_is_added(): void {
		$diff = $this->call( array(), array( 900515 ) );

		$this->assertSame( array( 900515 ), $diff['add'] );
		$this->assertSame( array(), $diff['remove'] );
	}

	/**
	 * A trainer who no longer teaches any shown course of a page loses the
	 * row — this is how Gymnastika dívky and Gymnastika kluci, which share
	 * one taxonomy term but not one roster, each end up with only their own.
	 *
	 * @return void
	 */
	public function test_a_page_no_longer_earned_is_removed(): void {
		$diff = $this->call( array( 900366 ), array() );

		$this->assertSame( array(), $diff['add'] );
		$this->assertSame( array( 900366 ), $diff['remove'] );
	}

	/**
	 * A gain and a loss at once are both carried out, not just one.
	 *
	 * @return void
	 */
	public function test_additions_and_removals_happen_together(): void {
		$diff = $this->call( array( 1, 2 ), array( 2, 3 ) );

		$this->assertSame( array( 3 ), $diff['add'] );
		$this->assertSame( array( 1 ), $diff['remove'] );
	}

	/**
	 * The order stored in is not the order that arrived, so it must not be
	 * mistaken for a change.
	 *
	 * @return void
	 */
	public function test_order_does_not_matter(): void {
		$diff = $this->call( array( 3, 1, 2 ), array( 2, 3, 1 ) );

		$this->assertSame( array(), $diff['add'] );
		$this->assertSame( array(), $diff['remove'] );
	}

	/**
	 * Calls the private helper.
	 *
	 * @param array<int, int> $have   Page ids currently stored.
	 * @param array<int, int> $should Page ids that should be stored.
	 * @return array{add: array<int, int>, remove: array<int, int>}
	 */
	private function call( array $have, array $should ): array {
		$callable = new \ReflectionMethod( KindRepository::class, 'diff_trainer_relationships' );
		$callable->setAccessible( true );

		return (array) $callable->invoke( null, $have, $should );
	}
}
