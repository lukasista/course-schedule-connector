<?php
/**
 * The editing screen of a kind of course.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Admin;

defined( 'ABSPATH' ) || exit;

use CSCS\Data\Audience;
use CSCS\Data\KindType;
use CSCS\Data\PostType;
use CSCS\Plugin;

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
	 * The nonce the courses panel is saved with.
	 */
	public const NONCE = 'cscs_kind_courses';

	/**
	 * The action the copy link posts to.
	 */
	public const DUPLICATE = 'cscs_kind_duplicate';

	/**
	 * How many characters of a course title the select shows.
	 */
	private const OPTION_LENGTH = 26;

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
		add_action( 'save_post_' . KindType::KIND, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_post_' . self::DUPLICATE, array( $this, 'duplicate' ) );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Says what the fetch button did.
	 *
	 * Without this the button was silent: a kind whose courses carry no
	 * description in iSport looked exactly like a button that does nothing,
	 * which is what it was reported as.
	 *
	 * @return void
	 */
	public function notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading a redirect marker to phrase a message, no action taken.
		$outcome = isset( $_GET['cscs-description'] ) ? sanitize_key( wp_unslash( $_GET['cscs-description'] ) ) : '';

		if ( ! in_array( $outcome, array( 'written', 'empty' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen || KindType::KIND !== $screen->post_type ) {
			return;
		}

		$message = 'written' === $outcome
			? __( 'The description was taken from iSport and now stands in the editor.', 'course-schedule-connector' )
			: __( 'iSport holds no description for that course, so nothing was changed. Descriptions are written in iSport itself; a course with an empty one there has nothing to fetch.', 'course-schedule-connector' );

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			'written' === $outcome ? 'success' : 'warning',
			esc_html( $message )
		);
	}

	/**
	 * Adds "Copy" to a kind page's row in the list.
	 *
	 * Because the answer to "one card as two" is a second page, and a second
	 * page written from nothing means retyping a description somebody already
	 * wrote and finding the photograph again.
	 *
	 * @param array<string, string> $actions Row actions.
	 * @param \WP_Post              $post    Post.
	 * @return array<string, string>
	 */
	public function row_actions( array $actions, \WP_Post $post ): array {
		if ( KindType::KIND !== $post->post_type || ! Capabilities::can_manage_content() ) {
			return $actions;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::DUPLICATE,
					'post'   => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			self::DUPLICATE . '-' . $post->ID
		);

		$actions['cscs-duplicate'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Copy', 'course-schedule-connector' )
		);

		return $actions;
	}

	/**
	 * Copies a kind page and opens the copy.
	 *
	 * The words, the picture and the excerpt come across; the copy is a draft,
	 * and it is pointed at the same kind as the original by name rather than by
	 * inheriting the pairing, which belongs to one page and not to two.
	 *
	 * @return void
	 */
	public function duplicate(): void {
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checked immediately below, once there is an id to check it against.

		check_admin_referer( self::DUPLICATE . '-' . $post_id );

		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || KindType::KIND !== $post->post_type || ! Capabilities::can_manage_content() ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'course-schedule-connector' ) );
		}

		$term = $this->plugin->kinds()->term_for( $post_id );
		$copy = wp_insert_post(
			array(
				'post_type'    => KindType::KIND,
				'post_status'  => 'draft',
				/* translators: %s: the name of the page being copied. */
				'post_title'   => sprintf( __( '%s (copy)', 'course-schedule-connector' ), $post->post_title ),
				'post_content' => $post->post_content,
				'post_excerpt' => $post->post_excerpt,
			),
			true
		);

		if ( $copy instanceof \WP_Error || 0 === (int) $copy ) {
			wp_die( esc_html__( 'The page could not be copied.', 'course-schedule-connector' ) );
		}

		$copy = (int) $copy;

		// Deliberately not the pairing key. That key says "this is the page the
		// synchronisation made for this kind", and two pages claiming it is one
		// page too many; the copy says which kind it is about outright, which
		// is what a page with a name of its own has to do anyway.
		if ( $term instanceof \WP_Term ) {
			update_post_meta( $copy, KindType::META_TERM, $term->term_id );
		}

		$filter = $this->plugin->kinds()->filter( $post_id );

		if ( array() !== $filter ) {
			update_post_meta( $copy, KindType::META_FILTER, $filter );
		}

		$thumbnail = (int) get_post_thumbnail_id( $post_id );

		if ( 0 !== $thumbnail ) {
			set_post_thumbnail( $copy, $thumbnail );
		}

		wp_safe_redirect( (string) get_edit_post_link( $copy, 'url' ) );

		exit;
	}

	/**
	 * Adds the panel to a kind's screen.
	 *
	 * @return void
	 */
	public function add_box(): void {
		add_meta_box(
			'cscs-kind-courses',
			__( 'Which courses this page is about', 'course-schedule-connector' ),
			array( $this, 'render_courses_box' ),
			KindType::KIND,
			'normal',
			'high'
		);

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
	 * Renders the panel that says which courses this page is about.
	 *
	 * The answer a theme builder template cannot hold. A template is one design
	 * for every page of a type, so a filter set on the module inside it is set
	 * for all of them at once — "girls" on the page for the boys. Here it is
	 * per page, which is the only place it can differ: one template then serves
	 * "Gymnastika", "Gymnastika dívky" and "Gymnastika kluci" and each prints
	 * its own timetable.
	 *
	 * @param \WP_Post $post Kind page.
	 * @return void
	 */
	public function render_courses_box( \WP_Post $post ): void {
		$terms  = get_terms(
			array(
				'taxonomy'   => PostType::KIND,
				'hide_empty' => false,
			)
		);
		$terms  = is_array( $terms ) ? $terms : array();
		$term   = $this->plugin->kinds()->term_for( $post->ID );
		$chosen = (int) get_post_meta( $post->ID, KindType::META_TERM, true );
		$filter = $this->plugin->kinds()->filter( $post->ID );
		$read   = static function ( string $key ) use ( $filter ): string {
			return (string) ( $filter[ $key ] ?? '' );
		};

		wp_nonce_field( self::NONCE, 'cscs_kind_nonce' );

		?>
		<p class="description" style="max-width:48em">
			<?php esc_html_e( 'Everything below decides which courses the timetable on this page lists — and the blocks and modules on it, wherever they are used, including a design made once for every page of this type. Leave it all alone and the page shows every course of its kind, which is what a page made by the synchronisation does.', 'course-schedule-connector' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cscs-kind-term"><?php esc_html_e( 'Kind of course', 'course-schedule-connector' ); ?></label></th>
				<td>
					<select id="cscs-kind-term" name="cscs_kind[term]">
						<option value="0">
							<?php
							echo esc_html(
								$term instanceof \WP_Term && 0 === $chosen
									? sprintf(
										/* translators: %s: the name of a kind of course. */
										__( '— the one this page is named after (%s) —', 'course-schedule-connector' ),
										$term->name
									)
									: __( '— the one this page is named after —', 'course-schedule-connector' )
							);
							?>
						</option>
						<?php foreach ( $terms as $option ) : ?>
							<option value="<?php echo esc_attr( (string) $option->term_id ); ?>" <?php selected( $chosen, (int) $option->term_id ); ?>>
								<?php echo esc_html( $option->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Say it outright when the page is called something the kind is not — a page named “Gymnastika dívky” pairs with no kind by itself.', 'course-schedule-connector' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cscs-kind-gender"><?php esc_html_e( 'Who the course is for', 'course-schedule-connector' ); ?></label></th>
				<td>
					<select id="cscs-kind-gender" name="cscs_kind[filterGenders]">
						<option value=""><?php esc_html_e( 'Everybody', 'course-schedule-connector' ); ?></option>
						<?php foreach ( Audience::genders() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( (string) $key ); ?>" <?php selected( $read( 'filterGenders' ), (string) $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'A course whose name says nothing about this is never shown by a setting that asks.', 'course-schedule-connector' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cscs-kind-level"><?php esc_html_e( 'At what level', 'course-schedule-connector' ); ?></label></th>
				<td>
					<select id="cscs-kind-level" name="cscs_kind[filterLevels]">
						<option value=""><?php esc_html_e( 'Every level', 'course-schedule-connector' ); ?></option>
						<?php foreach ( Audience::levels() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( (string) $key ); ?>" <?php selected( $read( 'filterLevels' ), (string) $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Age', 'course-schedule-connector' ); ?></th>
				<td>
					<label for="cscs-kind-age-min"><?php esc_html_e( 'from', 'course-schedule-connector' ); ?></label>
					<input type="text" size="4" id="cscs-kind-age-min" name="cscs_kind[filterAgeMin]" value="<?php echo esc_attr( $read( 'filterAgeMin' ) ); ?>" />
					<label for="cscs-kind-age-max"><?php esc_html_e( 'to', 'course-schedule-connector' ); ?></label>
					<input type="text" size="4" id="cscs-kind-age-max" name="cscs_kind[filterAgeMax]" value="<?php echo esc_attr( $read( 'filterAgeMax' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Leaves out courses that finish below the floor or start above the ceiling. Either may be left empty.', 'course-schedule-connector' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cscs-kind-sort"><?php esc_html_e( 'Order by', 'course-schedule-connector' ); ?></label></th>
				<td>
					<select id="cscs-kind-sort" name="cscs_kind[filterSort]">
						<?php foreach ( self::sorts() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( (string) $key ); ?>" <?php selected( $read( 'filterSort' ), (string) $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<select id="cscs-kind-order" name="cscs_kind[filterOrder]">
						<option value="asc" <?php selected( $read( 'filterOrder' ), 'asc' ); ?>><?php esc_html_e( 'Ascending', 'course-schedule-connector' ); ?></option>
						<option value="desc" <?php selected( $read( 'filterOrder' ), 'desc' ); ?>><?php esc_html_e( 'Descending', 'course-schedule-connector' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cscs-kind-limit"><?php esc_html_e( 'At most', 'course-schedule-connector' ); ?></label></th>
				<td>
					<input type="number" min="0" step="1" id="cscs-kind-limit" name="cscs_kind[filterLimit]" value="<?php echo esc_attr( $read( 'filterLimit' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'How many rows to print. Empty or zero means all of them.', 'course-schedule-connector' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Returns what a timetable may be ordered by.
	 *
	 * @return array<string, string>
	 */
	private static function sorts(): array {
		return array(
			''       => __( 'As they are filed', 'course-schedule-connector' ),
			'name'   => __( 'Name', 'course-schedule-connector' ),
			'start'  => __( 'When it starts', 'course-schedule-connector' ),
			'price'  => __( 'Price', 'course-schedule-connector' ),
			'places' => __( 'Places free', 'course-schedule-connector' ),
		);
	}

	/**
	 * Saves the panel.
	 *
	 * @param int      $post_id Kind page id.
	 * @param \WP_Post $post    Kind page.
	 * @return void
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['cscs_kind_nonce'], $_POST['cscs_kind'] ) || ! is_array( $_POST['cscs_kind'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['cscs_kind_nonce'] ) ), self::NONCE ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || 'auto-draft' === $post->post_status ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) || ! Capabilities::can_manage_content() ) {
			return;
		}

		$submitted = wp_unslash( $_POST['cscs_kind'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every value is sanitised field by field below.
		$term      = absint( $submitted['term'] ?? 0 );

		if ( 0 === $term || ! get_term( $term, PostType::KIND ) instanceof \WP_Term ) {
			delete_post_meta( $post_id, KindType::META_TERM );
		} else {
			update_post_meta( $post_id, KindType::META_TERM, $term );
		}

		$filter = array(
			'filterGenders' => self::one_of( (string) ( $submitted['filterGenders'] ?? '' ), Audience::gender_keys() ),
			'filterLevels'  => self::one_of( (string) ( $submitted['filterLevels'] ?? '' ), Audience::level_keys() ),
			'filterAgeMin'  => self::age( (string) ( $submitted['filterAgeMin'] ?? '' ) ),
			'filterAgeMax'  => self::age( (string) ( $submitted['filterAgeMax'] ?? '' ) ),
			'filterSort'    => self::one_of( (string) ( $submitted['filterSort'] ?? '' ), array_keys( self::sorts() ) ),
			'filterOrder'   => 'desc' === ( $submitted['filterOrder'] ?? '' ) ? 'desc' : '',
			'filterLimit'   => (string) max( 0, (int) ( $submitted['filterLimit'] ?? 0 ) ),
		);

		if ( '0' === $filter['filterLimit'] ) {
			$filter['filterLimit'] = '';
		}

		// A page that asks nothing stores nothing, so that "no filter" and "a
		// filter of seven empty strings" are not two states to reason about.
		$filter = array_filter( $filter );

		if ( array() === $filter ) {
			delete_post_meta( $post_id, KindType::META_FILTER );

			return;
		}

		update_post_meta( $post_id, KindType::META_FILTER, $filter );
	}

	/**
	 * Returns the value when it is one of the allowed ones, or nothing.
	 *
	 * @param string            $value   Submitted value.
	 * @param array<int, string> $allowed Allowed values.
	 * @return string
	 */
	private static function one_of( string $value, array $allowed ): string {
		$value = sanitize_key( $value );

		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Reduces a submitted age to what is stored, or to nothing.
	 *
	 * A comma is what a Czech types and a full stop is what a number is.
	 *
	 * @param string $value Submitted age.
	 * @return string
	 */
	private static function age( string $value ): string {
		$value = str_replace( ',', '.', trim( $value ) );

		if ( ! is_numeric( $value ) || (float) $value < 0 ) {
			return '';
		}

		return rtrim( rtrim( number_format( (float) $value, 1, '.', '' ), '0' ), '.' );
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

		wp_enqueue_style(
			'cscs-admin',
			CSCS_URL . 'assets/css/cscs-admin.css',
			array(),
			CSCS_VERSION
		);

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
			<select id="cscs-kind-course" class="cscs-full-width">
				<option value="0"><?php esc_html_e( '— shared by most courses —', 'course-schedule-connector' ); ?></option>
				<?php foreach ( $courses as $course_id ) : ?>
					<?php $title = (string) get_the_title( $course_id ); ?>
					<option value="<?php echo esc_attr( (string) $course_id ); ?>" title="<?php echo esc_attr( $title ); ?>"><?php echo esc_html( self::shorten( $title ) ); ?></option>
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
	 * Shortens a course title to something a narrow control can hold.
	 *
	 * A select is as wide as its longest option, and `width: 100%` does not
	 * change that: in a column that sizes itself to its contents - which is
	 * what a meta box beside the editor is - the percentage resolves against a
	 * width the select itself has just pushed out. Measured on this gym's
	 * courses the control came out 370 pixels wide in a 250 pixel column and
	 * hung over the edge of the screen.
	 *
	 * Nothing is lost by cutting the text: a course title opens with the number
	 * that identifies it, the open list is drawn at whatever width it needs,
	 * and the whole title is on the option as a tooltip.
	 *
	 * @param string $title Course title.
	 * @return string
	 */
	private static function shorten( string $title ): string {
		$title  = trim( $title );
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $title, 'UTF-8' ) : strlen( $title );

		if ( $length <= self::OPTION_LENGTH ) {
			return $title;
		}

		$cut = function_exists( 'mb_substr' )
			? mb_substr( $title, 0, self::OPTION_LENGTH - 1, 'UTF-8' )
			: substr( $title, 0, self::OPTION_LENGTH - 1 );

		return rtrim( (string) $cut ) . '…';
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
