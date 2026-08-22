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
	public function render_id( string $id, ?ListingArgs $args = null, string $base_url = '' ): string {
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

		return $this->render( $set, $args, $base_url );
	}

	/**
	 * Renders a set.
	 *
	 * @param DisplaySet $set Display set.
	 * @return string
	 */
	public function render( DisplaySet $set, ?ListingArgs $args = null, string $base_url = '' ): string {
		$query = new Query( $this->plugin );
		$args  = $args ?? ListingArgs::from_request( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading which page and week a visitor asked for; every value is clamped by ListingArgs and nothing is written.

		if ( DisplaySet::TYPE_SCHEDULE === $set->type ) {
			$rows  = $query->lessons( $set, $args );
			$times = array();
		} else {
			$rows  = $query->courses( $set, $args );
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

		$listing = new Listing( $set, $rows, self::labels_for( $set ), $this->plugin->settings(), $times );

		$listing->place(
			$args,
			$query->total(),
			$this->rooms( $set ),
			'' === $base_url ? $this->current_url() : $base_url
		);

		$this->enqueue();

		return $this->capture( DisplaySet::TYPE_SCHEDULE === $set->type ? 'schedule' : 'courses', $listing );
	}

	/**
	 * Returns the rooms a listing may be narrowed to.
	 *
	 * Only the rooms the set already covers, and only when there is more than
	 * one: a filter offering a single choice is furniture.
	 *
	 * @param DisplaySet $set Display set.
	 * @return array<int, string>
	 */
	private function rooms( DisplaySet $set ): array {
		if ( DisplaySet::TYPE_SCHEDULE !== $set->type ) {
			return array();
		}

		$rooms = array();

		foreach ( $this->plugin->rooms()->decorate( $this->plugin->lessons()->rooms() ) as $room ) {
			if ( $room['hidden'] ) {
				continue;
			}

			if ( array() !== $set->rooms && ! in_array( $room['id'], $set->rooms, true ) ) {
				continue;
			}

			$rooms[ $room['id'] ] = $room['name'];
		}

		return 2 > count( $rooms ) ? array() : $rooms;
	}

	/**
	 * Returns the address of the page being viewed.
	 *
	 * @return string
	 */
	private function current_url(): string {
		$permalink = is_singular() ? (string) get_permalink() : '';

		return '' === $permalink ? home_url( add_query_arg( array() ) ) : $permalink;
	}

	/**
	 * Returns the columns of a set, under the headings it gave them.
	 *
	 * Public and static because a listing is not always built from a set a
	 * person configured: a course's own page makes one on the spot, and it
	 * should carry the same headings as every other.
	 *
	 * @param DisplaySet $set Display set.
	 * @return array<string, string>
	 */
	public static function labels_for( DisplaySet $set ): array {
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
		// A name may carry one directory — `partials/table` — and nothing else:
		// each part is reduced to the characters a template name may hold, so a
		// name that arrived from anywhere but this plugin cannot climb out of
		// the template directory.
		$parts = array_filter(
			array_map(
				static function ( string $part ): string {
					return (string) preg_replace( '/[^a-z0-9\-_]/', '', strtolower( $part ) );
				},
				explode( '/', $name )
			)
		);

		if ( array() === $parts ) {
			return '';
		}

		$name = implode( '/', array_slice( $parts, 0, 2 ) ) . '.php';

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

		// Registered once, elsewhere, because three entrances would otherwise
		// each add the same generated rule after the same file.
		wp_enqueue_style( Assets::HANDLE );
		wp_enqueue_script( Assets::HANDLE );
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
