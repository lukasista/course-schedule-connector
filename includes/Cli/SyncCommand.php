<?php
/**
 * WP-CLI commands for synchronisation.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Cli;

defined( 'ABSPATH' ) || exit;

use CSCS\Api\ApiException;
use CSCS\Data\Audience;
use CSCS\Data\CourseKind;
use CSCS\Data\KindType;
use CSCS\Data\CourseRepository;
use CSCS\Data\PostType;
use CSCS\Plugin;
use CSCS\Sync\MatchResult;

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
			array( 'id_activity_term', 'lesson_date', 'time_from', 'activity_name', 'tags', 'tab_name', 'trainer_name' )
		);
	}

	/**
	 * Lists occurrences of a given kind.
	 *
	 * Use this to check what the non-bookable list actually caught. A name
	 * compared as a substring is a blunt instrument, and an entry that is too
	 * broad would quietly reclassify things nobody meant it to.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Which kind to list.
	 * ---
	 * default: not_bookable
	 * options:
	 *   - matched
	 *   - external
	 *   - not_bookable
	 *   - makeup
	 *   - unresolved
	 * ---
	 *
	 * [--limit=<number>]
	 * : Maximum rows.
	 * ---
	 * default: 100
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
	 *     wp cscs sync list --status=not_bookable
	 *     wp cscs sync list --status=external --format=count
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function list( array $args, array $assoc_args ): void {
		$rows = $this->plugin->lessons()->by_status(
			(string) ( $assoc_args['status'] ?? 'not_bookable' ),
			(int) ( $assoc_args['limit'] ?? 100 )
		);

		if ( array() === $rows ) {
			\WP_CLI::success( 'Nothing of that kind is stored.' );

			return;
		}

		\WP_CLI\Utils\format_items(
			(string) ( $assoc_args['format'] ?? 'table' ),
			$rows,
			array( 'id_activity_term', 'lesson_date', 'time_from', 'activity_name', 'tags', 'tab_name', 'trainer_name' )
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
	 * Fills in the description of every kind page that has none.
	 *
	 * The same thing the button on the overview screen does, for the sites that
	 * are set up from a terminal.
	 *
	 * ## OPTIONS
	 *
	 * [--overwrite]
	 * : Replace what is written on a page too, rather than only filling blanks.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs sync descriptions
	 *     wp cscs sync descriptions --overwrite
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 * @return void
	 */
	public function descriptions( array $args, array $assoc_args ): void {
		unset( $args );

		$outcome = $this->plugin->kinds()->fill_descriptions( isset( $assoc_args['overwrite'] ) );

		\WP_CLI::success(
			sprintf(
				'%d written, %d left as they were, %d with nothing in iSport to take.',
				$outcome['written'],
				$outcome['kept'],
				$outcome['empty']
			)
		);
	}

	/**
	 * Stops the scheduled jobs, or starts them again.
	 *
	 * ## OPTIONS
	 *
	 * [<state>]
	 * : on to pause, off to resume. Omitted, it reports which it is.
	 * ---
	 * options:
	 *   - on
	 *   - off
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs sync pause
	 *     wp cscs sync pause on
	 *     wp cscs sync pause off
	 *
	 * @param array<int, string> $args Positional arguments.
	 * @return void
	 */
	public function pause( array $args ): void {
		$scheduler = $this->plugin->scheduler();
		$state     = isset( $args[0] ) ? strtolower( (string) $args[0] ) : '';

		if ( 'on' === $state ) {
			$scheduler->pause();

			\WP_CLI::success( 'Scheduled synchronisation is paused.' );

			return;
		}

		if ( 'off' === $state ) {
			$scheduler->resume();

			\WP_CLI::success( 'Scheduled synchronisation has started again.' );

			return;
		}

		\WP_CLI::line( $scheduler->is_paused() ? 'paused' : 'running' );
	}

	/**
	 * Files every stored course under the kind of course its name says it is.
	 *
	 * The next synchronisation would do this on its own, one course at a time.
	 * This is for the day the kinds are added, when waiting for a whole cycle
	 * to pass means a website with no cards on it.
	 *
	 * A course whose kind somebody has locked is left alone and counted apart,
	 * the same way the audience is.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Print what would be written and write nothing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs sync kinds --dry-run
	 *     wp cscs sync kinds
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 * @return void
	 */
	public function kinds( array $args, array $assoc_args ): void {
		unset( $args );

		$dry     = isset( $assoc_args['dry-run'] );
		$posts   = get_posts(
			array(
				'post_type'        => PostType::COURSE,
				'post_status'      => 'any',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => false,
			)
		);
		$read    = 0;
		$kept    = 0;
		$nothing = 0;
		$groups  = array();

		foreach ( $posts as $post_id ) {
			$post_id = (int) $post_id;
			$title   = (string) get_post_field( 'post_title', $post_id );
			$locked  = in_array( PostType::KIND, $this->plugin->courses()->locked_fields( $post_id ), true );
			$kind    = CourseKind::read_for(
				(string) get_post_meta( $post_id, '_cscs_activity_name', true ),
				$title
			);

			if ( $locked ) {
				++$kept;

				$terms = wp_get_object_terms( $post_id, PostType::KIND, array( 'fields' => 'names' ) );
				$kind  = is_array( $terms ) && array() !== $terms ? (string) $terms[0] : '';
			} elseif ( '' === $kind ) {
				++$nothing;

				if ( ! $dry ) {
					wp_set_object_terms( $post_id, array(), PostType::KIND );
				}
			} else {
				++$read;

				if ( ! $dry ) {
					wp_set_object_terms( $post_id, array( $kind ), PostType::KIND );
					$this->plugin->kinds()->ensure( $kind );
				}
			}

			$label = ( '' === $kind ? '—' : $kind ) . ( $locked ? ' *' : '' );

			if ( ! isset( $groups[ $label ] ) ) {
				$groups[ $label ] = 0;
			}

			++$groups[ $label ];
		}

		$described = 0;

		if ( ! $dry ) {
			foreach ( get_posts(
				array(
					'post_type'        => KindType::KIND,
					'post_status'      => 'any',
					'numberposts'      => -1,
					'fields'           => 'ids',
					'suppress_filters' => false,
				)
			) as $page_id ) {
				if ( $this->plugin->kinds()->fill_description( (int) $page_id ) ) {
					++$described;
				}
			}
		}

		ksort( $groups );

		// One line per kind rather than per course: the question this answers
		// is "what cards does the catalogue fall into", and 113 lines is not an
		// answer to it.
		foreach ( $groups as $label => $count ) {
			\WP_CLI::log( sprintf( '%-56s %d', mb_substr( (string) $label, 0, 56 ), $count ) );
		}

		\WP_CLI::success(
			sprintf(
				'%d courses in %d kinds: %d read from a name, %d left to a name that says nothing, %d kept as somebody set them (marked *). %d pages given the description their courses share.%s',
				count( $posts ),
				count( $groups ),
				$read,
				$nothing,
				$kept,
				$described,
				$dry ? ' Nothing was written.' : ''
			)
		);
	}

	/**
	 * Reads the audience and the level out of every stored course's name.
	 *
	 * The next synchronisation would do this on its own, one course at a time.
	 * This is for the day the fields are added, when waiting for a whole cycle
	 * to pass means a website that says nothing about who a course is for.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Print what would be written and write nothing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs sync audience --dry-run
	 *     wp cscs sync audience
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 * @return void
	 */
	public function audience( array $args, array $assoc_args ): void {
		unset( $args );

		$dry     = isset( $assoc_args['dry-run'] );
		$posts   = get_posts(
			array(
				'post_type'        => PostType::COURSE,
				'post_status'      => 'any',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => false,
			)
		);
		$read    = 0;
		$kept    = 0;
		$nothing = 0;

		foreach ( $posts as $post_id ) {
			$post_id = (int) $post_id;
			$title   = (string) get_post_field( 'post_title', $post_id );
			$found   = Audience::read( $title );
			$locked  = $this->plugin->courses()->locked_fields( $post_id );

			// What the row shows is what the course ends up with, not what its
			// name says: on a course somebody has set by hand those are two
			// different answers, and printing the reading there would report a
			// change that did not happen.
			$shown = array();

			$reading = array(
				CourseRepository::META_GENDER   => $found['gender'],
				CourseRepository::META_LEVEL    => $found['level'],
				CourseRepository::META_AGE_FROM => $found['age_from'],
				CourseRepository::META_AGE_TO   => $found['age_to'],
			);

			foreach ( $reading as $key => $value ) {
				if ( in_array( $key, $locked, true ) ) {
					++$kept;

					$stored        = (string) get_post_meta( $post_id, $key, true );
					$shown[ $key ] = ( '' === $stored ? '—' : $stored ) . ' *';

					continue;
				}

				$shown[ $key ] = '' === $value ? '—' : $value;

				if ( '' === $value ) {
					++$nothing;

					if ( ! $dry ) {
						delete_post_meta( $post_id, $key );
					}

					continue;
				}

				++$read;

				if ( ! $dry ) {
					update_post_meta( $post_id, $key, $value );
				}
			}

			// Four columns rather than a rendered range, because this is the
			// screen somebody checks a reading on: what is stored is what is
			// worth seeing, and an empty ceiling is a fact of its own.
			\WP_CLI::log(
				sprintf(
					'%-56s %-10s %-10s %-8s %s',
					mb_substr( $title, 0, 56 ),
					$shown[ CourseRepository::META_GENDER ],
					$shown[ CourseRepository::META_LEVEL ],
					$shown[ CourseRepository::META_AGE_FROM ],
					$shown[ CourseRepository::META_AGE_TO ]
				)
			);
		}

		\WP_CLI::success(
			sprintf(
				'%d courses: %d values read from a name, %d left to a name that says nothing, %d kept as somebody set them (marked *).%s',
				count( $posts ),
				$read,
				$nothing,
				$kept,
				$dry ? ' Nothing was written.' : ''
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
		\WP_CLI::log( sprintf( 'Not bookable:   %d (outside lecturers, individual training)', $outcome->not_bookable() ) );
		\WP_CLI::log( sprintf( 'Make-up:        %d (replacements for a missed class)', $outcome->makeup() ) );
		\WP_CLI::log( sprintf( 'Unresolved:     %d', $outcome->problematic() ) );
		\WP_CLI::log( sprintf( 'Match rate:     %.1f%%', $outcome->rate() ) );

		foreach ( $outcome->methods() as $method => $count ) {
			\WP_CLI::log( sprintf( '  via %-8s %d', $method, $count ) );
		}

		$reasons = array();

		foreach ( $outcome->unmatched as $entry ) {
			$reasons[ $entry['reason'] ] = ( $reasons[ $entry['reason'] ] ?? 0 ) + 1;
		}

		unset( $reasons['no_candidate'], $reasons['not_bookable'], $reasons['makeup'] );

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
