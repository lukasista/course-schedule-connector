<?php
/**
 * Course editing screen.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Admin;

use CSCS\Data\CourseRepository;
use CSCS\Data\PostType;
use CSCS\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Adds to a course the things the remote system has nowhere to put.
 *
 * A course arrives from iSport as facts: a price, a capacity, a room, a set of
 * dates. What it never carries is the part a person writes — who to ring about
 * a course nobody books online, whether this particular course should show a
 * booking button, and which of the synced facts a human has corrected and would
 * rather not have corrected back tomorrow morning.
 *
 * The last of those is the reason this screen exists at all. Losing an
 * afternoon's editing to a scheduled job is the fastest way to make somebody
 * stop trusting an integration, so anything ticked here is left alone by every
 * later synchronisation, permanently and visibly.
 */
final class CourseEditor {

	/**
	 * Nonce action.
	 */
	private const NONCE = 'cscs_course_meta';

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
	 * Hooks the screen.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes_' . PostType::COURSE, array( $this, 'add_boxes' ) );
		add_action( 'save_post_' . PostType::COURSE, array( $this, 'ensure_id' ), 5, 2 );
		add_action( 'save_post_' . PostType::COURSE, array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Registers the boxes.
	 *
	 * @return void
	 */
	public function add_boxes(): void {
		add_meta_box(
			'cscs-course-content',
			__( 'Contact and booking', 'course-schedule-connector' ),
			array( $this, 'render_content_box' ),
			PostType::COURSE,
			'normal',
			'high'
		);

		add_meta_box(
			'cscs-course-locks',
			__( 'Fields synchronisation must not touch', 'course-schedule-connector' ),
			array( $this, 'render_lock_box' ),
			PostType::COURSE,
			'side'
		);

		add_meta_box(
			'cscs-course-facts',
			__( 'From iSport', 'course-schedule-connector' ),
			array( $this, 'render_facts_box' ),
			PostType::COURSE,
			'side'
		);
	}

	/**
	 * Renders the contact and booking fields.
	 *
	 * @param \WP_Post $post Course.
	 * @return void
	 */
	public function render_content_box( \WP_Post $post ): void {
		$button = (string) get_post_meta( $post->ID, CourseRepository::META_BUTTON, true );
		$button = in_array( $button, array( 'always', 'never' ), true ) ? $button : 'default';

		wp_nonce_field( self::NONCE, 'cscs_course_nonce' );

		?>
		<p class="description" style="max-width:45em">
			<?php esc_html_e( 'Some courses take no bookings through iSport at all, and a visitor who wants one needs a person to ask. Anything filled in here is shown on the course, and nothing here is ever overwritten by a synchronisation.', 'course-schedule-connector' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cscs-contact-name"><?php esc_html_e( 'Who to contact', 'course-schedule-connector' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="cscs-contact-name" name="cscs_course[contact_name]" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, CourseRepository::META_CONTACT_NAME, true ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Leave empty to show the trainer iSport names.', 'course-schedule-connector' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cscs-contact-email"><?php esc_html_e( 'E-mail', 'course-schedule-connector' ); ?></label></th>
				<td><input type="email" class="regular-text" id="cscs-contact-email" name="cscs_course[contact_email]" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, CourseRepository::META_CONTACT_EMAIL, true ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="cscs-contact-phone"><?php esc_html_e( 'Telephone', 'course-schedule-connector' ); ?></label></th>
				<td><input type="text" class="regular-text" id="cscs-contact-phone" name="cscs_course[contact_phone]" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, CourseRepository::META_CONTACT_PHONE, true ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="cscs-contact-note"><?php esc_html_e( 'Note', 'course-schedule-connector' ); ?></label></th>
				<td>
					<textarea id="cscs-contact-note" name="cscs_course[contact_note]" rows="3" class="large-text"><?php echo esc_textarea( (string) get_post_meta( $post->ID, CourseRepository::META_CONTACT_NOTE, true ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'For example when to ring, or that places are arranged individually.', 'course-schedule-connector' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cscs-show-button"><?php esc_html_e( 'Booking button', 'course-schedule-connector' ); ?></label></th>
				<td>
					<select id="cscs-show-button" name="cscs_course[show_button]">
						<option value="default" <?php selected( $button, 'default' ); ?>><?php esc_html_e( 'As the settings say', 'course-schedule-connector' ); ?></option>
						<option value="always" <?php selected( $button, 'always' ); ?>><?php esc_html_e( 'Always show it', 'course-schedule-connector' ); ?></option>
						<option value="never" <?php selected( $button, 'never' ); ?>><?php esc_html_e( 'Never show it', 'course-schedule-connector' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Even when shown, the button hides itself while the course is full or iSport reports that it takes no registrations.', 'course-schedule-connector' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Renders the field locks.
	 *
	 * @param \WP_Post $post Course.
	 * @return void
	 */
	public function render_lock_box( \WP_Post $post ): void {
		$locked = $this->plugin->courses()->locked_fields( $post->ID );

		?>
		<p class="description">
			<?php esc_html_e( 'A ticked field keeps whatever is written here. Synchronisation refreshes everything else and leaves this alone, for good.', 'course-schedule-connector' ); ?>
		</p>
		<ul>
			<?php foreach ( self::lockable() as $field => $label ) : ?>
				<li>
					<label>
						<input type="checkbox" name="cscs_course[locked][]" value="<?php echo esc_attr( $field ); ?>" <?php checked( in_array( $field, $locked, true ) ); ?> />
						<?php echo esc_html( $label ); ?>
					</label>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Renders what the remote system says about this course.
	 *
	 * @param \WP_Post $post Course.
	 * @return void
	 */
	public function render_facts_box( \WP_Post $post ): void {
		$url    = (string) get_post_meta( $post->ID, '_cscs_course_url', true );
		$synced = (int) get_post_meta( $post->ID, '_cscs_synced_at', true );

		$course_id = (int) get_post_meta( $post->ID, CourseRepository::META_ID, true );

		$rows = array(
			__( 'Course id', 'course-schedule-connector' )   => CourseRepository::is_manual( $course_id )
				? sprintf(
					/* translators: %d: course id */
					__( '%d — created here, not in iSport', 'course-schedule-connector' ),
					$course_id
				)
				: (string) $course_id,
			__( 'Activity', 'course-schedule-connector' )    => (string) get_post_meta( $post->ID, '_cscs_activity_name', true ),
			__( 'State', 'course-schedule-connector' )       => $this->state_label( (string) get_post_meta( $post->ID, CourseRepository::META_STATUS, true ) ),
			__( 'Price', 'course-schedule-connector' )       => $this->price_label( get_post_meta( $post->ID, '_cscs_price', true ) ),
			__( 'Runs from', 'course-schedule-connector' )   => (string) get_post_meta( $post->ID, '_cscs_date_from', true ),
			__( 'Runs to', 'course-schedule-connector' )     => (string) get_post_meta( $post->ID, '_cscs_date_to', true ),
			__( 'Capacity', 'course-schedule-connector' )    => (string) get_post_meta( $post->ID, '_cscs_capacity', true ),
			__( 'Taken', 'course-schedule-connector' )       => (string) get_post_meta( $post->ID, '_cscs_occupied', true ),
			__( 'Last retrieved', 'course-schedule-connector' ) => 0 === $synced ? '' : wp_date( 'j. n. Y H:i', $synced ),
		);

		echo '<table class="widefat striped"><tbody>';

		foreach ( $rows as $label => $value ) {
			printf(
				'<tr><th scope="row">%s</th><td>%s</td></tr>',
				esc_html( (string) $label ),
				esc_html( '' === $value ? '—' : $value )
			);
		}

		echo '</tbody></table>';

		if ( '' !== $url ) {
			printf(
				'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
				esc_url( $url ),
				esc_html__( 'Open this course in iSport', 'course-schedule-connector' )
			);
		}
	}

	/**
	 * Gives a hand-made course an id of its own.
	 *
	 * A course created through the WordPress editor has no id in iSport, and
	 * everything downstream is written in terms of course ids. Rather than
	 * teach each of those about a second kind of course, one is invented here,
	 * once, in a range the remote system will never reach.
	 *
	 * @param int      $post_id Course id.
	 * @param \WP_Post $post    Course.
	 * @return void
	 */
	public function ensure_id( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( in_array( $post->post_status, array( 'auto-draft', 'trash', 'inherit' ), true ) ) {
			return;
		}

		$this->plugin->courses()->ensure_manual_id( $post_id );
	}

	/**
	 * Saves the fields.
	 *
	 * @param int      $post_id Course id.
	 * @param \WP_Post $post    Course.
	 * @return void
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		unset( $post );

		if ( ! isset( $_POST['cscs_course_nonce'] ) || ! isset( $_POST['cscs_course'] ) || ! is_array( $_POST['cscs_course'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['cscs_course_nonce'] ) ), self::NONCE ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Two checks, not one: editing this post is a WordPress question, and
		// maintaining course content is the plugin's own.
		if ( ! current_user_can( 'edit_post', $post_id ) || ! Capabilities::can_manage_content() ) {
			return;
		}

		$submitted = wp_unslash( $_POST['cscs_course'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every value is sanitised field by field below.
		$button    = sanitize_key( (string) ( $submitted['show_button'] ?? 'default' ) );

		update_post_meta( $post_id, CourseRepository::META_BUTTON, in_array( $button, array( 'always', 'never' ), true ) ? $button : 'default' );
		update_post_meta( $post_id, CourseRepository::META_CONTACT_NAME, sanitize_text_field( (string) ( $submitted['contact_name'] ?? '' ) ) );
		update_post_meta( $post_id, CourseRepository::META_CONTACT_EMAIL, sanitize_email( (string) ( $submitted['contact_email'] ?? '' ) ) );
		update_post_meta( $post_id, CourseRepository::META_CONTACT_PHONE, sanitize_text_field( (string) ( $submitted['contact_phone'] ?? '' ) ) );
		update_post_meta( $post_id, CourseRepository::META_CONTACT_NOTE, sanitize_textarea_field( (string) ( $submitted['contact_note'] ?? '' ) ) );

		$locked = array_intersect(
			array_map( 'sanitize_key', (array) ( $submitted['locked'] ?? array() ) ),
			array_keys( self::lockable() )
		);

		update_post_meta( $post_id, CourseRepository::META_LOCKED, array_values( $locked ) );
	}

	/**
	 * Returns the fields that may be locked, and what to call them.
	 *
	 * Deliberately short. Locking a capacity or an occupancy would freeze a
	 * number that changes by the hour and turn the page into a lie; these are
	 * the fields somebody has a reason to word differently.
	 *
	 * @return array<string, string>
	 */
	public static function lockable(): array {
		return array(
			'post_title'            => __( 'Course name', 'course-schedule-connector' ),
			'_cscs_api_description' => __( 'Description from iSport', 'course-schedule-connector' ),
			'_cscs_trainer_name'    => __( 'Trainer', 'course-schedule-connector' ),
			'_cscs_room_name'       => __( 'Room', 'course-schedule-connector' ),
			'_cscs_price'           => __( 'Price', 'course-schedule-connector' ),
		);
	}

	/**
	 * Renders the lifecycle state.
	 *
	 * @param string $status Stored state.
	 * @return string
	 */
	private function state_label( string $status ): string {
		$labels = array(
			CourseRepository::STATUS_RUNNING  => __( 'Running', 'course-schedule-connector' ),
			CourseRepository::STATUS_FINISHED => __( 'Finished', 'course-schedule-connector' ),
			CourseRepository::STATUS_ARCHIVED => __( 'No longer offered by iSport', 'course-schedule-connector' ),
		);

		return $labels[ $status ] ?? '';
	}

	/**
	 * Renders the price, including the case of not having one.
	 *
	 * @param mixed $price Stored price.
	 * @return string
	 */
	private function price_label( $price ): string {
		if ( null === $price || '' === $price ) {
			return __( 'Free', 'course-schedule-connector' );
		}

		return (string) $price;
	}
}
