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
use CSCS\Support\Normalise;
use CSCS\Sync\MakeupResolver;

defined( 'ABSPATH' ) || exit;

/**
 * Ties each make-up lesson to the course it stands in for.
 *
 * A make-up lesson replaces a class in exactly one course, but its name never
 * says which: the timetable calls it "Náhradní lekce 4-6 let I. pololetí" and
 * twelve courses run for that age group. The name is reused rather than owned,
 * so two occurrences spelled identically can belong to two different courses.
 * That is why the link is recorded per occurrence and not per name: a name-wide
 * link would be right once and wrong eleven times.
 *
 * Nothing in the data resolves this, so a person does, one occurrence at a time.
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
	 * Lists make-up occurrences and the courses they are tied to.
	 *
	 * ## OPTIONS
	 *
	 * [--unlinked]
	 * : Show only occurrences that are not tied to a course yet.
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
	 *     wp cscs makeup list --unlinked
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function list( array $args, array $assoc_args ): void {
		unset( $args );

		$repository  = $this->plugin->lessons();
		$links       = $repository->makeup_links();
		$courses     = $this->course_names();
		$occurrences = $repository->by_status( LessonRepository::STATUS_MAKEUP, 500 );
		$suggestions = $this->suggestions( $occurrences );
		$only_open   = isset( $assoc_args['unlinked'] );
		$rows        = array();

		foreach ( $occurrences as $row ) {
			$term_id   = (int) ( $row['id_activity_term'] ?? 0 );
			$name      = (string) ( $row['activity_name'] ?? '' );
			$course_id = $links[ $term_id ] ?? 0;

			if ( $only_open && 0 !== $course_id ) {
				continue;
			}

			$rows[] = array(
				'term'          => $term_id,
				'date'          => (string) ( $row['lesson_date'] ?? '' ),
				'time'          => substr( (string) ( $row['time_from'] ?? '' ), 0, 5 ),
				'activity_name' => $name,
				'room'          => (string) ( $row['tab_name'] ?? '' ),
				'trainer'       => (string) ( $row['trainer_name'] ?? '' ),
				'course'        => $this->label( $course_id, $courses ),
				'suggested'     => 0 === $course_id
					? $this->label( $suggestions[ Normalise::match_key( $name ) ] ?? 0, $courses )
					: '',
			);
		}

		if ( array() === $rows ) {
			\WP_CLI::success( $only_open ? 'Every make-up occurrence is tied to a course.' : 'No make-up lessons are stored.' );

			return;
		}

		\WP_CLI\Utils\format_items(
			(string) ( $assoc_args['format'] ?? 'table' ),
			$rows,
			array( 'term', 'date', 'time', 'activity_name', 'room', 'trainer', 'course', 'suggested' )
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
	 * A suggestion follows from the name alone, so it is the same for every
	 * occurrence sharing a name. It stays a suggestion for that reason: the
	 * person confirms it once per occurrence.
	 *
	 * @param array<int, array<string, mixed>> $occurrences Make-up occurrences.
	 * @return array<string, int> Match key to course id.
	 */
	private function suggestions( array $occurrences ): array {
		$names = array();

		foreach ( $occurrences as $row ) {
			$name = (string) ( $row['activity_name'] ?? '' );

			if ( '' !== $name ) {
				$names[ Normalise::match_key( $name ) ] = $name;
			}
		}

		if ( array() === $names ) {
			return array();
		}

		return ( new MakeupResolver() )->suggest( array_values( $names ), $this->courses() );
	}

	/**
	 * Ties one make-up occurrence to the course it stands in for.
	 *
	 * The link is recorded against the occurrence, so it says nothing about the
	 * next lesson of the same name: that one is a separate slot and may belong
	 * to a different course. Run `wp cscs makeup list` for the occurrence ids.
	 *
	 * ## OPTIONS
	 *
	 * <term>
	 * : Occurrence id, as shown in the "term" column of `wp cscs makeup list`.
	 *
	 * [<course>]
	 * : Course id. Pass none, or 0, to clear the link.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs makeup link 55368 1072
	 *     wp cscs makeup link 55368
	 *
	 * @param array<int, string> $args Positional arguments.
	 * @return void
	 */
	public function link( array $args ): void {
		$term_id = (int) ( $args[0] ?? 0 );

		if ( 0 === $term_id ) {
			\WP_CLI::error( 'An occurrence id is required. Run `wp cscs makeup list` to see them.' );

			return;
		}

		$course_id = (int) ( $args[1] ?? 0 );

		if ( ! $this->plugin->lessons()->link_makeup( $term_id, $course_id ) ) {
			\WP_CLI::error( 'That occurrence id is not usable.' );

			return;
		}

		if ( 0 === $course_id ) {
			\WP_CLI::success( sprintf( 'Cleared the course tied to occurrence %d.', $term_id ) );

			return;
		}

		\WP_CLI::success( sprintf( 'Occurrence %d now stands in for course %d.', $term_id, $course_id ) );
	}
}
