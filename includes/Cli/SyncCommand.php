<?php
/**
 * WP-CLI commands for synchronisation.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Cli;

use CSCS\Api\ApiException;
use CSCS\Plugin;
use CSCS\Sync\MatchResult;

defined( 'ABSPATH' ) || exit;

/**
 * Drives synchronisation, matching and retention from the command line.
 *
 * These exist so the data layer can be exercised and audited before any admin
 * screen exists, and so a support question can be answered without clicking.
 */
final class SyncCommand {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Registers the command namespace with WP-CLI.
	 *
	 * @param Plugin $plugin Plugin instance.
	 * @return void
	 */
	public static function register( Plugin $plugin ): void {
		\WP_CLI::add_command( 'cscs sync', new self( $plugin ) );
	}

	/**
	 * Refreshes the course list.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Bypass the cache.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs sync courses
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function courses( array $args, array $assoc_args ): void {
		try {
			$result = $this->plugin->synchroniser()->sync_courses( isset( $assoc_args['force'] ) );
		} catch ( ApiException $e ) {
			\WP_CLI::error( sprintf( '%s (%s)', $e->getMessage(), $e->get_reason() ) );

			return;
		}

		\WP_CLI::success(
			sprintf(
				'%d courses written, %d archived.',
				$result['records'],
				$result['archived']
			)
		);
	}

	/**
	 * Refreshes a window of class occurrences and re-runs matching over it.
	 *
	 * ## OPTIONS
	 *
	 * [--from=<Ymd>]
	 * : Start of the window. Defaults to today.
	 *
	 * [--to=<Ymd>]
	 * : End of the window.
	 *
	 * [--force]
	 * : Bypass the cache.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs sync lessons --from=20260911 --to=20261002
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function lessons( array $args, array $assoc_args ): void {
		try {
			$result = $this->plugin->synchroniser()->sync_lessons(
				'lessons_manual',
				isset( $assoc_args['from'] ) ? (string) $assoc_args['from'] : null,
				isset( $assoc_args['to'] ) ? (string) $assoc_args['to'] : null,
				isset( $assoc_args['force'] )
			);
		} catch ( ApiException $e ) {
			\WP_CLI::error( sprintf( '%s (%s)', $e->getMessage(), $e->get_reason() ) );

			return;
		}

		\WP_CLI::success( sprintf( '%d occurrences written.', $result['records'] ) );

		if ( $result['match'] instanceof MatchResult ) {
			$this->report( $result['match'] );
		}
	}

	/**
	 * Re-runs matching over everything stored, without contacting the API.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs sync rematch
	 *
	 * @return void
	 */
	public function rematch(): void {
		try {
			$match = $this->plugin->synchroniser()->rematch();
		} catch ( ApiException $e ) {
			\WP_CLI::error( sprintf( '%s (%s)', $e->getMessage(), $e->get_reason() ) );

			return;
		}

		$this->report( $match );
	}

	/**
	 * Lists occurrences that matched no course and are not external bookings.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Maximum rows.
	 * ---
	 * default: 50
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs sync unmatched
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function unmatched( array $args, array $assoc_args ): void {
		$rows = $this->plugin->lessons()->unmatched( (int) ( $assoc_args['limit'] ?? 50 ) );

		if ( array() === $rows ) {
			\WP_CLI::success( 'Every occurrence is either matched or a recognised external booking.' );

			return;
		}

		\WP_CLI\Utils\format_items(
			(string) ( $assoc_args['format'] ?? 'table' ),
			$rows,
			array( 'id_activity_term', 'lesson_date', 'time_from', 'activity_name', 'tab_name', 'trainer_name' )
		);
	}

	/**
	 * Ties one occurrence to a course permanently.
	 *
	 * A manual assignment survives every later synchronisation.
	 *
	 * ## OPTIONS
	 *
	 * <term>
	 * : Occurrence id.
	 *
	 * <course>
	 * : Remote course id, or 0 to clear the assignment.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs sync assign 55918 1070
	 *
	 * @param array<int, string> $args Positional arguments.
	 * @return void
	 */
	public function assign( array $args ): void {
		$term   = (int) ( $args[0] ?? 0 );
		$course = (int) ( $args[1] ?? 0 );

		if ( 0 === $term ) {
			\WP_CLI::error( 'An occurrence id is required.' );

			return;
		}

		$this->plugin->lessons()->assign_manually( $term, $course );

		\WP_CLI::success(
			0 === $course
				? sprintf( 'Cleared the assignment for occurrence %d.', $term )
				: sprintf( 'Occurrence %d is now tied to course %d.', $term, $course )
		);

		\WP_CLI::log( 'Run "wp cscs sync rematch" to apply it.' );
	}

	/**
	 * Applies the retention rules.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs sync retention
	 *
	 * @return void
	 */
	public function retention(): void {
		$result = $this->plugin->retention()->run();

		\WP_CLI::success(
			sprintf(
				'%d occurrences removed, %d log rows removed, %d courses closed.',
				$result['lessons_removed'],
				$result['log_removed'],
				$result['courses_finished']
			)
		);
	}

	/**
	 * Prints how matching went.
	 *
	 * @param MatchResult $outcome Matching outcome.
	 * @return void
	 */
	private function report( MatchResult $outcome ): void {
		\WP_CLI::log( '' );
		\WP_CLI::log( sprintf( 'Matched:        %d', $outcome->matched() ) );
		\WP_CLI::log( sprintf( 'External:       %d (rentals and open sessions, no course expected)', $outcome->external() ) );
		\WP_CLI::log( sprintf( 'Unresolved:     %d', $outcome->problematic() ) );
		\WP_CLI::log( sprintf( 'Match rate:     %.1f%%', $outcome->rate() ) );

		foreach ( $outcome->methods() as $method => $count ) {
			\WP_CLI::log( sprintf( '  via %-8s %d', $method, $count ) );
		}

		$reasons = array();

		foreach ( $outcome->unmatched as $entry ) {
			$reasons[ $entry['reason'] ] = ( $reasons[ $entry['reason'] ] ?? 0 ) + 1;
		}

		unset( $reasons['no_candidate'] );

		foreach ( $reasons as $reason => $count ) {
			\WP_CLI::warning( sprintf( '%d occurrence(s) unresolved: %s', $count, $reason ) );
		}

		$by_name = $outcome->unresolved_by_name();

		if ( array() !== $by_name ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( sprintf( 'Unresolved activities (%d distinct):', count( $by_name ) ) );

			foreach ( array_slice( $by_name, 0, 15, true ) as $name => $count ) {
				\WP_CLI::log( sprintf( '  %3d x  %s', $count, $name ) );
			}
		}

		if ( $outcome->rate() < 95.0 ) {
			\WP_CLI::warning( 'The match rate is below 95%. Run "wp cscs sync unmatched" to see what is left.' );
		}
	}
}
