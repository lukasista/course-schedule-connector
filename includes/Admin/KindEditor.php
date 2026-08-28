<?php
/**
 * The editing screen of a kind of course.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Admin;

use CSCS\Data\KindType;
use CSCS\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Lets an editor pull a kind's description out of one of its courses.
 *
 * A new page starts with the description most of its courses share, which is
 * usually right and is never touched again. This is for the rest: the eleven
 * kinds of twenty-five whose courses word it differently, and the day somebody
 * would rather have the text a particular course carries.
 *
 * Deliberately a button and not a synchronisation. Fetching replaces what is on
 * the page, so it happens when a person asks for it, having been told in the
 * panel what it will do — and nothing about it runs on a schedule.
 */
final class KindEditor {

	/**
	 * The action the button posts to.
	 */
	public const ACTION = 'cscs_kind_description';

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
	 * Hooks the panel and the button.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Adds the panel to a kind's screen.
	 *
	 * @return void
	 */
	public function add_box(): void {
		add_meta_box(
			'cscs-kind-description',
			__( 'Description from a course', 'course-schedule-connector' ),
			array( $this, 'render_box' ),
			KindType::KIND,
			'side',
			'default'
		);
	}

	/**
	 * Loads the one script the panel needs.
	 *
	 * @param string $hook Screen.
	 * @return void
	 */
	public function assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen || KindType::KIND !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'cscs-kind-editor',
			CSCS_URL . 'assets/js/cscs-kind-editor.js',
			array(),
			CSCS_VERSION,
			true
		);
	}

	/**
	 * Renders the panel.
	 *
	 * @param \WP_Post $post Kind page.
	 * @return void
	 */
	public function render_box( \WP_Post $post ): void {
		$courses = $this->plugin->kinds()->courses( $post->ID );

		if ( array() === $courses ) {
			?>
			<p class="description"><?php esc_html_e( 'No course is filed under this kind yet, so there is no description to fetch.', 'course-schedule-connector' ); ?></p>
			<?php

			return;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ACTION,
					'post'   => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '-' . $post->ID
		);

		?>
		<p>
			<label class="screen-reader-text" for="cscs-kind-course"><?php esc_html_e( 'Course to take the description from', 'course-schedule-connector' ); ?></label>
			<select id="cscs-kind-course" style="width:100%">
				<option value="0"><?php esc_html_e( '— the one most courses share —', 'course-schedule-connector' ); ?></option>
				<?php foreach ( $courses as $course_id ) : ?>
					<option value="<?php echo esc_attr( (string) $course_id ); ?>"><?php echo esc_html( (string) get_the_title( $course_id ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<a class="button" id="cscs-kind-import" href="<?php echo esc_url( $url ); ?>" data-course-field="cscs-kind-course">
				<?php esc_html_e( 'Fetch the description', 'course-schedule-connector' ); ?>
			</a>
		</p>
		<p class="description">
			<?php esc_html_e( 'Replaces everything written in the editor with the description iSport holds for that course. Save any wording of your own first — this cannot be undone from here.', 'course-schedule-connector' ); ?>
		</p>
		<?php
	}

	/**
	 * Fetches the description and returns to the editor.
	 *
	 * @return void
	 */
	public function handle(): void {
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checked immediately below, once there is an id to check it against.

		check_admin_referer( self::ACTION . '-' . $post_id );

		if ( 0 === $post_id || ! current_user_can( 'edit_post', $post_id ) || ! Capabilities::can_manage_content() ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'course-schedule-connector' ) );
		}

		$course_id = isset( $_GET['course'] ) ? absint( wp_unslash( $_GET['course'] ) ) : 0;

		$description = 0 === $course_id
			? $this->plugin->kinds()->prevailing_description( $post_id )
			: (string) get_post_meta( $course_id, '_cscs_api_description', true );

		$written = '' !== trim( $description )
			&& in_array( $course_id, array_merge( array( 0 ), $this->plugin->kinds()->courses( $post_id ) ), true )
			&& $this->plugin->kinds()->write_description( $post_id, $description );

		wp_safe_redirect(
			add_query_arg(
				array( 'cscs-description' => $written ? 'written' : 'empty' ),
				(string) get_edit_post_link( $post_id, 'url' )
			)
		);

		exit;
	}
}
