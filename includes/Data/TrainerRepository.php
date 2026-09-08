<?php
/**
 * Reading and writing trainers.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Data;

defined( 'ABSPATH' ) || exit;

use CSCS\Support\Normalise;

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
	 * Trainers whose photograph has already been considered in this run.
	 *
	 * @var array<int, bool>
	 */
	private static array $touched = array();

	/**
	 * Brings the photograph iSport holds into the media library, once.
	 *
	 * Fetched on the server at synchronisation time and stored here, so that a
	 * visitor's browser never asks iSport for anything. Hot-linking the remote
	 * address would have told the remote system who is reading the site, which
	 * is precisely the thing this plugin promises not to do.
	 *
	 * "Once" was the intention and not what happened. The guard compared the
	 * incoming address with the last one fetched — and iSport keeps more than
	 * one trainer record under the same name, eleven of this gym's twenty-two
	 * names having two or three, each with its own photograph. A course names
	 * whichever record it was booked against, so walking a hundred courses made
	 * the address flip back and forth, and every flip was a download and a new
	 * attachment. The media library reached 2 116 pictures of twenty-two
	 * people: 309 of one of them.
	 *
	 * So the question is no longer "is this the address I fetched last time"
	 * but "is this an address I have ever fetched", which the flipping cannot
	 * defeat. A photograph genuinely new to iSport still arrives, because its
	 * address is one nobody has seen. Two further guards sit behind that: an
	 * attachment already made from this exact address is reused rather than
	 * made again, and no trainer is fetched more than once in a single run
	 * whatever the courses say.
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
		$has      = 0 !== $existing && null !== get_post( $existing );

		if ( $has && in_array( $url, $this->fetched_from( $post_id ), true ) ) {
			return;
		}

		// One trainer, one fetch per run. Belt to the braces above, and the
		// thing that would have kept the damage to twenty-two pictures rather
		// than two thousand had it been here from the start.
		if ( isset( self::$touched[ $post_id ] ) ) {
			return;
		}

		self::$touched[ $post_id ] = true;

		// The picture may already be in the library under another trainer's
		// name, or from before this meta existed. Pointing at it is free.
		$already = $this->attachment_for( $url );

		if ( 0 !== $already ) {
			$this->remember( $post_id, $already, $url );

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

		update_post_meta( (int) $attachment, TrainerType::META_ATTACHMENT_SOURCE, $url );

		$this->remember( $post_id, (int) $attachment, $url );
	}

	/**
	 * Records which picture a trainer now shows, and that this address is done with.
	 *
	 * @param int    $post_id       Trainer post id.
	 * @param int    $attachment_id Attachment id.
	 * @param string $url           Address it was made from.
	 * @return void
	 */
	private function remember( int $post_id, int $attachment_id, string $url ): void {
		// The list is written before the last-fetched address, not after. The
		// other way round it never grew: the address had just been written as
		// the last one, `fetched_from()` counts that as seen, and so the row
		// was never added — leaving the picture to change hands between the
		// same two attachments on every synchronisation, for ever.
		$seen = array_map( 'strval', (array) get_post_meta( $post_id, TrainerType::META_PHOTO_SEEN ) );

		if ( ! in_array( $url, $seen, true ) ) {
			add_post_meta( $post_id, TrainerType::META_PHOTO_SEEN, $url );
		}

		update_post_meta( $post_id, TrainerType::META_PHOTO_ID, $attachment_id );
		update_post_meta( $post_id, TrainerType::META_PHOTO_SOURCE, $url );
	}

	/**
	 * Every address a trainer's photograph has been fetched from.
	 *
	 * The address last fetched counts as seen even where the list does not
	 * mention it, so that a site upgrading to this does not fetch everything
	 * one more time to find that out.
	 *
	 * @param int $post_id Trainer post id.
	 * @return array<int, string>
	 */
	private function fetched_from( int $post_id ): array {
		$seen = array_map( 'strval', (array) get_post_meta( $post_id, TrainerType::META_PHOTO_SEEN ) );
		$last = (string) get_post_meta( $post_id, TrainerType::META_PHOTO_SOURCE, true );

		if ( '' !== $last && ! in_array( $last, $seen, true ) ) {
			$seen[] = $last;
		}

		return $seen;
	}

	/**
	 * Returns an attachment already made from an address, if there is one.
	 *
	 * @param string $url Address.
	 * @return int Attachment id, or 0.
	 */
	private function attachment_for( string $url ): int {
		$found = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'meta_key'         => TrainerType::META_ATTACHMENT_SOURCE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- The address is the only thing that identifies a fetched picture.
				'meta_value'       => $url, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- See above.
			)
		);

		return array() === $found ? 0 : (int) $found[0];
	}
}
