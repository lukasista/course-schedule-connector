<?php
/**
 * Course list table.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Admin;

use CSCS\Data\CourseRepository;
use CSCS\Data\PostType;

defined( 'ABSPATH' ) || exit;

/**
 * Makes the list of courses answer the questions it is opened with.
 *
 * Which courses are still running, which iSport has stopped offering, which
 * ones show a booking button and which ones somebody has given a contact to.
 * Without those columns the list is a hundred titles that all look alike.
 *
 * The bulk action is here for the same reason: turning the booking button off
 * for a season means doing it to dozens of courses at once, and doing that one
 * course at a time is how it ends up half done.
 */
final class CourseList {

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
		$changed = (int) $_GET['cscs_button_changed'];

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
}
