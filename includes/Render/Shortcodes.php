<?php
/**
 * Shortcodes.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

defined( 'ABSPATH' ) || exit;

use CSCS\Data\DisplaySet;
use CSCS\Plugin;

/**
 * Puts a listing on a page from the classic editor, a widget or a template.
 *
 * Two names rather than one, `[cscs_courses]` and `[cscs_schedule]`, because a
 * person writing one on a page knows which of the two they want and should not
 * have to remember a parameter for it. Both take the same single attribute: the
 * set. Everything else the set decides, which is the whole arrangement — the
 * page says what to show, and the set says how.
 */
final class Shortcodes {

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
	 * Registers both shortcodes.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'cscs_courses', array( $this, 'courses' ) );
		add_shortcode( 'cscs_schedule', array( $this, 'schedule' ) );
	}

	/**
	 * Renders a listing of courses.
	 *
	 * @param array<string, string>|string $atts Attributes.
	 * @return string
	 */
	public function courses( $atts ): string {
		return $this->render( $atts, DisplaySet::TYPE_COURSES );
	}

	/**
	 * Renders a timetable of classes.
	 *
	 * @param array<string, string>|string $atts Attributes.
	 * @return string
	 */
	public function schedule( $atts ): string {
		return $this->render( $atts, DisplaySet::TYPE_SCHEDULE );
	}

	/**
	 * Renders whichever set was named.
	 *
	 * @param array<string, string>|string $atts Attributes.
	 * @param string                       $type The kind of listing this shortcode is for.
	 * @return string
	 */
	private function render( $atts, string $type ): string {
		$atts = shortcode_atts(
			array( 'set' => '' ),
			is_array( $atts ) ? $atts : array(),
			'cscs_' . $type
		);

		$id = sanitize_key( (string) $atts['set'] );

		if ( '' === $id ) {
			return $this->fallback( $type );
		}

		return $this->plugin->renderer()->render_id( $id );
	}

	/**
	 * Renders the only set of the right kind, when a shortcode names none.
	 *
	 * A page written as `[cscs_courses]` with one set of courses configured
	 * plainly means that one. With several, guessing would be worse than
	 * saying so.
	 *
	 * @param string $type The kind of listing wanted.
	 * @return string
	 */
	private function fallback( string $type ): string {
		$candidates = array();

		foreach ( $this->plugin->sets()->all() as $set ) {
			if ( $set->type === $type ) {
				$candidates[] = $set;
			}
		}

		if ( 1 === count( $candidates ) ) {
			return $this->plugin->renderer()->render( $candidates[0] );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}

		return '<p class="cscs-notice">' . esc_html__( 'This shortcode needs a display set, for example [cscs_courses set="kurzy-pro-deti"].', 'course-schedule-connector' ) . '</p>';
	}
}
