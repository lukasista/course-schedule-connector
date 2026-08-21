<?php
/**
 * Scheduled synchronisation.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Sync;

use CSCS\Api\ApiException;
use CSCS\Plugin;
use CSCS\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and runs the scheduled jobs.
 *
 * Three jobs, all querying forward in time. The near window carries what
 * visitors are actually looking at and runs often; the far window reaches to
 * the end of the term and runs once a night; the course list runs in between.
 * One request answers each of them in full, capacity included, so freshness is
 * a question of how often these fire rather than how many endpoints exist.
 */
final class Scheduler {

	/**
	 * Hook for the course list job.
	 */
	public const HOOK_COURSES = 'cscs_sync_courses';

	/**
	 * Hook for the near window of occurrences.
	 */
	public const HOOK_NEAR = 'cscs_sync_lessons_near';

	/**
	 * Hook for the far window of occurrences.
	 */
	public const HOOK_FAR = 'cscs_sync_lessons_far';

	/**
	 * Hook for retention.
	 */
	public const HOOK_RETENTION = 'cscs_retention';

	/**
	 * Plugin instance, used to build services lazily.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Plugin   $plugin   Plugin instance.
	 * @param Settings $settings Settings.
	 */
	public function __construct( Plugin $plugin, Settings $settings ) {
		$this->plugin   = $plugin;
		$this->settings = $settings;
	}

	/**
	 * Hooks the jobs and the custom intervals.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'cron_schedules', array( $this, 'add_intervals' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- Intervals are administrator-configurable and capped by the hourly request ceiling.

		add_action( self::HOOK_COURSES, array( $this, 'run_courses' ) );
		add_action( self::HOOK_NEAR, array( $this, 'run_near' ) );
		add_action( self::HOOK_FAR, array( $this, 'run_far' ) );
		add_action( self::HOOK_RETENTION, array( $this, 'run_retention' ) );
	}

	/**
	 * Adds the plugin's own cron intervals.
	 *
	 * @param mixed $schedules Existing schedules, as passed by the filter.
	 * @return array<string, array{interval: int, display: string}>
	 */
	public function add_intervals( $schedules ): array {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}

		$schedules['cscs_courses'] = array(
			'interval' => $this->settings->get_int( 'interval_courses', 300, DAY_IN_SECONDS ),
			'display'  => __( 'Course synchronisation interval', 'course-schedule-connector' ),
		);

		$schedules['cscs_lessons'] = array(
			'interval' => $this->settings->get_int( 'interval_lessons_near', 300, DAY_IN_SECONDS ),
			'display'  => __( 'Class synchronisation interval', 'course-schedule-connector' ),
		);

		return $schedules;
	}

	/**
	 * Schedules every job that is not scheduled yet.
	 *
	 * @return void
	 */
	public function schedule(): void {
		$jobs = array(
			self::HOOK_COURSES   => 'cscs_courses',
			self::HOOK_NEAR      => 'cscs_lessons',
			self::HOOK_FAR       => 'daily',
			self::HOOK_RETENTION => 'daily',
		);

		foreach ( $jobs as $hook => $recurrence ) {
			if ( false === wp_next_scheduled( $hook ) ) {
				wp_schedule_event( time() + 60, $recurrence, $hook );
			}
		}
	}

	/**
	 * Clears every scheduled job.
	 *
	 * @return void
	 */
	public function unschedule(): void {
		foreach ( array( self::HOOK_COURSES, self::HOOK_NEAR, self::HOOK_FAR, self::HOOK_RETENTION ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Runs the course job.
	 *
	 * @return void
	 */
	public function run_courses(): void {
		if ( ! $this->ready() ) {
			return;
		}

		try {
			$this->plugin->synchroniser()->sync_courses();
		} catch ( ApiException $e ) {
			unset( $e );
		}
	}

	/**
	 * Runs the near window job.
	 *
	 * @return void
	 */
	public function run_near(): void {
		if ( ! $this->ready() ) {
			return;
		}

		$days = $this->settings->get_int( 'near_window_days', 1, 365 );

		try {
			$this->plugin->synchroniser()->sync_lessons(
				'lessons_near',
				$this->offset_date( 0 ),
				$this->offset_date( $days )
			);
		} catch ( ApiException $e ) {
			unset( $e );
		}
	}

	/**
	 * Runs the far window job, reaching to the end of the configured term.
	 *
	 * @return void
	 */
	public function run_far(): void {
		if ( ! $this->ready() ) {
			return;
		}

		$days = $this->settings->get_int( 'near_window_days', 1, 365 );
		$to   = $this->semester_end();

		if ( null === $to ) {
			return;
		}

		try {
			$this->plugin->synchroniser()->sync_lessons( 'lessons_far', $this->offset_date( $days + 1 ), $to );
		} catch ( ApiException $e ) {
			unset( $e );
		}
	}

	/**
	 * Runs retention.
	 *
	 * @return void
	 */
	public function run_retention(): void {
		$this->plugin->retention()->run();
	}

	/**
	 * Whether there is any point contacting the remote system.
	 *
	 * @return bool
	 */
	private function ready(): bool {
		return $this->settings->is_configured();
	}

	/**
	 * Returns a date this many days from today, as the API expects it.
	 *
	 * @param int $days Offset in days.
	 * @return string
	 */
	private function offset_date( int $days ): string {
		return (string) wp_date( 'Ymd', time() + ( $days * DAY_IN_SECONDS ) );
	}

	/**
	 * Returns the configured end of the current term, or null when unset.
	 *
	 * @return string|null Ymd date.
	 */
	private function semester_end(): ?string {
		$to = (string) $this->settings->get( 'semester_to' );

		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) {
			return null;
		}

		return str_replace( '-', '', $to );
	}
}
