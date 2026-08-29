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

		// A job lost is a job nobody notices: the pages keep rendering, the
		// numbers on them simply stop moving. Checking on every load costs four
		// lookups in an option WordPress has already loaded.
		add_action( 'init', array( $this, 'schedule' ), 20 );
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
	 * Returns which recurrence each job runs on.
	 *
	 * @return array<string, string>
	 */
	public function jobs(): array {
		return array(
			self::HOOK_COURSES   => 'cscs_courses',
			self::HOOK_NEAR      => 'cscs_lessons',
			self::HOOK_FAR       => 'daily',
			self::HOOK_RETENTION => 'daily',
		);
	}

	/**
	 * Schedules every job that is not scheduled yet.
	 *
	 * Checked on every load rather than only at activation, and this is the fix
	 * for a real and quiet failure. Two of the four jobs run on intervals this
	 * plugin declares itself, and `wp_schedule_event()` refuses a recurrence
	 * WordPress does not know at the moment it is called — which on the
	 * activation request it does not, because a plugin being activated is not
	 * yet in the list the `cron_schedules` filter is added from. So the two
	 * daily jobs were scheduled and the two that matter most were not: courses
	 * stopped being fetched, nobody was told, and the pages went on showing the
	 * places free at whatever hour the last synchronisation happened to run. On
	 * this gym's site 44 of 113 courses were out by the time it was noticed.
	 *
	 * The intervals are therefore registered here too, before anything is
	 * scheduled, and a job whose interval is somehow still unknown falls back
	 * to hourly rather than to nothing at all.
	 *
	 * @return void
	 */
	public function schedule(): void {
		if ( ! has_filter( 'cron_schedules', array( $this, 'add_intervals' ) ) ) {
			add_filter( 'cron_schedules', array( $this, 'add_intervals' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- Intervals are administrator-configurable and capped by the hourly request ceiling.
		}

		$known = wp_get_schedules();

		foreach ( $this->jobs() as $hook => $recurrence ) {
			if ( false !== wp_next_scheduled( $hook ) ) {
				continue;
			}

			wp_schedule_event(
				time() + MINUTE_IN_SECONDS,
				isset( $known[ $recurrence ] ) ? $recurrence : 'hourly',
				$hook
			);
		}
	}

	/**
	 * Puts a job back on its interval after that interval has been changed.
	 *
	 * WordPress stores the recurrence with the event, not with the schedule, so
	 * an administrator who shortens the course interval changes a number
	 * nothing reads until the job is scheduled again. Which used to mean
	 * switching the plugin off and on.
	 *
	 * @param string $hook Job.
	 * @return void
	 */
	public function reschedule( string $hook ): void {
		if ( ! isset( $this->jobs()[ $hook ] ) ) {
			return;
		}

		wp_clear_scheduled_hook( $hook );
		$this->schedule();
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
