<?php
/**
 * WP-CLI commands for make-up lessons.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Cli;

use CSCS\Data\LessonRepository;
use CSCS\Plugin;
use CSCS\Sync\MakeupResolver;

defined( 'ABSPATH' ) || exit;

/**
 * Ties each make-up lesson to the course it stands in for.
 *
 * A make-up lesson replaces a class in exactly one course, but its name never
 * says which: the timetable calls it "Náhradní lekce 4-6 let" and several
 * courses run for that age group. Nothing in the data resolves it, so a person
 * does — once per activity name rather than once per occurrence, because the
 * same lesson repeats weekly and a link recorded now should cover the ones
 * that arrive next month too.
 */
final class MakeupCommand {

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
		\WP_CLI::add_command( 'cscs makeup', new self( $plugin ) );
	}

	/**
	 * Lists make-up lessons and the courses they are tied to.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs makeup list
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function list( array $args, array $assoc_args ): void {
		$repository = $this->plugin->lessons();
		$links      = $repository->makeup_links();
		$courses    = $this->course_names();
		$rows       = array();
		$names      = array();

		foreach ( $repository->by_status( LessonRepository::STATUS_MAKEUP, 500 ) as $row ) {
			$name = (string) $row['activity_name'];
			$key  = \CSCS\Support\Normalise::match_key( $name );

			$names[ $key ] = $name;

			$rows[ $key ] = array(
				'activity_name' => $name,
				'occurrences'   => ( $rows[ $key ]['occurrences'] ?? 0 ) + 1,
				'course'        => $this->label( $links[ $key ] ?? 0, $courses ),
				'suggested'     => '',
			);
		}

		foreach ( $this->suggestions( $names ) as $key => $course_id ) {
			if ( isset( $rows[ $key ] ) && '' === $rows[ $key ]['course'] ) {
				$rows[ $key ]['suggested'] = $this->label( $course_id, $courses );
			}
		}

		if ( array() === $rows ) {
			\WP_CLI::success( 'No make-up lessons are stored.' );

			return;
		}

		\WP_CLI\Utils\format_items(
			(string) ( $assoc_args['format'] ?? 'table' ),
			array_values( $rows ),
			array( 'activity_name', 'occurrences', 'course', 'suggested' )
		);
	}

	/**
	 * Renders a course as its id and its name.
	 *
	 * The id alone is unreadable, and an unreadable id is how a make-up lesson
	 * for four-year-olds ends up tied to a board games course for teenagers
	 * without anybody noticing.
	 *
	 * @param int                $course_id Course id, or 0.
	 * @param array<int, string> $courses   Course id to name.
	 * @return string
	 */
	private function label( int $course_id, array $courses ): string {
		if ( 0 === $course_id ) {
			return '';
		}

		$name = $courses[ $course_id ] ?? '';

		return '' === $name ? (string) $course_id : $course_id . ' · ' . $name;
	}

	/**
	 * Returns course names keyed by course id.
	 *
	 * @return array<int, string>
	 */
	private function course_names(): array {
		$names = array();

		foreach ( $this->courses() as $course ) {
			$names[ $course->id ] = $course->name;
		}

		return $names;
	}

	/**
	 * Reads the course list, which the last synchronisation already cached.
	 *
	 * @return array<int, \CSCS\Api\Dto\Course>
	 */
	private function courses(): array {
		try {
			return $this->plugin->client()->get_courses();
		} catch ( \CSCS\Api\ApiException $e ) {
			unset( $e );

			return array();
		}
	}

	/**
	 * Asks the resolver which course each make-up lesson names, if any.
	 *
	 * Reading the course list costs nothing extra: it is already cached from the
	 * last synchronisation. A failure here is not worth an error, because a
	 * suggestion is a convenience and the listing is the point.
	 *
	 * @param array<string, string> $names Match key to activity name.
	 * @return array<string, int>
	 */
	private function suggestions( array $names ): array {
		if ( array() === $names ) {
			return array();
		}

		return ( new MakeupResolver() )->suggest( array_values( $names ), $this->courses() );
	}

	/**
	 * Ties a make-up lesson to the course it stands in for.
	 *
	 * The link is recorded against the activity name, so it covers every
	 * occurrence of that lesson, including ones not yet retrieved.
	 *
	 * ## OPTIONS
	 *
	 * <activity>
	 * : Activity name as the timetable spells it.
	 *
	 * [<course>]
	 * : Course id. Pass none, or 0, to clear the link.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs makeup link "Náhradní lekce 4-6 let I.pololetí" 1070
	 *     wp cscs makeup link "Náhradní lekce 4-6 let I.pololetí"
	 *
	 * @param array<int, string> $args Positional arguments.
	 * @return void
	 */
	public function link( array $args ): void {
		$activity = (string) ( $args[0] ?? '' );

		if ( '' === $activity ) {
			\WP_CLI::error( 'An activity name is required.' );

			return;
		}

		$course_id = (int) ( $args[1] ?? 0 );
		$key       = $this->plugin->lessons()->link_makeup( $activity, $course_id );

		if ( '' === $key ) {
			\WP_CLI::error( 'That activity name normalises to nothing usable.' );

			return;
		}

		if ( 0 === $course_id ) {
			\WP_CLI::success( sprintf( 'Cleared the course tied to "%s".', $activity ) );

			return;
		}

		\WP_CLI::success( sprintf( '"%s" now stands in for course %d.', $activity, $course_id ) );
	}
}
