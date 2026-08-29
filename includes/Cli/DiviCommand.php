<?php
/**
 * WP-CLI diagnostics for the Divi module.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Cli;

defined( 'ABSPATH' ) || exit;

use CSCS\Divi\DisplayModule;
use CSCS\Plugin;

/**
 * Says which link in the Divi chain is broken.
 *
 * A module that never appears, or a field that will not accept a value, looks
 * the same from the outside whatever the cause: the module was not registered,
 * the builder never asked for the script, the script was there but nothing told
 * it what the module is, or it was told and the builder rejected it. Guessing
 * between those cost an evening, so each step now leaves a mark and this reads
 * them back.
 */
final class DiviCommand {

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
		\WP_CLI::add_command( 'cscs divi', new self( $plugin ) );
	}

	/**
	 * Reports on the Divi module.
	 *
	 * Open the Visual Builder once before running this: three of the four steps
	 * only happen while the builder is loading.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs divi status
	 *
	 * @return void
	 */
	public function status(): void {
		$probe = get_option( DisplayModule::PROBE_OPTION, array() );
		$probe = is_array( $probe ) ? $probe : array();

		$rows = array(
			$this->row( 'Divi 5 present', class_exists( '\ET\Builder\Packages\ModuleLibrary\ModuleRegistration' ) ? 'yes' : 'no' ),
			$this->row( 'Module registered as a block', \WP_Block_Type_Registry::get_instance()->is_registered( DisplayModule::NAME ) ? 'yes' : 'no' ),
			$this->row( 'Editor script file', is_readable( CSCS_DIR . 'visual-builder/cscs-divi-display.js' ) ? 'present' : 'MISSING' ),
			$this->row( 'Module metadata file', is_readable( CSCS_DIR . 'divi/cscs-display/module.json' ) ? 'present' : 'MISSING' ),
			$this->row( 'Display sets configured', (string) count( $this->plugin->sets()->all() ) ),
			$this->row( 'Added to Divi dependency tree', $this->when( $probe['registered_at'] ?? 0 ) ),
			$this->row( 'Builder asked for the script', $this->when( $probe['package_at'] ?? 0 ) ),
			$this->row( 'Metadata handed to the script', $this->when( $probe['metadata_at'] ?? 0 ) ),
			$this->row( 'Script handle was missing', $this->when( $probe['script_missing_at'] ?? 0 ) ),
			$this->row( 'Sets offered in the module', isset( $probe['options'] ) ? (string) $probe['options'] : '—' ),
		);

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'step', 'state' ) );

		if ( 0 === (int) ( $probe['package_at'] ?? 0 ) ) {
			\WP_CLI::log( 'Nothing recorded from the builder yet. Open a page in the Visual Builder, then run this again.' );
		}
	}

	/**
	 * Builds one row.
	 *
	 * @param string $step  Step name.
	 * @param string $state What is known about it.
	 * @return array<string, string>
	 */
	private function row( string $step, string $state ): array {
		return array(
			'step'  => $step,
			'state' => $state,
		);
	}

	/**
	 * Renders when something last happened.
	 *
	 * @param int $stamp Timestamp, or 0.
	 * @return string
	 */
	private function when( int $stamp ): string {
		if ( 0 === $stamp ) {
			return 'never';
		}

		return sprintf( '%s (%s ago)', wp_date( 'H:i:s', $stamp ), human_time_diff( $stamp ) );
	}
}
