<?php
/**
 * Make-up lessons screen.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Admin\Screen;

use CSCS\Admin\Capabilities;
use CSCS\Data\LessonRepository;
use CSCS\Data\Schema;
use CSCS\Plugin;
use CSCS\Support\Normalise;
use CSCS\Sync\MakeupResolver;
use CSCS\Sync\MakeupSiblings;

defined( 'ABSPATH' ) || exit;

/**
 * Ties each make-up lesson to the course it stands in for, by hand.
 *
 * Nothing in the remote data says which course a make-up lesson replaces a
 * class in. The timetable calls one "Náhradní lekce 4-6 let I. pololetí" and a
 * dozen courses run for that age group, and the name is reused rather than
 * owned: the next occurrence spelled the same way may belong to a different
 * course. So the answer comes from a person, one occurrence at a time, and this
 * screen is where they give it.
 *
 * It is deliberately a content screen rather than a settings one. Recording
 * which course a lesson belongs to is running the site, not configuring the
 * plugin, so the site manager may do it without an administrator.
 */
final class MakeupPage {

	/**
	 * Menu slug.
	 */
	public const SLUG = 'cscs-makeup';

	/**
	 * Most occurrences shown at once.
	 *
	 * A term holds a few dozen make-up lessons at the outside. The ceiling is
	 * here so that a mistake somewhere else cannot turn this screen into a page
	 * with thousands of select boxes on it.
	 */
	private const LIMIT = 500;

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

		$notice      = $this->handle_actions();
		$repository  = $this->plugin->lessons();
		$links       = $repository->makeup_links();
		$courses     = $this->plugin->courses()->names();
		$occurrences = $repository->by_status( LessonRepository::STATUS_MAKEUP, self::LIMIT );
		$suggestions = $this->suggestions( $occurrences, $courses );
		$proposals   = ( new MakeupSiblings() )->propose( $occurrences, $links );
		$filter      = $this->filter();
		$linked      = 0;

		foreach ( $occurrences as $row ) {
			$linked += isset( $links[ (int) $row['id_activity_term'] ] ) ? 1 : 0;
		}

		$counts = array(
			'all'      => count( $occurrences ),
			'unlinked' => count( $occurrences ) - $linked,
			'linked'   => $linked,
		);

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Make-up lessons', 'course-schedule-connector' ); ?></h1>

			<p class="description" style="max-width:52em">
				<?php esc_html_e( 'A make-up lesson replaces a class in exactly one course, but nothing in the timetable says which: the same name is used for the make-up slot of whichever course needs one, so two lessons named alike can belong to two different courses. Choose the course for each occurrence. Occurrences left unassigned are still counted and stored — they simply appear at no course. Nothing here is final: an assignment can be undone in the Assigned list.', 'course-schedule-connector' ); ?>
			</p>

			<?php if ( '' !== $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>

			<?php if ( array() === $courses ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'No courses are stored yet, so there is nothing to choose from. Synchronise first.', 'course-schedule-connector' ); ?></p>
				</div>
			<?php endif; ?>

			<ul class="subsubsub">
				<?php $this->filter_link( 'unlinked', __( 'Unassigned', 'course-schedule-connector' ), $counts['unlinked'], $filter, true ); ?>
				<?php $this->filter_link( 'linked', __( 'Assigned', 'course-schedule-connector' ), $counts['linked'], $filter, true ); ?>
				<?php $this->filter_link( 'all', __( 'All', 'course-schedule-connector' ), $counts['all'], $filter, false ); ?>
			</ul>

			<form method="post">
				<?php wp_nonce_field( 'cscs_makeup' ); ?>

				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Date', 'course-schedule-connector' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Time', 'course-schedule-connector' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Activity', 'course-schedule-connector' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Room', 'course-schedule-connector' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Trainer', 'course-schedule-connector' ); ?></th>
							<th scope="col" style="width:28em"><?php esc_html_e( 'Stands in for', 'course-schedule-connector' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$shown = 0;

						foreach ( $occurrences as $row ) {
							$term_id   = (int) ( $row['id_activity_term'] ?? 0 );
							$course_id = (int) ( $links[ $term_id ] ?? 0 );

							if ( 'unlinked' === $filter && 0 !== $course_id ) {
								continue;
							}

							if ( 'linked' === $filter && 0 === $course_id ) {
								continue;
							}

							++$shown;

							$this->row( $row, $term_id, $course_id, $courses, $suggestions, $proposals );
						}

						if ( 0 === $shown ) :
							?>
							<tr>
								<td colspan="6"><?php esc_html_e( 'Nothing to show here.', 'course-schedule-connector' ); ?></td>
							</tr>
							<?php
						endif;
						?>
					</tbody>
				</table>

				<p>
					<button type="submit" name="cscs_action" value="save" class="button button-primary">
						<?php esc_html_e( 'Save assignments', 'course-schedule-connector' ); ?>
					</button>
					<button type="submit" name="cscs_action" value="suggest" class="button">
						<?php esc_html_e( 'Fill in and save the suggestions', 'course-schedule-connector' ); ?>
					</button>
					<?php if ( array() !== $proposals ) : ?>
						<button type="submit" name="cscs_action" value="like-all" class="button">
							<?php esc_html_e( 'Assign the repeats like their series', 'course-schedule-connector' ); ?>
						</button>
					<?php endif; ?>
				</p>
				<p class="description" style="max-width:52em">
					<?php esc_html_e( 'A make-up slot usually repeats: the same name, the same weekday, the same hour, the same room. Where you have already assigned one of a series, the others can be assigned like it in one press, and each row offers the same button on its own. Where two occurrences of one series were assigned to two different courses, nothing is offered — the series is not a series. The trainer is not part of the comparison, because the timetable fills it in some weeks and leaves it blank others; it is on screen for you to weigh.', 'course-schedule-connector' ); ?>
				</p>
				<p class="description" style="max-width:52em">
					<?php esc_html_e( 'A suggestion is offered only where the lesson names its course and exactly one course fits. Filling them in records those, and only those: anything already assigned is left alone, and a suggestion you disagree with is changed like any other row. Nothing here is guessed on your behalf at synchronisation time.', 'course-schedule-connector' ); ?>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Counts the make-up occurrences nobody has assigned yet.
	 *
	 * Shown as a bubble in the menu, because these arrive a few at a time and
	 * nobody is going to open a screen on the off chance that something new is
	 * waiting on it.
	 *
	 * @param Plugin $plugin Plugin instance.
	 * @return int
	 */
	public static function pending( Plugin $plugin ): int {
		// The menu is built on every admin page, including the one right after
		// activation, and the table is created a hook later than that.
		if ( 0 === (int) get_option( Schema::VERSION_OPTION, 0 ) ) {
			return 0;
		}

		return count( $plugin->lessons()->unlinked_makeup_terms() );
	}

	/**
	 * Renders one occurrence.
	 *
	 * @param array<string, mixed> $row         Stored row.
	 * @param int                  $term_id     Occurrence id.
	 * @param int                  $course_id   Currently linked course, or 0.
	 * @param array<int, string>   $courses     Course id to name.
	 * @param array<string, int>   $suggestions Match key to course id.
	 * @param array<int, int>      $proposals   Occurrence id to the course its series uses.
	 * @return void
	 */
	private function row( array $row, int $term_id, int $course_id, array $courses, array $suggestions, array $proposals ): void {
		$name       = (string) ( $row['activity_name'] ?? '' );
		$date       = (string) ( $row['lesson_date'] ?? '' );
		$suggested  = (int) ( $suggestions[ Normalise::match_key( $name ) ] ?? 0 );
		$proposed   = (int) ( $proposals[ $term_id ] ?? 0 );
		$field      = 'cscs_makeup[' . $term_id . ']';
		$identifier = 'cscs-makeup-' . $term_id;

		?>
		<tr>
			<td>
				<?php echo esc_html( '' === $date ? '—' : mysql2date( 'D j. n. Y', $date ) ); ?>
				<br />
				<span class="description">#<?php echo esc_html( (string) $term_id ); ?></span>
			</td>
			<td><?php echo esc_html( substr( (string) ( $row['time_from'] ?? '' ), 0, 5 ) ); ?></td>
			<td><?php echo esc_html( $name ); ?></td>
			<td><?php echo esc_html( (string) ( $row['tab_name'] ?? '' ) ); ?></td>
			<td><?php echo esc_html( (string) ( $row['trainer_name'] ?? '' ) ); ?></td>
			<td>
				<label class="screen-reader-text" for="<?php echo esc_attr( $identifier ); ?>">
					<?php esc_html_e( 'Course this lesson stands in for', 'course-schedule-connector' ); ?>
				</label>
				<select id="<?php echo esc_attr( $identifier ); ?>" name="<?php echo esc_attr( $field ); ?>" style="max-width:100%">
					<option value="0"><?php esc_html_e( '— not assigned —', 'course-schedule-connector' ); ?></option>
					<?php if ( 0 !== $course_id && ! isset( $courses[ $course_id ] ) ) : ?>
						<option value="<?php echo esc_attr( (string) $course_id ); ?>" selected="selected">
							<?php
							printf(
								/* translators: %d: course id */
								esc_html__( 'Course %d, which is no longer stored', 'course-schedule-connector' ),
								(int) $course_id
							);
							?>
						</option>
					<?php endif; ?>
					<?php foreach ( $courses as $id => $course_name ) : ?>
						<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( $course_id, $id ); ?>>
							<?php echo esc_html( $id . ' · ' . $course_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php if ( 0 === $course_id && 0 !== $suggested ) : ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: course id and name */
							esc_html__( 'Suggested by the name: %s', 'course-schedule-connector' ),
							esc_html( $suggested . ' · ' . ( $courses[ $suggested ] ?? '' ) )
						);
						?>
					</p>
				<?php endif; ?>
				<?php if ( 0 !== $course_id ) : ?>
					<p>
						<button type="submit" name="cscs_action" value="clear-<?php echo esc_attr( (string) $term_id ); ?>" class="button button-small">
							<?php esc_html_e( 'Undo this assignment', 'course-schedule-connector' ); ?>
						</button>
					</p>
				<?php endif; ?>
				<?php if ( 0 === $course_id && 0 !== $proposed ) : ?>
					<p>
						<button type="submit" name="cscs_action" value="like-<?php echo esc_attr( (string) $term_id ); ?>" class="button button-small">
							<?php
							printf(
								/* translators: %s: course id and name */
								esc_html__( 'Assign like the rest of this series: %s', 'course-schedule-connector' ),
								esc_html( $proposed . ' · ' . ( $courses[ $proposed ] ?? '' ) )
							);
							?>
						</button>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders one filter link.
	 *
	 * @param string $value     Filter value.
	 * @param string $label     Link text.
	 * @param int    $count     Number of occurrences.
	 * @param string $current   Filter in effect.
	 * @param bool   $separator Whether a separator follows.
	 * @return void
	 */
	private function filter_link( string $value, string $label, int $count, string $current, bool $separator ): void {
		$url = add_query_arg(
			array(
				'page'   => self::SLUG,
				'filter' => $value,
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
	 * Returns the filter in effect.
	 *
	 * @return string
	 */
	private function filter(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Choosing which rows to look at changes nothing.
		$filter = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : 'unlinked';

		return in_array( $filter, array( 'all', 'linked', 'unlinked' ), true ) ? $filter : 'unlinked';
	}

	/**
	 * Saves whatever was submitted.
	 *
	 * @return string Message to show, empty when nothing was submitted.
	 */
	private function handle_actions(): string {
		if ( ! isset( $_POST['cscs_action'] ) ) {
			return '';
		}

		check_admin_referer( 'cscs_makeup' );

		// The screen already refuses to render without this, but a request can
		// arrive without ever rendering it.
		if ( ! Capabilities::can_manage_content() ) {
			return '';
		}

		$action = sanitize_key( wp_unslash( $_POST['cscs_action'] ) );

		// Whatever the button, the fields that came with it are saved first.
		// Pressing "assign like the series" after changing three rows by hand
		// must not throw those three changes away.
		$messages = array_filter( array( $this->save_selection() ) );

		if ( 'suggest' === $action ) {
			$messages[] = $this->apply_suggestions();
		}

		if ( str_starts_with( $action, 'clear-' ) ) {
			$messages[] = $this->clear( (int) substr( $action, 6 ) );
		}

		if ( str_starts_with( $action, 'like-' ) ) {
			$messages[] = $this->apply_series( 'like-all' === $action ? 0 : (int) substr( $action, 5 ) );
		}

		if ( array() === $messages ) {
			return __( 'Nothing changed.', 'course-schedule-connector' );
		}

		return implode( ' ', $messages );
	}

	/**
	 * Records whatever the select boxes were submitted with.
	 *
	 * @return string Message, empty when nothing changed.
	 */
	private function save_selection(): string {
		$submitted = isset( $_POST['cscs_makeup'] ) && is_array( $_POST['cscs_makeup'] )
			? wp_unslash( $_POST['cscs_makeup'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Both the key and the value are cast to integers below.
			: array();

		$repository = $this->plugin->lessons();
		$links      = $repository->makeup_links();
		$changed    = 0;

		foreach ( $submitted as $term_id => $course_id ) {
			$term_id   = (int) $term_id;
			$course_id = (int) $course_id;

			if ( $course_id === (int) ( $links[ $term_id ] ?? 0 ) ) {
				continue;
			}

			if ( $repository->link_makeup( $term_id, $course_id ) ) {
				++$changed;
			}
		}

		if ( 0 === $changed ) {
			return '';
		}

		return sprintf(
			/* translators: %d: number of make-up lessons */
			_n( '%d make-up lesson saved. Assigned ones are in the Assigned list, where any of them can be undone.', '%d make-up lessons saved. Assigned ones are in the Assigned list, where any of them can be undone.', $changed, 'course-schedule-connector' ),
			$changed
		);
	}

	/**
	 * Takes one assignment back.
	 *
	 * Every assignment here is a judgement somebody made, and a judgement that
	 * cannot be taken back is one people hesitate to make. Clearing the choice
	 * in the list beside the row does the same thing, but only for somebody who
	 * already knows where the row went after it was saved.
	 *
	 * @param int $term_id Occurrence id.
	 * @return string Message to show.
	 */
	private function clear( int $term_id ): string {
		if ( 0 === $term_id || ! $this->plugin->lessons()->link_makeup( $term_id, 0 ) ) {
			return __( 'Nothing to undo.', 'course-schedule-connector' );
		}

		return __( 'Assignment undone. The occurrence is unassigned again.', 'course-schedule-connector' );
	}

	/**
	 * Assigns an occurrence, or every one of them, the way its series is.
	 *
	 * @param int $term_id Occurrence to assign, or 0 for all that can be.
	 * @return string Message to show.
	 */
	private function apply_series( int $term_id ): string {
		$repository  = $this->plugin->lessons();
		$occurrences = $repository->by_status( LessonRepository::STATUS_MAKEUP, self::LIMIT );
		$proposals   = ( new MakeupSiblings() )->propose( $occurrences, $repository->makeup_links() );

		if ( 0 !== $term_id ) {
			$proposals = array_intersect_key( $proposals, array( $term_id => 0 ) );
		}

		$assigned = 0;

		foreach ( $proposals as $occurrence => $course_id ) {
			if ( $repository->link_makeup( (int) $occurrence, (int) $course_id ) ) {
				++$assigned;
			}
		}

		if ( 0 === $assigned ) {
			return __( 'Nothing was assigned: the series either disagrees about its course or has no assignment to copy.', 'course-schedule-connector' );
		}

		return sprintf(
			/* translators: %d: number of make-up lessons */
			_n( '%d repeat assigned like its series.', '%d repeats assigned like their series.', $assigned, 'course-schedule-connector' ),
			$assigned
		);
	}

	/**
	 * Records the suggestion for every occurrence that has one and no course.
	 *
	 * @return string Message to show.
	 */
	private function apply_suggestions(): string {
		$repository  = $this->plugin->lessons();
		$links       = $repository->makeup_links();
		$courses     = $this->plugin->courses()->names();
		$occurrences = $repository->by_status( LessonRepository::STATUS_MAKEUP, self::LIMIT );
		$suggestions = $this->suggestions( $occurrences, $courses );
		$filled      = 0;

		foreach ( $occurrences as $row ) {
			$term_id = (int) ( $row['id_activity_term'] ?? 0 );

			if ( 0 !== (int) ( $links[ $term_id ] ?? 0 ) ) {
				continue;
			}

			$course_id = (int) ( $suggestions[ Normalise::match_key( (string) ( $row['activity_name'] ?? '' ) ) ] ?? 0 );

			if ( 0 !== $course_id && $repository->link_makeup( $term_id, $course_id ) ) {
				++$filled;
			}
		}

		if ( 0 === $filled ) {
			return __( 'No lesson names a course clearly enough to suggest one.', 'course-schedule-connector' );
		}

		return sprintf(
			/* translators: %d: number of make-up lessons */
			_n( '%d suggestion applied. Check it before relying on it.', '%d suggestions applied. Check them before relying on them.', $filled, 'course-schedule-connector' ),
			$filled
		);
	}

	/**
	 * Asks the resolver which course each make-up lesson names, if any.
	 *
	 * The suggestion follows from the name alone, so it is the same for every
	 * occurrence sharing a name. That is exactly why it stays a suggestion: a
	 * person confirms it for each occurrence, because the next one named alike
	 * may well belong elsewhere.
	 *
	 * @param array<int, array<string, mixed>> $occurrences Make-up occurrences.
	 * @param array<int, string>               $courses     Course id to name.
	 * @return array<string, int> Match key to course id.
	 */
	private function suggestions( array $occurrences, array $courses ): array {
		$names = array();

		foreach ( $occurrences as $row ) {
			$name = (string) ( $row['activity_name'] ?? '' );

			if ( '' !== $name ) {
				$names[ Normalise::match_key( $name ) ] = $name;
			}
		}

		if ( array() === $names || array() === $courses ) {
			return array();
		}

		return ( new MakeupResolver() )->suggest_by_names( array_values( $names ), $courses );
	}
}
