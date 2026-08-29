<?php
/**
 * WP-CLI commands for display sets.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Cli;

defined( 'ABSPATH' ) || exit;

use CSCS\Data\DisplaySet;
use CSCS\Plugin;

/**
 * Reads the display sets from the command line.
 *
 * Editing one belongs on the screen built for it, where the choices are named
 * and the taxonomies are listed. This is for the other half of the job: seeing
 * what a page is actually configured with when it renders something nobody
 * expected.
 */
final class SetsCommand {

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
	 * Registers the command namespace with WP-CLI.
	 *
	 * @param Plugin $plugin Plugin instance.
	 * @return void
	 */
	public static function register( Plugin $plugin ): void {
		\WP_CLI::add_command( 'cscs sets', new self( $plugin ) );
	}

	/**
	 * Lists the display sets.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs sets list
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function list( array $args, array $assoc_args ): void {
		unset( $args );

		$rows = array();

		foreach ( $this->plugin->sets()->all() as $set ) {
			$rows[] = array(
				'id'        => $set->id,
				'name'      => $set->name,
				'shows'     => $set->type,
				'columns'   => implode( ', ', $set->columns ),
				'range'     => $set->range,
				'rentals'   => $set->include_rentals ? 'yes' : 'no',
				'shortcode' => sprintf(
					'[%s set="%s"]',
					DisplaySet::TYPE_SCHEDULE === $set->type ? 'cscs_schedule' : 'cscs_courses',
					$set->id
				),
			);
		}

		if ( array() === $rows ) {
			\WP_CLI::success( 'No display sets are configured.' );

			return;
		}

		\WP_CLI\Utils\format_items(
			(string) ( $assoc_args['format'] ?? 'table' ),
			$rows,
			array( 'id', 'name', 'shows', 'columns', 'range', 'rentals', 'shortcode' )
		);
	}

	/**
	 * Prints one display set in full.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The set's identifier, as `wp cscs sets list` shows it.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs sets get kurzy-pro-deti
	 *
	 * @param array<int, string> $args Positional arguments.
	 * @return void
	 */
	public function get( array $args ): void {
		$set = $this->plugin->sets()->find( (string) ( $args[0] ?? '' ) );

		if ( null === $set ) {
			\WP_CLI::error( 'There is no set with that id.' );

			return;
		}

		\WP_CLI::log( (string) wp_json_encode( $set->to_array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}
}
