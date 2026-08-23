<?php
/**
 * Reading and writing trainers.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Data;

use CSCS\Support\Normalise;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps a trainer's page in step with the courses that name them.
 *
 * The remote system has no trainer record to fetch: a trainer is a name printed
 * on a course, and occasionally a photograph beside it. So a trainer post is
 * made the first time a course names one, and from then on the synchronisation
 * only ever touches the two things it owns — the name and the photograph — and
 * never the words somebody wrote underneath.
 *
 * Pairing is by the same normalised name the whole plugin matches on, because
 * that is the only identifier both sides have. It removes every space and
 * lowercases what is left, so "Jana  Nováková" and "Jana Nováková" are one
 * person rather than two pages.
 */
final class TrainerRepository {

	/**
	 * Turns a name into the key a trainer is found by.
	 *
	 * @param string $name Trainer name.
	 * @return string Key, or an empty string when there is no name.
	 */
	public static function key( string $name ): string {
		$name = trim( $name );

		return '' === $name ? '' : Normalise::match_key( $name );
	}

	/**
	 * Finds the trainer post for a name.
	 *
	 * @param string $name Trainer name.
	 * @return int Post id, or 0.
	 */
	public function find( string $name ): int {
		$key = self::key( $name );

		if ( '' === $key ) {
			return 0;
		}

		$found = get_posts(
			array(
				'post_type'        => TrainerType::TRAINER,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'meta_key'         => TrainerType::META_KEY_NAME, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- The key is the trainer's identity; there is nothing else to find them by.
				'meta_value'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- See above.
			)
		);

		return array() === $found ? 0 : (int) $found[0];
	}

	/**
	 * Makes sure a trainer with this name has a page, and returns it.
	 *
	 * Only the name and the photograph are written. A page that already exists
	 * keeps its title: somebody may have written "Mgr. Jana Nováková" where
	 * iSport says "Jana Novakova", and a synchronisation that corrected that
	 * every night would be a bug wearing the clothes of a feature.
	 *
	 * @param string      $name      Trainer name, as iSport spells it.
	 * @param string|null $image     Photograph address at iSport, where there is one.
	 * @param int         $remote_id Trainer id in iSport, where a class named one.
	 * @return int Post id, or 0 when there was no name or the write failed.
	 */
	public function ensure( string $name, ?string $image = null, int $remote_id = 0 ): int {
		$name = trim( $name );
		$key  = self::key( $name );

		if ( '' === $key ) {
			return 0;
		}

		$post_id = $this->find( $name );

		if ( 0 === $post_id ) {
			$post_id = (int) wp_insert_post(
				array(
					'post_type'    => TrainerType::TRAINER,
					'post_status'  => 'publish',
					'post_title'   => $name,
					'post_content' => '',
				),
				true
			);

			if ( 0 === $post_id || $post_id instanceof \WP_Error ) {
				return 0;
			}

			update_post_meta( $post_id, TrainerType::META_KEY_NAME, $key );
		}

		if ( 0 !== $remote_id ) {
			update_post_meta( $post_id, TrainerType::META_REMOTE_ID, $remote_id );
		}

		if ( is_string( $image ) && '' !== $image ) {
			$this->fetch_photograph( $post_id, $image );
		}

		/**
		 * Fires after a trainer's page has been created or refreshed.
		 *
		 * @since 0.5.0
		 *
		 * @param int    $post_id Trainer post id.
		 * @param string $name    Trainer name as iSport spells it.
		 */
		do_action( 'cscs_trainer_saved', $post_id, $name );

		return $post_id;
	}

	/**
	 * Returns the trainer page a course belongs to, if there is one.
	 *
	 * @param int $course_id Course post id.
	 * @return int Trainer post id, or 0.
	 */
	public function for_course( int $course_id ): int {
		$key = (string) get_post_meta( $course_id, TrainerType::META_KEY_NAME, true );

		if ( '' === $key ) {
			$key = self::key( (string) get_post_meta( $course_id, '_cscs_trainer_name', true ) );
		}

		if ( '' === $key ) {
			return 0;
		}

		$found = get_posts(
			array(
				'post_type'        => TrainerType::TRAINER,
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'meta_key'         => TrainerType::META_KEY_NAME, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- The key is the trainer's identity.
				'meta_value'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- See above.
			)
		);

		return array() === $found ? 0 : (int) $found[0];
	}

	/**
	 * Returns the courses a trainer runs, newest term first.
	 *
	 * Archived courses are left out for the same reason they are left out of a
	 * listing: a page that still advertises last year is worse than a short one.
	 *
	 * @param int  $trainer_id Trainer post id.
	 * @param bool $include_finished Whether courses that have ended count.
	 * @return array<int, \WP_Post>
	 */
	public function courses( int $trainer_id, bool $include_finished = true ): array {
		$key = (string) get_post_meta( $trainer_id, TrainerType::META_KEY_NAME, true );

		if ( '' === $key ) {
			return array();
		}

		$statuses = $include_finished
			? array( CourseRepository::STATUS_ARCHIVED )
			: array( CourseRepository::STATUS_ARCHIVED, CourseRepository::STATUS_FINISHED );

		$meta = array(
			'relation' => 'AND',
			array(
				'key'   => TrainerType::META_KEY_NAME,
				'value' => $key,
			),
			array(
				'key'     => CourseRepository::META_STATUS,
				'value'   => $statuses,
				'compare' => 'NOT IN',
			),
		);

		$posts = get_posts(
			array(
				'post_type'        => PostType::COURSE,
				'post_status'      => 'publish',
				'posts_per_page'   => 100,
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'orderby'          => 'meta_value_num',
				'meta_key'         => '_cscs_stamp_from', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Ordering a trainer's courses by when they start is the point of the list.
				'order'            => 'ASC',
				'meta_query'       => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- A trainer has a handful of courses among several hundred; the alternative is reading all of them.
			)
		);

		return is_array( $posts ) ? $posts : array();
	}

	/**
	 * Returns the photograph to show for a trainer.
	 *
	 * A picture uploaded here wins over the one iSport holds, always. That is
	 * the whole point of being able to replace it: the remote photograph is a
	 * starting point, not a decision.
	 *
	 * @param int $trainer_id Trainer post id.
	 * @return int Attachment id, or 0.
	 */
	public function photograph( int $trainer_id ): int {
		$own = (int) get_post_thumbnail_id( $trainer_id );

		if ( 0 !== $own ) {
			return $own;
		}

		$synced = (int) get_post_meta( $trainer_id, TrainerType::META_PHOTO_ID, true );

		return 0 !== $synced && null !== get_post( $synced ) ? $synced : 0;
	}

	/**
	 * Brings the photograph iSport holds into the media library, once.
	 *
	 * Fetched on the server at synchronisation time and stored here, so that a
	 * visitor's browser never asks iSport for anything. Hot-linking the remote
	 * address would have told the remote system who is reading the site, which
	 * is precisely the thing this plugin promises not to do.
	 *
	 * @param int    $post_id Trainer post id.
	 * @param string $url     Photograph address.
	 * @return void
	 */
	private function fetch_photograph( int $post_id, string $url ): void {
		$url = (string) Normalise::to_url( $url );

		if ( '' === $url ) {
			return;
		}

		$existing = (int) get_post_meta( $post_id, TrainerType::META_PHOTO_ID, true );
		$source   = (string) get_post_meta( $post_id, TrainerType::META_PHOTO_SOURCE, true );

		// The same address, already fetched, and the attachment still there:
		// nothing to do. A photograph does not change often enough to justify a
		// download on every synchronisation.
		if ( $source === $url && 0 !== $existing && null !== get_post( $existing ) ) {
			return;
		}

		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$temporary = download_url( $url, 20 );

		if ( $temporary instanceof \WP_Error ) {
			return;
		}

		$name = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$name = '' === $name ? 'trainer.jpg' : $name;

		$attachment = media_handle_sideload(
			array(
				'name'     => sanitize_file_name( $name ),
				'tmp_name' => $temporary,
			),
			$post_id,
			null,
			array( 'post_title' => get_the_title( $post_id ) )
		);

		if ( $attachment instanceof \WP_Error ) {
			// download_url made the file; if the sideload refused it, nobody
			// else is going to remove it.
			wp_delete_file( $temporary );

			return;
		}

		update_post_meta( $post_id, TrainerType::META_PHOTO_ID, (int) $attachment );
		update_post_meta( $post_id, TrainerType::META_PHOTO_SOURCE, $url );
	}
}
