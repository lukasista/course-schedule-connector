<?php
/**
 * WP-CLI commands for inspecting the API.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Cli;

use CSCS\Api\ApiException;
use CSCS\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes the API client on the command line.
 *
 * These commands exist so that the data layer can be verified before any user
 * interface exists, and so that a support question can be answered without
 * clicking through the admin.
 */
final class ApiCommand {

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
		\WP_CLI::add_command( 'cscs api', new self( $plugin ) );
	}

	/**
	 * Lists courses straight from the API.
	 *
	 * ## OPTIONS
	 *
	 * [--date=<Ymd>]
	 * : Only list courses from this date onwards.
	 *
	 * [--force]
	 * : Bypass the cache.
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
	 *     wp cscs api courses
	 *     wp cscs api courses --date=20260901 --format=json
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function courses( array $args, array $assoc_args ): void {
		try {
			$courses = $this->plugin->client()->get_courses(
				isset( $assoc_args['date'] ) ? (string) $assoc_args['date'] : null,
				isset( $assoc_args['force'] )
			);
		} catch ( ApiException $e ) {
			\WP_CLI::error( sprintf( '%s (%s)', $e->getMessage(), $e->get_reason() ) );

			return;
		}

		$rows = array();

		foreach ( $courses as $course ) {
			$rows[] = array(
				'id'       => $course->id,
				'name'     => $course->name,
				'room'     => $course->room_name,
				'trainer'  => $course->trainer_name,
				'from'     => (string) $course->date_from,
				'to'       => (string) $course->date_to,
				'price'    => null === $course->price ? '-' : $course->price,
				'terms'    => count( $course->terms ),
				'capacity' => $course->capacity,
				'free'     => $course->available,
			);
		}

		\WP_CLI\Utils\format_items(
			(string) ( $assoc_args['format'] ?? 'table' ),
			$rows,
			array( 'id', 'name', 'room', 'trainer', 'from', 'to', 'price', 'terms', 'capacity', 'free' )
		);
	}

	/**
	 * Lists class occurrences straight from the API.
	 *
	 * ## OPTIONS
	 *
	 * [--from=<Ymd>]
	 * : Start of the date range.
	 *
	 * [--to=<Ymd>]
	 * : End of the date range.
	 *
	 * [--tab=<id>]
	 * : Restrict to one tab, which is how the API expresses a room.
	 *
	 * [--limit=<number>]
	 * : Maximum number of records.
	 *
	 * [--force]
	 * : Bypass the cache.
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
	 *     wp cscs api lessons --from=20260911 --to=20260911
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function lessons( array $args, array $assoc_args ): void {
		try {
			$lessons = $this->plugin->client()->get_lessons(
				isset( $assoc_args['from'] ) ? (string) $assoc_args['from'] : null,
				isset( $assoc_args['to'] ) ? (string) $assoc_args['to'] : null,
				isset( $assoc_args['tab'] ) ? (int) $assoc_args['tab'] : null,
				isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : null,
				isset( $assoc_args['force'] )
			);
		} catch ( ApiException $e ) {
			\WP_CLI::error( sprintf( '%s (%s)', $e->getMessage(), $e->get_reason() ) );

			return;
		}

		$rows = array();

		foreach ( $lessons as $lesson ) {
			$rows[] = array(
				'term'     => $lesson->id_term,
				'date'     => (string) $lesson->date,
				'time'     => (string) $lesson->time_from . '-' . (string) $lesson->time_to,
				'activity' => $lesson->activity_name,
				'room'     => $lesson->tab_name,
				'trainer'  => $lesson->trainer_name,
				'price'    => null === $lesson->price ? '-' : $lesson->price,
				'free'     => $lesson->available,
				'canceled' => $lesson->canceled ? 'yes' : 'no',
			);
		}

		\WP_CLI\Utils\format_items(
			(string) ( $assoc_args['format'] ?? 'table' ),
			$rows,
			array( 'term', 'date', 'time', 'activity', 'room', 'trainer', 'price', 'free', 'canceled' )
		);
	}

	/**
	 * Reports on configuration and connectivity.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs api doctor
	 *
	 * @return void
	 */
	public function doctor(): void {
		$settings = $this->plugin->settings();
		$client   = $this->plugin->client();

		$base = $settings->api_base_url();

		\WP_CLI::log( 'Base URL:            ' . ( '' === $base ? '(not configured or rejected)' : $base ) );
		\WP_CLI::log( 'Requests this hour:  ' . $client->requests_this_hour() );
		\WP_CLI::log( 'Consecutive errors:  ' . $client->breaker()->failures() );
		\WP_CLI::log( 'Circuit:             ' . ( $client->breaker()->is_closed() ? 'closed' : 'open until ' . gmdate( 'H:i:s', $client->breaker()->open_until() ) ) );

		if ( '' === $base ) {
			\WP_CLI::warning( 'Set a base URL before running a synchronisation.' );

			return;
		}

		try {
			$courses = $client->get_courses( null, true );
			\WP_CLI::success( sprintf( 'Reached the API and read %d courses.', count( $courses ) ) );
		} catch ( ApiException $e ) {
			\WP_CLI::error( sprintf( 'Could not reach the API: %s (%s)', $e->getMessage(), $e->get_reason() ), false );
		}
	}
}
