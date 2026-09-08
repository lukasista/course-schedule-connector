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
	 * Removes the duplicate photographs an earlier version left behind.
	 *
	 * Until this was fixed, a synchronisation fetched a trainer's photograph
	 * again every time a course named a different one of iSport's several
	 * records for that person — which, walking a hundred courses, was often.
	 * One gym's media library held 2 116 pictures of twenty-two people.
	 *
	 * What is kept: the picture the trainer's page actually shows, and any
	 * picture used as a featured image anywhere. Everything else attached to a
	 * trainer's page goes, file and all. Run it with --dry-run first; deleted
	 * attachments do not come back.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Say what would happen and delete nothing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs trainers tidy --dry-run
	 *     wp cscs trainers tidy
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function tidy( array $args, array $assoc_args ): void {
		unset( $args );

		$dry     = isset( $assoc_args['dry-run'] );
		$deleted = 0;
		$kept    = 0;
		$rows    = array();

		foreach ( $this->trainer_ids() as $trainer_id ) {
			$keep = $this->plugin->trainers()->photograph( $trainer_id );

			if ( 0 === $keep ) {
				$keep = (int) get_post_meta( $trainer_id, TrainerType::META_PHOTO_ID, true );
			}

			$attachments = get_posts(
				array(
					'post_type'        => 'attachment',
					'post_status'      => 'inherit',
					'post_parent'      => $trainer_id,
					'posts_per_page'   => -1,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => false,
				)
			);

			if ( 0 === $keep && array() !== $attachments ) {
				// Nothing says which one is in use, so the newest stands.
				$keep = (int) max( array_map( 'intval', $attachments ) );
			}

			$gone = 0;

			foreach ( $attachments as $attachment_id ) {
				$attachment_id = (int) $attachment_id;

				if ( $attachment_id === $keep || $this->is_a_featured_image( $attachment_id ) ) {
					++$kept;

					continue;
				}

				++$gone;
				++$deleted;

				if ( ! $dry ) {
					// The address this copy was made from is remembered as
					// fetched, or the next synchronisation reads its absence as
					// "never had it" and fetches it straight back.
					$this->remember_removed( $trainer_id, $attachment_id );

					wp_delete_attachment( $attachment_id, true );
				}
			}

			if ( 0 !== $keep ) {
				++$kept;

				if ( ! $dry ) {
					update_post_meta( $trainer_id, TrainerType::META_PHOTO_ID, $keep );
				}
			}

			if ( 0 !== $gone ) {
				$rows[] = array(
					'trainer' => (string) get_the_title( $trainer_id ),
					'kept'    => $keep,
					'removed' => $gone,
				);
			}
		}

		if ( array() !== $rows ) {
			\WP_CLI\Utils\format_items( 'table', $rows, array( 'trainer', 'kept', 'removed' ) );
		}

		\WP_CLI::success(
			sprintf(
				'%d attachment(s) %s, %d kept.',
				$deleted,
				$dry ? 'would be removed' : 'removed',
				$kept
			)
		);
	}

	/**
	 * Notes the address of a picture being removed, so it is not fetched again.
	 *
	 * @param int $trainer_id    Trainer post id.
	 * @param int $attachment_id Attachment about to go.
	 * @return void
	 */
	private function remember_removed( int $trainer_id, int $attachment_id ): void {
		$url = (string) get_post_meta( $attachment_id, TrainerType::META_ATTACHMENT_SOURCE, true );

		if ( '' === $url ) {
			return;
		}

		$seen = array_map( 'strval', (array) get_post_meta( $trainer_id, TrainerType::META_PHOTO_SEEN ) );

		if ( ! in_array( $url, $seen, true ) ) {
			add_post_meta( $trainer_id, TrainerType::META_PHOTO_SEEN, $url );
		}
	}

	/**
	 * Says whether an attachment is somebody's featured image.
	 *
	 * @param int $attachment_id Attachment id.
	 * @return bool
	 */
	private function is_a_featured_image( int $attachment_id ): bool {
		$found = get_posts(
			array(
				'post_type'        => 'any',
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'meta_key'         => '_thumbnail_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Asked once per attachment by a command that is run by hand.
				'meta_value'       => (string) $attachment_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- See above.
			)
		);

		return array() !== $found;
	}

	/**
	 * Every trainer page, by id.
	 *
	 * @return array<int, int>
	 */
	private function trainer_ids(): array {
		$found = get_posts(
			array(
				'post_type'        => TrainerType::TRAINER,
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);

		return array_map( 'intval', $found );
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
