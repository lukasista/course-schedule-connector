<?php
/**
 * Overview screen.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Admin\Screen;

use CSCS\Admin\Capabilities;
use CSCS\Api\ApiException;
use CSCS\Data\LessonRepository;
use CSCS\Plugin;
use CSCS\Sync\Scheduler;

defined( 'ABSPATH' ) || exit;

/**
 * Shows whether the integration is working, in numbers.
 *
 * The request counter is on this page deliberately. The remote system belongs
 * to somebody else, and if they ever ask how much traffic this site sends them,
 * the answer should be a figure anyone can read off a screen rather than an
 * estimate.
 */
final class Overview {

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
	 * Renders the screen.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! Capabilities::can_manage_content() ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'course-schedule-connector' ) );
		}

		$notice = $this->handle_actions();
		$stats  = $this->plugin->lessons()->stats();
		$client = $this->plugin->client();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'iSport overview', 'course-schedule-connector' ); ?></h1>

			<?php if ( '' !== $notice ) : ?>
				<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! $this->plugin->settings()->is_configured() ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php esc_html_e( 'No usable address of an iSport System installation is configured, so nothing is being retrieved.', 'course-schedule-connector' ); ?>
						<?php if ( Capabilities::can_manage_design() ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=cscs-settings' ) ); ?>"><?php esc_html_e( 'Open settings', 'course-schedule-connector' ); ?></a>
						<?php else : ?>
							<?php esc_html_e( 'Ask an administrator to set it.', 'course-schedule-connector' ); ?>
						<?php endif; ?>
					</p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'What is stored', 'course-schedule-connector' ); ?></h2>
			<table class="widefat striped" style="max-width:40em">
				<tbody>
					<?php
					foreach ( $this->rows( $stats ) as $label => $value ) :
						?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td><?php echo esc_html( (string) $value ); ?></td>
						</tr>
						<?php
					endforeach;
					?>
				</tbody>
			</table>

			<?php if ( (int) ( $stats['unresolved'] ?? 0 ) > 0 ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<?php
						printf(
							/* translators: %d: number of class occurrences */
							esc_html__( '%d occurrences should belong to a course and do not. Everything else is either matched or a recognised rental.', 'course-schedule-connector' ),
							(int) $stats['unresolved']
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Connection', 'course-schedule-connector' ); ?></h2>
			<table class="widefat striped" style="max-width:40em">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Requests this hour', 'course-schedule-connector' ); ?></th>
						<td><?php echo esc_html( $client->requests_this_hour() . ' / ' . $this->plugin->settings()->get_int( 'request_cap_per_hour', 1, 3600 ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Consecutive failures', 'course-schedule-connector' ); ?></th>
						<td><?php echo esc_html( (string) $client->breaker()->failures() ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Requests paused', 'course-schedule-connector' ); ?></th>
						<td>
							<?php
							echo $client->breaker()->is_closed()
								? esc_html__( 'No', 'course-schedule-connector' )
								: esc_html(
									sprintf(
										/* translators: %s: time of day */
										__( 'Yes, until %s', 'course-schedule-connector' ),
										wp_date( 'H:i', $client->breaker()->open_until() )
									)
								);
							?>
						</td>
					</tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Scheduled jobs', 'course-schedule-connector' ); ?></h2>
			<p class="description" style="max-width:45em">
				<?php esc_html_e( 'A job that has stopped shows nothing on the pages themselves: they keep rendering, and the places free simply stay at whatever they were the last time anything ran.', 'course-schedule-connector' ); ?>
			</p>
			<table class="widefat striped" style="max-width:40em">
				<tbody>
					<?php foreach ( $this->plugin->scheduler()->jobs() as $cscs_hook => $cscs_recurrence ) : ?>
						<?php $cscs_next = wp_next_scheduled( $cscs_hook ); ?>
						<tr>
							<th scope="row"><?php echo esc_html( self::job_name( (string) $cscs_hook ) ); ?></th>
							<td>
								<?php
								if ( false === $cscs_next ) {
									echo '<strong>' . esc_html__( 'Not running', 'course-schedule-connector' ) . '</strong>';
								} else {
									echo esc_html(
										sprintf(
											/* translators: %s: a date and time */
											__( 'Next at %s', 'course-schedule-connector' ),
											wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), (int) $cscs_next )
										)
									);
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( Capabilities::can_manage_design() ) : ?>
				<h2><?php esc_html_e( 'Actions', 'course-schedule-connector' ); ?></h2>
				<form method="post">
					<?php wp_nonce_field( 'cscs_overview' ); ?>
					<p>
						<button type="submit" name="cscs_action" value="sync" class="button button-primary">
							<?php esc_html_e( 'Synchronise now', 'course-schedule-connector' ); ?>
						</button>
						<button type="submit" name="cscs_action" value="reset" class="button">
							<?php esc_html_e( 'Resume paused requests', 'course-schedule-connector' ); ?>
						</button>
					</p>
				</form>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Recent runs', 'course-schedule-connector' ); ?></h2>
			<?php $this->render_log(); ?>
		</div>
		<?php
	}

	/**
	 * Returns what a job is called on this screen.
	 *
	 * @param string $hook Job.
	 * @return string
	 */
	private static function job_name( string $hook ): string {
		$names = array(
			Scheduler::HOOK_COURSES   => __( 'Courses', 'course-schedule-connector' ),
			Scheduler::HOOK_NEAR      => __( 'Classes in the near term', 'course-schedule-connector' ),
			Scheduler::HOOK_FAR       => __( 'Classes further out', 'course-schedule-connector' ),
			Scheduler::HOOK_RETENTION => __( 'Clearing out what is past', 'course-schedule-connector' ),
		);

		return $names[ $hook ] ?? $hook;
	}

	/**
	 * Runs whichever action was submitted.
	 *
	 * @return string Message to show, empty when nothing ran.
	 */
	private function handle_actions(): string {
		if ( ! isset( $_POST['cscs_action'] ) ) {
			return '';
		}

		// Reaching the network and clearing failure state are an administrator's
		// business, and the capability is checked here rather than assumed from
		// the fact that the button was rendered.
		if ( ! Capabilities::can_manage_design() ) {
			return __( 'You are not allowed to run that.', 'course-schedule-connector' );
		}

		check_admin_referer( 'cscs_overview' );

		$action = sanitize_key( wp_unslash( $_POST['cscs_action'] ) );

		if ( 'reset' === $action ) {
			$this->plugin->reset_connection_state();

			return __( 'Requests resumed and cached responses dropped.', 'course-schedule-connector' );
		}

		if ( 'sync' !== $action ) {
			return '';
		}

		try {
			$courses = $this->plugin->synchroniser()->sync_courses( true );
			$lessons = $this->plugin->synchroniser()->sync_lessons( 'lessons_manual', null, null, true );
		} catch ( ApiException $e ) {
			return sprintf(
				/* translators: %s: error message */
				__( 'Synchronisation failed: %s', 'course-schedule-connector' ),
				$e->getMessage()
			);
		}

		return sprintf(
			/* translators: 1: number of courses, 2: number of class occurrences */
			__( '%1$d courses and %2$d occurrences retrieved.', 'course-schedule-connector' ),
			$courses['records'],
			$lessons['records']
		);
	}

	/**
	 * Builds the label and value pairs for the stored data table.
	 *
	 * @param array<string, int> $stats Counts from the repository.
	 * @return array<string, int>
	 */
	private function rows( array $stats ): array {
		return array(
			__( 'Class occurrences stored', 'course-schedule-connector' ) => $stats['total'] ?? 0,
			__( 'Tied to a course', 'course-schedule-connector' ) => $stats['matched'] ?? 0,
			__( 'Rentals and open sessions', 'course-schedule-connector' ) => $stats['external'] ?? 0,
			__( 'Activities that take no bookings', 'course-schedule-connector' ) => $stats['not_bookable'] ?? 0,
			__( 'Make-up lessons', 'course-schedule-connector' ) => $stats['makeup'] ?? 0,
			__( 'Unresolved', 'course-schedule-connector' ) => $stats['unresolved'] ?? 0,
			__( 'Cancelled', 'course-schedule-connector' ) => $stats['canceled'] ?? 0,
		);
	}

	/**
	 * Renders the recent synchronisation log.
	 *
	 * @return void
	 */
	private function render_log(): void {
		$entries = $this->plugin->logger()->recent( 10 );

		if ( array() === $entries ) {
			echo '<p>' . esc_html__( 'Nothing has run yet.', 'course-schedule-connector' ) . '</p>';

			return;
		}

		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'When', 'course-schedule-connector' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Job', 'course-schedule-connector' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Records', 'course-schedule-connector' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Outcome', 'course-schedule-connector' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Detail', 'course-schedule-connector' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( (string) wp_date( 'j.n. H:i', (int) $entry['started_at'] ) ); ?></td>
						<td><?php echo esc_html( (string) $entry['job'] ); ?></td>
						<td><?php echo esc_html( (string) $entry['records'] ); ?></td>
						<td><?php echo esc_html( (string) $entry['outcome'] ); ?></td>
						<td><?php echo esc_html( (string) $entry['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
