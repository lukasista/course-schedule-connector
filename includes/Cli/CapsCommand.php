<?php
/**
 * WP-CLI commands for the plugin's capabilities.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Cli;

defined( 'ABSPATH' ) || exit;

use CSCS\Admin\Capabilities;
use CSCS\Plugin;

/**
 * Says who may do what, and puts it right when the answer is nobody.
 *
 * This exists because of a failure that gave no sign of itself: the admin menu
 * hangs on `cscs_manage_content`, capabilities were granted only on activation,
 * and a site running the plugin since before that capability existed had no
 * menu and no error to explain it. The plugin now grants them by itself, and
 * this is how to look.
 */
final class CapsCommand {

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
		\WP_CLI::add_command( 'cscs caps', new self( $plugin ) );
	}

	/**
	 * Lists which roles hold the plugin's capabilities.
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
	 *     wp cscs caps list
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function list( array $args, array $assoc_args ): void {
		unset( $args );

		$rows = array();

		foreach ( wp_roles()->roles as $slug => $role ) {
			$caps = is_array( $role['capabilities'] ?? null ) ? $role['capabilities'] : array();

			$rows[] = array(
				'role'    => (string) $slug,
				'name'    => (string) ( $role['name'] ?? '' ),
				'content' => empty( $caps[ Capabilities::MANAGE_CONTENT ] ) ? 'no' : 'yes',
				'design'  => empty( $caps[ Capabilities::MANAGE_DESIGN ] ) ? 'no' : 'yes',
			);
		}

		\WP_CLI::log(
			sprintf(
				'Capabilities installed: version %d (the plugin expects %d).',
				(int) get_option( Capabilities::VERSION_OPTION, 0 ),
				Capabilities::VERSION
			)
		);

		\WP_CLI\Utils\format_items(
			(string) ( $assoc_args['format'] ?? 'table' ),
			$rows,
			array( 'role', 'name', 'content', 'design' )
		);
	}

	/**
	 * Grants the capabilities again, whatever state they are in.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs caps install
	 *
	 * @return void
	 */
	public function install(): void {
		Capabilities::install();

		\WP_CLI::success( 'Capabilities granted. Administrators and editors can manage content; settings stay with administrators.' );
	}
}
