<?php
/**
 * Settings screen.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Admin\Screen;

use CSCS\Admin\Capabilities;
use CSCS\Plugin;
use CSCS\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Lets an administrator configure the integration.
 *
 * Every value submitted here goes through the same validating setter the
 * command line uses. The form is a convenience; the rules live one layer down,
 * so there is no way to store through this screen something the client would
 * then refuse to use.
 */
final class SettingsPage {

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
		if ( ! Capabilities::can_manage_design() ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'course-schedule-connector' ) );
		}

		$messages = $this->save();
		$settings = $this->plugin->settings();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'iSport settings', 'course-schedule-connector' ); ?></h1>

			<?php foreach ( $messages['errors'] as $message ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endforeach; ?>

			<?php if ( array() !== $messages['saved'] ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						printf(
							/* translators: %d: number of settings */
							esc_html__( '%d setting(s) saved.', 'course-schedule-connector' ),
							count( $messages['saved'] )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'cscs_settings' ); ?>

				<h2><?php esc_html_e( 'Connection', 'course-schedule-connector' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->text_row(
						'api_base_url',
						__( 'Address of the iSport System', 'course-schedule-connector' ),
						(string) $settings->get( 'api_base_url' ),
						__( 'For example https://example.isportsystem.cz — https only. An address inside a private network is refused.', 'course-schedule-connector' )
					);
					$this->number_row( 'request_cap_per_hour', __( 'Maximum requests per hour', 'course-schedule-connector' ), (int) $settings->get( 'request_cap_per_hour' ), __( 'A ceiling the scheduled jobs can never exceed, whatever the intervals say.', 'course-schedule-connector' ) );
					$this->number_row( 'request_timeout', __( 'Request timeout (seconds)', 'course-schedule-connector' ), (int) $settings->get( 'request_timeout' ) );
					$this->number_row( 'request_retries', __( 'Retries', 'course-schedule-connector' ), (int) $settings->get( 'request_retries' ) );
					?>
				</table>

				<h2><?php esc_html_e( 'Term and synchronisation', 'course-schedule-connector' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->text_row( 'semester_from', __( 'Term starts', 'course-schedule-connector' ), (string) $settings->get( 'semester_from' ), __( 'In the form 2026-09-11. Applies to every course at once.', 'course-schedule-connector' ) );
					$this->text_row( 'semester_to', __( 'Term ends', 'course-schedule-connector' ), (string) $settings->get( 'semester_to' ), __( 'Decides how far ahead the timetable is retrieved.', 'course-schedule-connector' ) );
					$this->number_row( 'interval_courses', __( 'Course refresh interval (seconds)', 'course-schedule-connector' ), (int) $settings->get( 'interval_courses' ) );
					$this->number_row( 'interval_lessons_near', __( 'Timetable refresh interval (seconds)', 'course-schedule-connector' ), (int) $settings->get( 'interval_lessons_near' ) );
					$this->number_row( 'near_window_days', __( 'Days kept closely up to date', 'course-schedule-connector' ), (int) $settings->get( 'near_window_days' ) );
					$this->number_row( 'lesson_retention_days', __( 'Days of past timetable to keep', 'course-schedule-connector' ), (int) $settings->get( 'lesson_retention_days' ), __( 'Past occurrences older than this are removed. Courses are never deleted.', 'course-schedule-connector' ) );
					?>
				</table>

				<h2><?php esc_html_e( 'Display', 'course-schedule-connector' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->select_row(
						'lesson_price_when_empty',
						__( 'A class with no price shows', 'course-schedule-connector' ),
						(string) $settings->get( 'lesson_price_when_empty' ),
						array(
							'on_request' => __( 'On request', 'course-schedule-connector' ),
							'free'       => __( 'Free', 'course-schedule-connector' ),
						),
						__( 'A course with no price always shows as free. This is about hall rentals and open sessions, where "free" would be misleading.', 'course-schedule-connector' )
					);
					$this->checkbox_row( 'show_canceled_lessons', __( 'Show cancelled classes', 'course-schedule-connector' ), (bool) $settings->get( 'show_canceled_lessons' ), __( 'They appear struck through and labelled. Unticking hides them entirely.', 'course-schedule-connector' ) );
					$this->checkbox_row( 'show_isport_button', __( 'Show the booking button by default', 'course-schedule-connector' ), (bool) $settings->get( 'show_isport_button' ), __( 'Individual courses can override this.', 'course-schedule-connector' ) );
					$this->number_row( 'table_breakpoint', __( 'Width at which tables fold (pixels)', 'course-schedule-connector' ), (int) $settings->get( 'table_breakpoint' ) );
					?>
				</table>

				<h2><?php esc_html_e( 'Activities that take no bookings', 'course-schedule-connector' ); ?></h2>
				<p class="description" style="max-width:45em">
					<?php esc_html_e( 'Activities that occupy a slot in the timetable but accept no payments or registrations: courses run by outside lecturers, make-up lessons, individual training. Nothing in the remote data marks them, so they are listed here, one per line. A name is matched loosely, so "Zdravé cvičení" also covers "Zdravé cvičení s overbaly". A course that genuinely matches always wins over this list.', 'course-schedule-connector' ); ?>
				</p>
				<p>
					<textarea name="cscs[non_bookable_activities]" rows="10" cols="50" class="large-text code"><?php echo esc_textarea( implode( "\n", (array) $settings->get( 'non_bookable_activities' ) ) ); ?></textarea>
				</p>

				<h2><?php esc_html_e( 'What each tag means', 'course-schedule-connector' ); ?></h2>
				<p class="description" style="max-width:45em">
					<?php esc_html_e( 'The remote system tags every class, and a tag maintained there stays right for everyone, so it is asked before the list above. One rule per line, in the form label = category. Categories are course, external_course, makeup and rental. A label that is not listed here simply falls through to the list above.', 'course-schedule-connector' ); ?>
				</p>
				<p>
					<textarea name="cscs[tag_categories]" rows="7" cols="50" class="large-text code"><?php echo esc_textarea( $this->tag_lines( (array) $settings->get( 'tag_categories' ) ) ); ?></textarea>
				</p>

				<h2><?php esc_html_e( 'Removal', 'course-schedule-connector' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->checkbox_row(
						'delete_data_on_uninstall',
						__( 'Delete all data when the plugin is deleted', 'course-schedule-connector' ),
						(bool) $settings->get( 'delete_data_on_uninstall' ),
						__( 'Off by default. Deleting a plugin is not the same as wanting the course pages and everything written on them to disappear.', 'course-schedule-connector' )
					);
					?>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Saves whatever was submitted.
	 *
	 * @return array{saved: array<int, string>, errors: array<int, string>}
	 */
	private function save(): array {
		$result = array(
			'saved'  => array(),
			'errors' => array(),
		);

		if ( ! isset( $_POST['cscs'] ) || ! is_array( $_POST['cscs'] ) ) {
			return $result;
		}

		check_admin_referer( 'cscs_settings' );

		// Belt and braces: the screen already refuses to render without this,
		// but a request can arrive without ever rendering it.
		if ( ! Capabilities::can_manage_design() ) {
			$result['errors'][] = __( 'You are not allowed to change these settings.', 'course-schedule-connector' );

			return $result;
		}

		$submitted = wp_unslash( $_POST['cscs'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is sanitised by the validating setter below, per key.
		$settings  = $this->plugin->settings();
		$defaults  = Settings::defaults();

		foreach ( $defaults as $key => $default_value ) {
			if ( is_bool( $default_value ) ) {
				$value = isset( $submitted[ $key ] ) ? '1' : '0';
			} elseif ( ! array_key_exists( $key, $submitted ) ) {
				continue;
			} else {
				$value = $submitted[ $key ];
			}

			try {
				$settings->set( $key, $value );

				$result['saved'][] = $key;
			} catch ( \InvalidArgumentException $e ) {
				$result['errors'][] = sprintf( '%s: %s', $key, $e->getMessage() );
			}
		}

		return $result;
	}

	/**
	 * Renders the tag map as editable lines.
	 *
	 * @param array<string, string> $map Label to category.
	 * @return string
	 */
	private function tag_lines( array $map ): string {
		$lines = array();

		foreach ( $map as $label => $category ) {
			$lines[] = $label . ' = ' . $category;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Renders a text field row.
	 *
	 * @param string $key         Setting name.
	 * @param string $label       Field label.
	 * @param string $value       Current value.
	 * @param string $description Optional help text.
	 * @return void
	 */
	private function text_row( string $key, string $label, string $value, string $description = '' ): void {
		?>
		<tr>
			<th scope="row"><label for="cscs-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="text" class="regular-text" id="cscs-<?php echo esc_attr( $key ); ?>" name="cscs[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" />
				<?php $this->description( $description ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders a number field row.
	 *
	 * @param string $key         Setting name.
	 * @param string $label       Field label.
	 * @param int    $value       Current value.
	 * @param string $description Optional help text.
	 * @return void
	 */
	private function number_row( string $key, string $label, int $value, string $description = '' ): void {
		?>
		<tr>
			<th scope="row"><label for="cscs-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="number" min="0" step="1" class="small-text" id="cscs-<?php echo esc_attr( $key ); ?>" name="cscs[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>" />
				<?php $this->description( $description ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders a checkbox row.
	 *
	 * @param string $key         Setting name.
	 * @param string $label       Field label.
	 * @param bool   $value       Current value.
	 * @param string $description Optional help text.
	 * @return void
	 */
	private function checkbox_row( string $key, string $label, bool $value, string $description = '' ): void {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="cscs[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $value ); ?> />
					<?php esc_html_e( 'Yes', 'course-schedule-connector' ); ?>
				</label>
				<?php $this->description( $description ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders a select row.
	 *
	 * @param string                $key         Setting name.
	 * @param string                $label       Field label.
	 * @param string                $value       Current value.
	 * @param array<string, string> $choices     Value to label.
	 * @param string                $description Optional help text.
	 * @return void
	 */
	private function select_row( string $key, string $label, string $value, array $choices, string $description = '' ): void {
		?>
		<tr>
			<th scope="row"><label for="cscs-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<select id="cscs-<?php echo esc_attr( $key ); ?>" name="cscs[<?php echo esc_attr( $key ); ?>]">
					<?php foreach ( $choices as $choice => $choice_label ) : ?>
						<option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $value, $choice ); ?>><?php echo esc_html( $choice_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php $this->description( $description ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders help text.
	 *
	 * @param string $description Help text.
	 * @return void
	 */
	private function description( string $description ): void {
		if ( '' === $description ) {
			return;
		}

		echo '<p class="description" style="max-width:40em">' . esc_html( $description ) . '</p>';
	}
}
