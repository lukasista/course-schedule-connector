<?php
/**
 * Unmatched lessons screen.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Admin\Screen;

use CSCS\Admin\Capabilities;
use CSCS\Api\ApiException;
use CSCS\Data\LessonRepository;
use CSCS\Data\Schema;
use CSCS\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Shows what the matching did with each class, and lets a person overrule it.
 *
 * iSport publishes no link between a course and the classes that make it up, so
 * the plugin infers one from the name and the timestamp. It gets that right for
 * everything the gym runs today, but an inference is still an inference: this
 * is where somebody can see what it decided and say otherwise.
 *
 * An assignment made here is permanent. Every later synchronisation honours it
 * rather than re-deciding, because a person who has told the software the
 * answer once should not have to tell it again next Tuesday.
 */
final class UnmatchedPage {

	/**
	 * Menu slug.
	 */
	public const SLUG = 'cscs-unmatched';

	/**
	 * Most occurrences shown at once.
	 */
	private const LIMIT = 300;

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
	 * Counts the occurrences that should have found a course and did not.
	 *
	 * @param Plugin $plugin Plugin instance.
	 * @return int
	 */
	public static function pending( Plugin $plugin ): int {
		if ( 0 === (int) get_option( Schema::VERSION_OPTION, 0 ) ) {
			return 0;
		}

		return (int) ( $plugin->lessons()->stats()['unresolved'] ?? 0 );
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

		$notice     = $this->handle_actions();
		$repository = $this->plugin->lessons();
		$status     = $this->status();
		$rows       = $repository->by_status( $status, self::LIMIT );
		$courses    = $this->plugin->courses()->names();
		$manual     = $repository->manual_assignments();
		$stats      = $repository->stats();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Unmatched lessons', 'course-schedule-connector' ); ?></h1>

			<p class="description" style="max-width:52em">
				<?php esc_html_e( 'iSport publishes no link between a course and the classes that make it up, so the plugin works it out from the name and the time. Anything it could not place with confidence is here, together with the classes it decided belong to nobody — hall rentals, open sessions, and activities that take no bookings. Choosing a course records the answer for good: no later synchronisation will decide otherwise.', 'course-schedule-connector' ); ?>
			</p>

			<?php if ( '' !== $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>

			<ul class="subsubsub">
				<?php
				$filters = array(
					LessonRepository::STATUS_UNRESOLVED   => __( 'Unresolved', 'course-schedule-connector' ),
					LessonRepository::STATUS_EXTERNAL     => __( 'Rentals and open sessions', 'course-schedule-connector' ),
					LessonRepository::STATUS_NOT_BOOKABLE => __( 'Activities that take no bookings', 'course-schedule-connector' ),
				);

				$last = array_key_last( $filters );

				foreach ( $filters as $value => $label ) {
					$this->filter_link( $value, $label, (int) ( $stats[ $value ] ?? 0 ), $status, $value !== $last );
				}
				?>
			</ul>

			<form method="post">
				<?php wp_nonce_field( 'cscs_unmatched' ); ?>

				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Date', 'course-schedule-connector' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Time', 'course-schedule-connector' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Activity', 'course-schedule-connector' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Room', 'course-schedule-connector' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Trainer', 'course-schedule-connector' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Tags', 'course-schedule-connector' ); ?></th>
							<th scope="col" style="width:26em"><?php esc_html_e( 'Belongs to', 'course-schedule-connector' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( array() === $rows ) : ?>
							<tr><td colspan="7"><?php esc_html_e( 'Nothing to show here.', 'course-schedule-connector' ); ?></td></tr>
						<?php endif; ?>

						<?php foreach ( $rows as $row ) : ?>
							<?php $this->row( $row, $courses, $manual ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p>
					<button type="submit" name="cscs_action" value="assign" class="button button-primary">
						<?php esc_html_e( 'Save assignments', 'course-schedule-connector' ); ?>
					</button>
				</p>
				<p class="description" style="max-width:52em">
					<?php esc_html_e( 'Saving re-runs the matching over everything already stored, so an assignment takes effect at once and without a request to iSport.', 'course-schedule-connector' ); ?>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders one occurrence.
	 *
	 * @param array<string, mixed> $row     Stored row.
	 * @param array<int, string>   $courses Course id to name.
	 * @param array<int, int>      $manual  Occurrence id to course id.
	 * @return void
	 */
	private function row( array $row, array $courses, array $manual ): void {
		$term_id   = (int) ( $row['id_activity_term'] ?? 0 );
		$course_id = (int) ( $manual[ $term_id ] ?? 0 );
		$date      = (string) ( $row['lesson_date'] ?? '' );

		?>
		<tr>
			<td>
				<?php echo esc_html( '' === $date ? '—' : mysql2date( 'D j. n. Y', $date ) ); ?>
				<br /><span class="description">#<?php echo esc_html( (string) $term_id ); ?></span>
			</td>
			<td><?php echo esc_html( substr( (string) ( $row['time_from'] ?? '' ), 0, 5 ) ); ?></td>
			<td><?php echo esc_html( (string) ( $row['activity_name'] ?? '' ) ); ?></td>
			<td><?php echo esc_html( (string) ( $row['tab_name'] ?? '' ) ); ?></td>
			<td><?php echo esc_html( (string) ( $row['trainer_name'] ?? '' ) ); ?></td>
			<td><?php echo esc_html( (string) ( $row['tags'] ?? '' ) ); ?></td>
			<td>
				<label class="screen-reader-text" for="cscs-assign-<?php echo esc_attr( (string) $term_id ); ?>">
					<?php esc_html_e( 'Course this class belongs to', 'course-schedule-connector' ); ?>
				</label>
				<select id="cscs-assign-<?php echo esc_attr( (string) $term_id ); ?>" name="cscs_assign[<?php echo esc_attr( (string) $term_id ); ?>]" style="max-width:100%">
					<option value="0"><?php esc_html_e( '— belongs to no course —', 'course-schedule-connector' ); ?></option>
					<?php foreach ( $courses as $id => $name ) : ?>
						<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( $course_id, $id ); ?>><?php echo esc_html( $id . ' · ' . $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders one filter link.
	 *
	 * @param string $value     Status.
	 * @param string $label     Link text.
	 * @param int    $count     Number of occurrences.
	 * @param string $current   Status in effect.
	 * @param bool   $separator Whether a separator follows.
	 * @return void
	 */
	private function filter_link( string $value, string $label, int $count, string $current, bool $separator ): void {
		$url = add_query_arg(
			array(
				'page'   => self::SLUG,
				'status' => $value,
			),
			admin_url( 'admin.php' )
		);

		?>
		<li>
			<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $value === $current ? 'current' : '' ); ?>">
				<?php echo esc_html( $label ); ?>
				<span class="count">(<?php echo esc_html( number_format_i18n( $count ) ); ?>)</span>
			</a>
			<?php if ( $separator ) : ?> |<?php endif; ?>
		</li>
		<?php
	}

	/**
	 * Returns the status being listed.
	 *
	 * @return string
	 */
	private function status(): string {
		$allowed = array(
			LessonRepository::STATUS_UNRESOLVED,
			LessonRepository::STATUS_EXTERNAL,
			LessonRepository::STATUS_NOT_BOOKABLE,
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Choosing which rows to look at changes nothing.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

		return in_array( $status, $allowed, true ) ? $status : LessonRepository::STATUS_UNRESOLVED;
	}

	/**
	 * Saves whatever was submitted.
	 *
	 * @return string Message to show.
	 */
	private function handle_actions(): string {
		if ( ! isset( $_POST['cscs_action'] ) ) {
			return '';
		}

		check_admin_referer( 'cscs_unmatched' );

		if ( ! Capabilities::can_manage_content() ) {
			return '';
		}

		$submitted = isset( $_POST['cscs_assign'] ) && is_array( $_POST['cscs_assign'] )
			? wp_unslash( $_POST['cscs_assign'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Both the key and the value are cast to integers below.
			: array();

		$repository = $this->plugin->lessons();
		$manual     = $repository->manual_assignments();
		$changed    = 0;

		foreach ( $submitted as $term_id => $course_id ) {
			$term_id   = (int) $term_id;
			$course_id = (int) $course_id;

			if ( 0 === $term_id || $course_id === (int) ( $manual[ $term_id ] ?? 0 ) ) {
				continue;
			}

			$repository->assign_manually( $term_id, $course_id );
			++$changed;
		}

		if ( 0 === $changed ) {
			return __( 'Nothing changed.', 'course-schedule-connector' );
		}

		// An assignment nobody can see the effect of is an assignment nobody
		// trusts. Matching runs over what is already stored, so this costs no
		// request to iSport.
		try {
			$this->plugin->synchroniser()->rematch();
		} catch ( ApiException $e ) {
			return sprintf(
				/* translators: %s: error message */
				__( 'Saved, but the matching could not be re-run: %s', 'course-schedule-connector' ),
				$e->getMessage()
			);
		}

		return sprintf(
			/* translators: %d: number of classes */
			_n( '%d class assigned and the matching re-run.', '%d classes assigned and the matching re-run.', $changed, 'course-schedule-connector' ),
			$changed
		);
	}
}
