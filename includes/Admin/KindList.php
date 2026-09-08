<?php
/**
 * List of kinds of course.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Admin;

defined( 'ABSPATH' ) || exit;

use CSCS\Data\KindType;
use CSCS\Plugin;

/**
 * Shows what each kind page says, and how many courses it holds.
 *
 * A description is the one thing about a kind page that is worth checking
 * across all of them at once - which ones are still empty, which ones somebody
 * has rewritten - and opening twenty-six pages to find out is not checking.
 *
 * The column is registered as a column and nothing more, because that is all
 * Screen Options needs: WordPress lists every column of a list table there and
 * remembers each person's choice on its own. Somebody who would rather see the
 * titles alone unticks it, and it stays unticked for them.
 */
final class KindList {

	/**
	 * How much of a description the column shows.
	 */
	private const WORDS = 28;

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
	 * Hooks the columns.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'manage_' . KindType::KIND . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . KindType::KIND . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
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

		$columns['cscs_courses']     = __( 'Courses', 'course-schedule-connector' );
		$columns['cscs_description'] = __( 'Description', 'course-schedule-connector' );

		if ( null !== $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * Renders one column.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Kind page id.
	 * @return void
	 */
	public function column( string $column, int $post_id ): void {
		if ( 'cscs_courses' === $column ) {
			echo esc_html( number_format_i18n( count( $this->plugin->kinds()->courses( $post_id ) ) ) );

			return;
		}

		if ( 'cscs_description' !== $column ) {
			return;
		}

		$written = trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ) );

		if ( '' === $written ) {
			echo '<em>' . esc_html__( 'Nothing written yet', 'course-schedule-connector' ) . '</em>';

			return;
		}

		echo esc_html( wp_trim_words( $written, self::WORDS ) );
	}
}
