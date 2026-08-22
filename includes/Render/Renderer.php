<?php
/**
 * Rendering a display set.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

use CSCS\Admin\Screen\DisplaySetsPage;
use CSCS\Data\DisplaySet;
use CSCS\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * The one place that turns a set into markup.
 *
 * The shortcode, the block and the builder module all come through here, which
 * is the point: three ways to put a listing on a page, one definition of what a
 * listing is. A change to the markup is a change everywhere, and a listing
 * cannot look one way in the editor's preview and another on the page.
 *
 * Templates are looked up in the theme first, so a child theme can replace any
 * of them without touching the plugin, and the plugin's own copy is the
 * fallback rather than the law.
 */
final class Renderer {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Whether the stylesheet has been asked for already.
	 *
	 * @var bool
	 */
	private bool $styled = false;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Renders a set by its id.
	 *
	 * @param string $id Set id.
	 * @return string
	 */
	public function render_id( string $id ): string {
		$set = $this->plugin->sets()->find( $id );

		if ( null === $set ) {
			// A page that refers to a set nobody has made yet is a mistake worth
			// seeing while editing and worth hiding from a visitor, who can do
			// nothing about it either way.
			return current_user_can( 'edit_posts' )
				? '<p class="cscs-notice">' . esc_html(
					sprintf(
						/* translators: %s: display set id */
						__( 'There is no display set called "%s".', 'course-schedule-connector' ),
						$id
					)
				) . '</p>'
				: '';
		}

		return $this->render( $set );
	}

	/**
	 * Renders a set.
	 *
	 * @param DisplaySet $set Display set.
	 * @return string
	 */
	public function render( DisplaySet $set ): string {
		$query = new Query( $this->plugin );

		if ( DisplaySet::TYPE_SCHEDULE === $set->type ) {
			$rows  = $query->lessons( $set );
			$times = array();
		} else {
			$rows  = $query->courses( $set );
			$times = $query->course_times( array_column( $rows, 'course_id' ) );
		}

		/**
		 * Filters the rows about to be rendered.
		 *
		 * @since 0.4.0
		 *
		 * @param array<int, array<string, mixed>> $rows Rows.
		 * @param DisplaySet                       $set  Display set.
		 */
		$rows = apply_filters( 'cscs_listing_rows', $rows, $set );

		$listing = new Listing( $set, $rows, $this->columns( $set ), $this->plugin->settings(), $times );

		$this->enqueue();

		return $this->capture( DisplaySet::TYPE_SCHEDULE === $set->type ? 'schedule' : 'courses', $listing );
	}

	/**
	 * Returns the columns of a set, under the headings it gave them.
	 *
	 * @param DisplaySet $set Display set.
	 * @return array<string, string>
	 */
	private function columns( DisplaySet $set ): array {
		$defaults = DisplaySetsPage::column_labels( $set->type );
		$columns  = array();

		foreach ( $set->columns as $column ) {
			$columns[ $column ] = $set->labels[ $column ] ?? ( $defaults[ $column ] ?? $column );
		}

		return $columns;
	}

	/**
	 * Runs a template and returns what it printed.
	 *
	 * @param string  $name    Template name, without extension.
	 * @param Listing $listing Listing.
	 * @return string
	 */
	private function capture( string $name, Listing $listing ): string {
		$file = self::locate( $name );

		if ( '' === $file ) {
			return '';
		}

		ob_start();

		// The variable the template reads. Nothing else is in scope on purpose:
		// a template that needs more than the listing is a sign the decision
		// belongs one layer up.
		include $file;

		return (string) ob_get_clean();
	}

	/**
	 * Finds a template, letting the theme win.
	 *
	 * @param string $name Template name, without extension.
	 * @return string Absolute path, or an empty string when there is none.
	 */
	public static function locate( string $name ): string {
		$name = str_replace( array( '..', '/', '\\' ), '', $name ) . '.php';

		/**
		 * Filters the theme directories searched for plugin templates.
		 *
		 * Both the plugin's own slug and the shorter name the site was built
		 * around are searched, so a child theme can use either.
		 *
		 * @since 0.4.0
		 *
		 * @param array<int, string> $directories Directory names, relative to the theme.
		 */
		$directories = apply_filters( 'cscs_template_directories', array( 'course-schedule-connector', 'jojo-isport' ) );

		$candidates = array();

		foreach ( $directories as $directory ) {
			$candidates[] = trailingslashit( (string) $directory ) . $name;
		}

		$found = locate_template( $candidates );

		if ( '' !== $found ) {
			return $found;
		}

		$own = CSCS_DIR . 'templates/' . $name;

		return is_readable( $own ) ? $own : '';
	}

	/**
	 * Asks for the stylesheet, once, and only where a listing is rendered.
	 *
	 * @return void
	 */
	private function enqueue(): void {
		if ( $this->styled ) {
			return;
		}

		$this->styled = true;

		wp_enqueue_style( 'cscs', CSCS_URL . 'assets/css/cscs.css', array(), CSCS_VERSION );

		$breakpoint = $this->plugin->settings()->get_int( 'table_breakpoint', 320, 1600 );

		// The width at which a table folds is a setting, and a setting cannot
		// live in a static stylesheet: a media query takes a number, not a
		// custom property.
		wp_add_inline_style( 'cscs', self::responsive_css( $breakpoint ) );
	}

	/**
	 * Builds the part of the stylesheet that depends on the breakpoint.
	 *
	 * Below it the table stops being a table: each row becomes a block, and
	 * every cell carries its own heading down the left with the value beside
	 * it, which is the only shape a wide timetable can take on a telephone.
	 *
	 * @param int $breakpoint Width in pixels.
	 * @return string
	 */
	public static function responsive_css( int $breakpoint ): string {
		return sprintf(
			'@media (max-width: %dpx) {
	.cscs-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); clip-path: inset(50%%); white-space: nowrap; }
	.cscs-table, .cscs-table tbody, .cscs-table tr, .cscs-table td { display: block; width: 100%%; }
	.cscs-table tr { margin: 0 0 1rem; border: 1px solid var(--cscs-border, #e0e0e0); border-radius: 4px; overflow: hidden; }
	.cscs-table td { display: grid; grid-template-columns: minmax(6rem, 40%%) 1fr; gap: 0.75rem; border: 0; border-bottom: 1px solid var(--cscs-border, #e0e0e0); padding: 0.5rem 0.75rem; }
	.cscs-table td:last-child { border-bottom: 0; }
	.cscs-table td::before { content: attr(data-label); font-weight: 600; }
	.cscs-table td:empty { display: none; }
}',
			$breakpoint
		);
	}
}
