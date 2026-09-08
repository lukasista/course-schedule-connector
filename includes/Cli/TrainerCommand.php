<?php
/**
 * WP-CLI commands for trainers.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Cli;

defined( 'ABSPATH' ) || exit;

use CSCS\Data\CourseRepository;
use CSCS\Data\PostType;
use CSCS\Data\TrainerRepository;
use CSCS\Data\TrainerType;
use CSCS\Plugin;

/**
 * Builds trainer pages out of courses that are already stored.
 *
 * Pairing happens when a course is written, which means a site that synced
 * before trainers existed has several hundred courses naming people who have
 * no page. Waiting for the next synchronisation would work and would also mean
 * nobody can see the feature until tomorrow morning; this does it now.
 */
final class TrainerCommand {

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
		\WP_CLI::add_command( 'cscs trainers', new self( $plugin ) );
	}

	/**
	 * Makes a page for every trainer the stored courses name.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Say what would happen and change nothing.
	 *
	 * [--photographs]
	 * : Also fetch the photographs iSport holds. Off by default: it is one
	 * download per trainer, and a first run should be fast and reversible.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs trainers backfill --dry-run
	 *     wp cscs trainers backfill --photographs
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function backfill( array $args, array $assoc_args ): void {
		unset( $args );

		$dry    = isset( $assoc_args['dry-run'] );
		$photos = isset( $assoc_args['photographs'] );

		$courses = get_posts(
			array(
				'post_type'        => PostType::COURSE,
				'post_status'      => CourseRepository::every_status(),
				'numberposts'      => -1,
				'suppress_filters' => false,
			)
		);

		$trainers = new TrainerRepository();
		$made     = 0;
		$paired   = 0;
		$seen     = array();

		foreach ( $courses as $course ) {
			$name = (string) get_post_meta( $course->ID, '_cscs_trainer_name', true );
			$key  = TrainerRepository::key( $name );

			if ( '' === $key ) {
				continue;
			}

			if ( ! $dry ) {
				update_post_meta( $course->ID, TrainerType::META_KEY_NAME, $key );
			}

			++$paired;

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;

			if ( 0 !== $trainers->find( $name ) ) {
				continue;
			}

			++$made;

			if ( $dry ) {
				\WP_CLI::log( sprintf( 'Would make a page for %s.', $name ) );

				continue;
			}

			$image = $photos ? (string) get_post_meta( $course->ID, '_cscs_trainer_image', true ) : null;

			$trainers->ensure( $name, '' === $image ? null : $image );
		}

		\WP_CLI::success(
			sprintf(
				'%d course(s) paired, %d trainer page(s) %s.',
				$paired,
				$made,
				$dry ? 'would be made' : 'made'
			)
		);
	}
}
