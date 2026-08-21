<?php
/**
 * Result of a matching run.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Sync;

/**
 * What matching produced, and how well it went.
 *
 * The success rate deliberately ignores occurrences that share their name with
 * no course at all. Those are hall rentals and open public sessions, which have
 * no course to belong to; counting them as failures would bury the cases that
 * genuinely need a human.
 */
final class MatchResult {

	/**
	 * Constructor.
	 *
	 * @param array<int, Assignment>                          $assignments Assignments keyed by class occurrence id.
	 * @param array<int, array{reason: string, name: string}> $unmatched   Unmatched occurrences keyed by occurrence id.
	 * @param array<int, array<int, string>>                  $rooms       Room id to room name, keyed by course id.
	 */
	public function __construct(
		public readonly array $assignments,
		public readonly array $unmatched,
		public readonly array $rooms
	) {}

	/**
	 * Number of matched occurrences.
	 *
	 * @return int
	 */
	public function matched(): int {
		return count( $this->assignments );
	}

	/**
	 * Occurrences that belong to no course and were never expected to.
	 *
	 * @return int
	 */
	public function external(): int {
		return count( $this->by_reason( 'no_candidate' ) );
	}

	/**
	 * Occurrences that look like they should have matched but did not.
	 *
	 * These are the ones worth a human's attention.
	 *
	 * @return int
	 */
	public function problematic(): int {
		return count( $this->unmatched ) - $this->external();
	}

	/**
	 * Share of matchable occurrences that were matched, from 0 to 100.
	 *
	 * @return float
	 */
	public function rate(): float {
		$matchable = $this->matched() + $this->problematic();

		if ( 0 === $matchable ) {
			return 100.0;
		}

		return round( ( $this->matched() / $matchable ) * 100, 1 );
	}

	/**
	 * Returns unmatched occurrences with a given reason.
	 *
	 * @param string $reason Reason code.
	 * @return array<int, array{reason: string, name: string}>
	 */
	public function by_reason( string $reason ): array {
		return array_filter(
			$this->unmatched,
			static fn( array $entry ): bool => $entry['reason'] === $reason
		);
	}

	/**
	 * Counts assignments made by each method.
	 *
	 * @return array<string, int>
	 */
	public function methods(): array {
		$counts = array();

		foreach ( $this->assignments as $assignment ) {
			$counts[ $assignment->method ] = ( $counts[ $assignment->method ] ?? 0 ) + 1;
		}

		ksort( $counts );

		return $counts;
	}
}
