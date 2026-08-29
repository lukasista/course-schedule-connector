<?php
/**
 * Trainer editing screen.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Admin;

defined( 'ABSPATH' ) || exit;

use CSCS\Data\TrainerRepository;
use CSCS\Data\TrainerType;
use CSCS\Plugin;

/**
 * The part of a trainer that iSport has nowhere to put.
 *
 * Qualifications and interests are lists because that is what they are: a
 * person holds three certificates, not a paragraph mentioning three. Keeping
 * them as lines rather than prose means a page can print them as a list, count
 * them, or show the first two — and none of that is possible once somebody has
 * typed them into a text box with commas.
 *
 * The rows are added and removed without JavaScript being required for it: the
 * form posts a fixed set of lines, empty ones are dropped on save, and the
 * screen always offers a spare. The small script only saves the round trip.
 */
final class TrainerEditor {

	/**
	 * Nonce action.
	 */
	private const NONCE = 'cscs_trainer_meta';

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
		add_action( 'add_meta_boxes_' . TrainerType::TRAINER, array( $this, 'add_boxes' ) );
		add_action( 'save_post_' . TrainerType::TRAINER, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Registers the boxes.
	 *
	 * @return void
	 */
	public function add_boxes(): void {
		add_meta_box(
			'cscs-trainer-about',
			__( 'About the trainer', 'course-schedule-connector' ),
			array( $this, 'render_about_box' ),
			TrainerType::TRAINER,
			'normal',
			'high'
		);

		add_meta_box(
			'cscs-trainer-courses',
			__( 'Courses this trainer runs', 'course-schedule-connector' ),
			array( $this, 'render_courses_box' ),
			TrainerType::TRAINER,
			'side'
		);

		add_meta_box(
			'cscs-trainer-photo',
			__( 'Photograph', 'course-schedule-connector' ),
			array( $this, 'render_photo_box' ),
			TrainerType::TRAINER,
			'side'
		);
	}

	/**
	 * Puts the small script for the repeating rows on this screen only.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen instanceof \WP_Screen || TrainerType::TRAINER !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'cscs-admin',
			CSCS_URL . 'assets/css/cscs-admin.css',
			array(),
			CSCS_VERSION
		);

		wp_enqueue_script(
			'cscs-trainer-editor',
			CSCS_URL . 'assets/js/cscs-trainer-editor.js',
			array(),
			CSCS_VERSION,
			true
		);

		wp_set_script_translations( 'cscs-trainer-editor', 'course-schedule-connector', CSCS_DIR . 'languages' );
	}

	/**
	 * Renders the lists, the fact and the motto.
	 *
	 * @param \WP_Post $post Trainer.
	 * @return void
	 */
	public function render_about_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, 'cscs_trainer_nonce' );

		$this->render_list(
			'qualifications',
			__( 'Qualifications', 'course-schedule-connector' ),
			__( 'One qualification to a line — a coaching licence, a course completed, a degree. They are printed in the order they are in here.', 'course-schedule-connector' ),
			(array) get_post_meta( $post->ID, TrainerType::META_QUALIFICATIONS, true )
		);

		$this->render_list(
			'hobbies',
			__( 'Interests', 'course-schedule-connector' ),
			__( 'What this trainer does when they are not in the hall.', 'course-schedule-connector' ),
			(array) get_post_meta( $post->ID, TrainerType::META_HOBBIES, true )
		);

		$fact  = (string) get_post_meta( $post->ID, TrainerType::META_FACT, true );
		$motto = (string) get_post_meta( $post->ID, TrainerType::META_MOTTO, true );

		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cscs-trainer-fact"><?php esc_html_e( 'Something worth knowing', 'course-schedule-connector' ); ?></label></th>
				<td>
					<textarea class="large-text" rows="3" id="cscs-trainer-fact" name="cscs_trainer[fact]"><?php echo esc_textarea( $fact ); ?></textarea>
					<p class="description"><?php esc_html_e( 'A sentence or two that makes this a person rather than a name on a timetable.', 'course-schedule-connector' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cscs-trainer-motto"><?php esc_html_e( 'Motto', 'course-schedule-connector' ); ?></label></th>
				<td>
					<input type="text" class="large-text" id="cscs-trainer-motto" name="cscs_trainer[motto]" value="<?php echo esc_attr( $motto ); ?>" />
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Renders one repeating list.
	 *
	 * @param string             $field       Field name.
	 * @param string             $label       Heading.
	 * @param string             $description What the list is for.
	 * @param array<int, string> $values      Current lines.
	 * @return void
	 */
	private function render_list( string $field, string $label, string $description, array $values ): void {
		$values = array_values(
			array_filter(
				array_map( 'strval', $values ),
				static function ( string $line ): bool {
					return '' !== trim( $line );
				}
			)
		);

		// One spare row, always: a list you cannot add to without first finding
		// a button is a list people stop adding to.
		$values[] = '';

		?>
		<div class="cscs-repeat" data-cscs-repeat="<?php echo esc_attr( $field ); ?>">
			<h3><?php echo esc_html( $label ); ?></h3>
			<p class="description"><?php echo esc_html( $description ); ?></p>

			<ul class="cscs-repeat__list">
				<?php foreach ( $values as $line ) : ?>
					<li class="cscs-repeat__row">
						<input
							type="text"
							class="large-text"
							name="cscs_trainer[<?php echo esc_attr( $field ); ?>][]"
							value="<?php echo esc_attr( $line ); ?>"
							aria-label="<?php echo esc_attr( $label ); ?>"
						/>
						<button type="button" class="button-link cscs-repeat__remove"><?php esc_html_e( 'Remove', 'course-schedule-connector' ); ?></button>
					</li>
				<?php endforeach; ?>
			</ul>

			<p><button type="button" class="button cscs-repeat__add"><?php esc_html_e( 'Add a line', 'course-schedule-connector' ); ?></button></p>
		</div>
		<?php
	}

	/**
	 * Lists the courses paired with this trainer.
	 *
	 * @param \WP_Post $post Trainer.
	 * @return void
	 */
	public function render_courses_box( \WP_Post $post ): void {
		$courses = ( new TrainerRepository() )->courses( $post->ID );

		if ( array() === $courses ) {
			?>
			<p class="description">
				<?php esc_html_e( 'No courses name this trainer yet. Pairing is by name: a course is tied to this page when the trainer iSport names on it matches the title above, ignoring spacing and capitals.', 'course-schedule-connector' ); ?>
			</p>
			<?php

			return;
		}

		echo '<ul>';

		foreach ( $courses as $course ) {
			printf(
				'<li><a href="%s">%s</a></li>',
				esc_url( (string) get_edit_post_link( $course->ID ) ),
				esc_html( get_the_title( $course ) )
			);
		}

		echo '</ul>';

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Paired automatically. The trainer’s page shows the same list to visitors.', 'course-schedule-connector' )
		);
	}

	/**
	 * Shows which photograph is in use and where it came from.
	 *
	 * @param \WP_Post $post Trainer.
	 * @return void
	 */
	public function render_photo_box( \WP_Post $post ): void {
		$trainers = new TrainerRepository();
		$own      = (int) get_post_thumbnail_id( $post->ID );
		$synced   = (int) get_post_meta( $post->ID, TrainerType::META_PHOTO_ID, true );
		$showing  = $trainers->photograph( $post->ID );

		if ( 0 !== $showing ) {
			echo wp_get_attachment_image( $showing, 'medium', false, array( 'style' => 'max-width:100%;height:auto' ) );
		}

		if ( 0 !== $own ) {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'This is the featured image set here. It is used instead of the photograph from iSport, and a synchronisation never replaces it.', 'course-schedule-connector' )
			);

			return;
		}

		if ( 0 !== $synced ) {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'Brought in from iSport and stored in the media library. To use another, set a featured image — it wins, and this one stays where it is.', 'course-schedule-connector' )
			);

			return;
		}

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'No photograph yet. iSport has none for this trainer, or none has been retrieved. Set a featured image to use your own.', 'course-schedule-connector' )
		);
	}

	/**
	 * Saves the fields.
	 *
	 * @param int      $post_id Trainer id.
	 * @param \WP_Post $post    Trainer.
	 * @return void
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['cscs_trainer_nonce'] ) || ! isset( $_POST['cscs_trainer'] ) || ! is_array( $_POST['cscs_trainer'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['cscs_trainer_nonce'] ) ), self::NONCE ) ) {
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

		$submitted = wp_unslash( $_POST['cscs_trainer'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every value is sanitised field by field below.

		update_post_meta( $post_id, TrainerType::META_QUALIFICATIONS, TrainerType::sanitise_list( $submitted['qualifications'] ?? array() ) );
		update_post_meta( $post_id, TrainerType::META_HOBBIES, TrainerType::sanitise_list( $submitted['hobbies'] ?? array() ) );
		update_post_meta( $post_id, TrainerType::META_FACT, sanitize_textarea_field( (string) ( $submitted['fact'] ?? '' ) ) );
		update_post_meta( $post_id, TrainerType::META_MOTTO, sanitize_text_field( (string) ( $submitted['motto'] ?? '' ) ) );

		// A trainer written here by hand has no key yet, and without one no
		// course can ever find the page. It is taken from the title once and
		// then left alone: the key is what iSport calls this person, and
		// rewriting it every time somebody tidies the title — adding a degree,
		// fixing a typo — would quietly unpair every course they run.
		if ( '' === (string) get_post_meta( $post_id, TrainerType::META_KEY_NAME, true ) ) {
			$key = TrainerRepository::key( (string) $post->post_title );

			if ( '' !== $key ) {
				update_post_meta( $post_id, TrainerType::META_KEY_NAME, $key );
			}
		}
	}
}
