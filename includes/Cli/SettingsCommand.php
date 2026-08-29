<?php
/**
 * WP-CLI commands for plugin settings.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Cli;

defined( 'ABSPATH' ) || exit;

use CSCS\Plugin;
use CSCS\Settings;

/**
 * Reads and writes settings from the command line.
 *
 * The admin screens arrive in a later phase, but a setting still has to be
 * changeable before then — and generic option editing is the wrong tool for it,
 * because it neither knows what a key expects nor refuses a base URL pointing
 * inside the network. Everything written here goes through the same validation
 * the admin screens will use.
 */
final class SettingsCommand {

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
		\WP_CLI::add_command( 'cscs settings', new self( $plugin ) );
	}

	/**
	 * Lists every setting and its current value.
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
	 *     wp cscs settings list
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function list( array $args, array $assoc_args ): void {
		$settings = $this->plugin->settings();
		$defaults = Settings::defaults();
		$rows     = array();

		foreach ( $defaults as $key => $default_value ) {
			$value = $settings->get( $key );

			$rows[] = array(
				'setting' => $key,
				'value'   => $this->render( $value ),
				'default' => $this->render( $default_value ),
			);
		}

		\WP_CLI\Utils\format_items(
			(string) ( $assoc_args['format'] ?? 'table' ),
			$rows,
			array( 'setting', 'value', 'default' )
		);
	}

	/**
	 * Prints one setting.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Setting name.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs settings get api_base_url
	 *
	 * @param array<int, string> $args Positional arguments.
	 * @return void
	 */
	public function get( array $args ): void {
		$key = (string) ( $args[0] ?? '' );

		if ( ! array_key_exists( $key, Settings::defaults() ) ) {
			\WP_CLI::error( sprintf( 'Unknown setting "%s". Run "wp cscs settings list" to see them all.', $key ) );

			return;
		}

		\WP_CLI::log( $this->render( $this->plugin->settings()->get( $key ) ) );
	}

	/**
	 * Writes one setting.
	 *
	 * The value is validated the way the admin screens will validate it. A base
	 * URL that is not https, carries credentials, or resolves into private or
	 * loopback space is refused rather than stored.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Setting name.
	 *
	 * <value>
	 * : New value. Use an empty string to clear a text setting.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cscs settings set api_base_url https://jojogym.isportsystem.cz
	 *     wp cscs settings set semester_from 2026-09-11
	 *     wp cscs settings set lesson_price_when_empty on_request
	 *
	 * @param array<int, string> $args Positional arguments.
	 * @return void
	 */
	public function set( array $args ): void {
		$key   = (string) ( $args[0] ?? '' );
		$value = (string) ( $args[1] ?? '' );

		try {
			$stored = $this->plugin->settings()->set( $key, $value );
		} catch ( \InvalidArgumentException $e ) {
			\WP_CLI::error( sprintf( '%s: %s', $key, $e->getMessage() ) );

			return;
		}

		\WP_CLI::success( sprintf( '%s = %s', $key, $this->render( $stored ) ) );
	}

	/**
	 * Renders a value for display.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function render( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( null === $value ) {
			return '';
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return (string) wp_json_encode( $value );
	}
}
