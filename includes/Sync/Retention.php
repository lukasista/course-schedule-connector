<?php
/**
 * Data retention.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Sync;

defined( 'ABSPATH' ) || exit;

use CSCS\Data\CourseRepository;
use CSCS\Data\LessonRepository;
use CSCS\Data\PostType;
use CSCS\Settings;

/**
 * Keeps stored data to the size it is actually useful at.
 *
 * Occurrences are a viewing aid with a short life: nobody browses last spring's
 * timetable, so they are kept for a configurable window behind today and then
 * dropped. Courses are the opposite — they have pages, text and inbound links,
 * so they are never deleted, only moved from running to finished.
 */
final class Retention {

	/**
	 * Occurrence storage.
	 *
	 * @var LessonRepository
	 */
	private LessonRepository $lessons;

	/**
	 * Log.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param LessonRepository $lessons  Occurrence storage.
	 * @param Logger           $logger   Log.
	 * @param Settings         $settings Settings.
	 */
	public function __construct( LessonRepository $lessons, Logger $logger, Settings $settings ) {
		$this->lessons  = $lessons;
		$this->logger   = $logger;
		$this->settings = $settings;
	}

	/**
	 * Runs every retention rule.
	 *
	 * @return array{lessons_removed: int, log_removed: int, courses_finished: int}
	 */
	public function run(): array {
		$started = time();

		$days    = $this->settings->get_int( 'lesson_retention_days', 0, 3650 );
		$cut_off = $started - ( $days * DAY_IN_SECONDS );

		$removed  = $this->lessons->purge_before( $cut_off );
		$log_gone = $this->logger->purge_before( $started - ( 90 * DAY_IN_SECONDS ) );
		$finished = $this->close_finished_courses();

		$this->logger->record(
			'retention',
			$started,
			$removed,
			'success',
			sprintf( '%d occurrences removed, %d log rows removed, %d courses closed', $removed, $log_gone, $finished )
		);

		return array(
			'lessons_removed'  => $removed,
			'log_removed'      => $log_gone,
			'courses_finished' => $finished,
		);
	}

	/**
	 * Moves courses whose end date has passed into the finished state.
	 *
	 * @return int Number of courses closed.
	 */
	private function close_finished_courses(): int {
		$posts = get_posts(
			array(
				'post_type'        => PostType::COURSE,
				'post_status'      => CourseRepository::every_status(),
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => false,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Runs once a day on a post type holding tens of rows.
					array(
						'key'     => CourseRepository::META_STATUS,
						'value'   => CourseRepository::STATUS_RUNNING,
						'compare' => '=',
					),
				),
			)
		);

		$closed = 0;
		$now    = time();

		foreach ( $posts as $post_id ) {
			$ends = (int) get_post_meta( (int) $post_id, '_cscs_stamp_to', true );

			if ( 0 === $ends ) {
				$date = (string) get_post_meta( (int) $post_id, '_cscs_date_to', true );
				$ends = '' === $date ? 0 : (int) strtotime( $date . ' 23:59:59' );
			}

			if ( 0 !== $ends && $ends < $now ) {
				update_post_meta( (int) $post_id, CourseRepository::META_STATUS, CourseRepository::STATUS_FINISHED );
				++$closed;
			}
		}

		return $closed;
	}
}
