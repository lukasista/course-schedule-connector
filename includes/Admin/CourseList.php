<?php
/**
 * Course list table.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Admin;

defined( 'ABSPATH' ) || exit;

use CSCS\Data\Audience;
use CSCS\Data\CourseRepository;
use CSCS\Data\PostType;

/**
 * Makes the list of courses answer the questions it is opened with.
 *
 * Which courses are still running, which iSport has stopped offering, which
 * ones show a booking button, which ones somebody has given a contact to, and
 * who each one is for. Without those columns the list is a hundred titles
 * that all look alike.
 *
 * The bulk action is here for the same reason: turning the booking button off
 * for a season means doing it to dozens of courses at once, and doing that one
 * course at a time is how it ends up half done.
 *
 * The filters above the list ask the same thing from the other direction:
 * rather than reading down every row, someone can go straight to what one
 * room, trainer, kind, tag or category holds, to courses of one gender, or
 * to courses in one state.
 */
final class CourseList {

	/**
	 * Query var the status dropdown submits. Not a taxonomy, so unlike the
	 * others it needs a name of its own rather than reading one off a
	 * PostType constant.
	 */
	private const STATUS_FILTER = 'cscs_status';

	/**
	 * Query var the gender dropdown submits. Gender lives in meta as well
	 * (see {@see CourseRepository::META_GENDER}), so it needs the same kind
	 * of name of its own as {@see self::STATUS_FILTER}.
	 */
	private const GENDER_FILTER = 'cscs_gender';

	/**
	 * Hooks the columns and the bulk actions.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'manage_' . PostType::COURSE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . PostType::COURSE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'bulk_actions-edit-' . PostType::COURSE, array( $this, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . PostType::COURSE, array( $this, 'handle_bulk' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'restrict_manage_posts', array( $this, 'filters' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_meta_filters' ) );
	}

	/**
	 * Adds the columns.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$date = $columns['date'] ?? null;

		unset( $columns['date'] );

		$columns['cscs_state']   = __( 'State', 'course-schedule-connector' );
		$columns['cscs_gender']  = __( 'Gender', 'course-schedule-connector' );
		$columns['cscs_button']  = __( 'Booking button', 'course-schedule-connector' );
		$columns['cscs_contact'] = __( 'Contact', 'course-schedule-connector' );

		if ( null !== $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * Renders one column.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Course id.
	 * @return void
	 */
	public function column( string $column, int $post_id ): void {
		if ( 'cscs_state' === $column ) {
			$status = (string) get_post_meta( $post_id, CourseRepository::META_STATUS, true );
			$labels = array(
				CourseRepository::STATUS_RUNNING  => __( 'Running', 'course-schedule-connector' ),
				CourseRepository::STATUS_FINISHED => __( 'Finished', 'course-schedule-connector' ),
				CourseRepository::STATUS_ARCHIVED => __( 'No longer offered by iSport', 'course-schedule-connector' ),
			);

			echo esc_html( $labels[ $status ] ?? '—' );

			return;
		}

		if ( 'cscs_gender' === $column ) {
			$gender = (string) get_post_meta( $post_id, CourseRepository::META_GENDER, true );

			echo esc_html( Audience::genders()[ $gender ] ?? '—' );

			return;
		}

		if ( 'cscs_button' === $column ) {
			$button = (string) get_post_meta( $post_id, CourseRepository::META_BUTTON, true );
			$labels = array(
				'always' => __( 'Always shown', 'course-schedule-connector' ),
				'never'  => __( 'Never shown', 'course-schedule-connector' ),
			);

			echo esc_html( $labels[ $button ] ?? __( 'As the settings say', 'course-schedule-connector' ) );

			return;
		}

		if ( 'cscs_contact' !== $column ) {
			return;
		}

		$name  = (string) get_post_meta( $post_id, CourseRepository::META_CONTACT_NAME, true );
		$email = (string) get_post_meta( $post_id, CourseRepository::META_CONTACT_EMAIL, true );
		$phone = (string) get_post_meta( $post_id, CourseRepository::META_CONTACT_PHONE, true );

		$parts = array_filter( array( $name, $email, $phone ) );

		echo esc_html( array() === $parts ? '—' : implode( ', ', $parts ) );
	}

	/**
	 * Adds the bulk actions.
	 *
	 * @param array<string, string> $actions Existing actions.
	 * @return array<string, string>
	 */
	public function bulk_actions( array $actions ): array {
		if ( ! Capabilities::can_manage_content() ) {
			return $actions;
		}

		$actions['cscs_button_always']  = __( 'Booking button: always show', 'course-schedule-connector' );
		$actions['cscs_button_never']   = __( 'Booking button: never show', 'course-schedule-connector' );
		$actions['cscs_button_default'] = __( 'Booking button: as the settings say', 'course-schedule-connector' );

		return $actions;
	}

	/**
	 * Applies a bulk action.
	 *
	 * @param string          $url    Redirect target.
	 * @param string          $action Action chosen.
	 * @param array<int, int> $ids    Selected course ids.
	 * @return string
	 */
	public function handle_bulk( string $url, string $action, array $ids ): string {
		$values = array(
			'cscs_button_always'  => 'always',
			'cscs_button_never'   => 'never',
			'cscs_button_default' => 'default',
		);

		if ( ! isset( $values[ $action ] ) ) {
			return $url;
		}

		if ( ! Capabilities::can_manage_content() ) {
			return $url;
		}

		$changed = 0;

		foreach ( $ids as $post_id ) {
			$post_id = (int) $post_id;

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			update_post_meta( $post_id, CourseRepository::META_BUTTON, $values[ $action ] );
			++$changed;
		}

		return add_query_arg( 'cscs_button_changed', $changed, $url );
	}

	/**
	 * Says what the bulk action did.
	 *
	 * @return void
	 */
	public function notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen instanceof \WP_Screen || PostType::COURSE !== $screen->post_type ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading a count out of the URL to say what already happened.
		if ( ! isset( $_GET['cscs_button_changed'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See above.
		$changed = absint( wp_unslash( $_GET['cscs_button_changed'] ) );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of courses */
					_n( 'Booking button changed on %d course.', 'Booking button changed on %d courses.', $changed, 'course-schedule-connector' ),
					$changed
				)
			)
		);
	}

	/**
	 * The taxonomies a course carries that are worth their own filter above
	 * the list, each paired with the label its "show everything" option
	 * gets. Activity is left out on purpose: it is the whole course name as
	 * the API sends it, one term per course, so a dropdown of a hundred
	 * one-off values would not narrow anything down.
	 *
	 * @return array<string, string>
	 */
	private function taxonomy_filters(): array {
		return array(
			PostType::ROOM     => __( 'All rooms', 'course-schedule-connector' ),
			PostType::TRAINER  => __( 'All trainers', 'course-schedule-connector' ),
			PostType::KIND     => __( 'All kinds of course', 'course-schedule-connector' ),
			PostType::TAG      => __( 'All tags', 'course-schedule-connector' ),
			PostType::CATEGORY => __( 'All categories', 'course-schedule-connector' ),
		);
	}

	/**
	 * The facts worth their own filter above the list that live in post meta
	 * rather than in a taxonomy term, so nothing reads a URL value back into
	 * the query for them the way {@see self::taxonomy_filters()}'s entries
	 * get read automatically - that is what {@see self::apply_meta_filters()}
	 * is for. Keyed by the query var each one's dropdown submits.
	 *
	 * @return array<string, array{meta_key: string, options: array<string, string>, all: string}>
	 */
	private function meta_filters(): array {
		return array(
			self::STATUS_FILTER => array(
				'meta_key' => CourseRepository::META_STATUS,
				'options'  => array(
					CourseRepository::STATUS_RUNNING  => __( 'Running', 'course-schedule-connector' ),
					CourseRepository::STATUS_FINISHED => __( 'Finished', 'course-schedule-connector' ),
					CourseRepository::STATUS_ARCHIVED => __( 'No longer offered by iSport', 'course-schedule-connector' ),
				),
				'all'      => __( 'All statuses', 'course-schedule-connector' ),
			),
			self::GENDER_FILTER => array(
				'meta_key' => CourseRepository::META_GENDER,
				'options'  => Audience::genders(),
				'all'      => __( 'All genders', 'course-schedule-connector' ),
			),
		);
	}

	/**
	 * Renders the filter dropdowns above the list.
	 *
	 * A dropdown only needs to be named after its taxonomy: that name is
	 * already the query var WordPress reads a term filter from on every list
	 * table, the same one a plain link to `edit.php?cscs_room=...` would use,
	 * so nothing else has to apply it. Status and gender are the exception,
	 * because those two live in meta rather than in a taxonomy term - those
	 * are read back in {@see self::apply_meta_filters()}.
	 *
	 * @param string $post_type Post type of the screen being filtered.
	 * @return void
	 */
	public function filters( string $post_type ): void {
		if ( PostType::COURSE !== $post_type ) {
			return;
		}

		foreach ( $this->taxonomy_filters() as $taxonomy => $show_all ) {
			$taxonomy_object = get_taxonomy( $taxonomy );

			if ( ! $taxonomy_object instanceof \WP_Taxonomy ) {
				continue;
			}

			wp_dropdown_categories(
				array(
					'taxonomy'        => $taxonomy,
					'name'            => $taxonomy,
					'id'              => $taxonomy,
					'value_field'     => 'slug',
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the filter's own current value back, nothing is written.
					'selected'        => isset( $_GET[ $taxonomy ] ) ? sanitize_title( wp_unslash( $_GET[ $taxonomy ] ) ) : '',
					'show_option_all' => $show_all,
					'hide_empty'      => false,
					'hierarchical'    => $taxonomy_object->hierarchical,
					'orderby'         => 'name',
				)
			);
		}

		foreach ( $this->meta_filters() as $query_var => $filter ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the filter's own current value back, nothing is written.
			$selected = isset( $_GET[ $query_var ] ) ? sanitize_key( wp_unslash( $_GET[ $query_var ] ) ) : '';

			echo '<select name="' . esc_attr( $query_var ) . '" id="' . esc_attr( $query_var ) . '">';
			echo '<option value="">' . esc_html( $filter['all'] ) . '</option>';

			foreach ( $filter['options'] as $value => $label ) {
				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr( $value ),
					selected( $selected, $value, false ),
					esc_html( $label )
				);
			}

			echo '</select>';
		}
	}

	/**
	 * Applies the status and gender dropdowns - the filters WordPress cannot
	 * already read off a query var on its own, because those two live in
	 * meta rather than in a taxonomy term.
	 *
	 * Every active one is collected into a single combined `meta_query`
	 * before it is set on the query exactly once: {@see self::STATUS_FILTER}
	 * and {@see self::GENDER_FILTER} both hang off this same `pre_get_posts`
	 * callback for that reason - two separate `$query->set( 'meta_query', ... )`
	 * calls would have the second replace the first rather than add to it,
	 * silently dropping whichever filter ran first.
	 *
	 * @param \WP_Query $query Main query being built.
	 * @return void
	 */
	public function apply_meta_filters( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || PostType::COURSE !== $query->get( 'post_type' ) ) {
			return;
		}

		$clauses = array();

		foreach ( $this->meta_filters() as $query_var => $filter ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading a filter choice, nothing is written.
			$value = isset( $_GET[ $query_var ] ) ? sanitize_key( wp_unslash( $_GET[ $query_var ] ) ) : '';

			if ( '' === $value || ! isset( $filter['options'][ $value ] ) ) {
				continue;
			}

			$clauses[] = array(
				'key'   => $filter['meta_key'],
				'value' => $value,
			);
		}

		if ( array() === $clauses ) {
			return;
		}

		if ( count( $clauses ) > 1 ) {
			$clauses['relation'] = 'AND';
		}

		$query->set( 'meta_query', $clauses );
	}
}
