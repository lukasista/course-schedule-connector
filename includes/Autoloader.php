<?php
/**
 * PSR-4 autoloader.
 *
 * The plugin deliberately ships no Composer runtime dependency, so it registers
 * its own autoloader rather than requiring a vendor directory to be present.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS;

defined( 'ABSPATH' ) || exit;

/**
 * Maps the CSCS\ namespace onto the includes directory.
 */
final class Autoloader {

	/**
	 * Namespace prefix handled by this autoloader.
	 */
	private const PREFIX = 'CSCS\\';

	/**
	 * Registers the autoloader.
	 *
	 * @param string $base_dir Absolute path to the directory holding the CSCS namespace root.
	 * @return void
	 */
	public static function register( string $base_dir ): void {
		$base_dir = rtrim( $base_dir, '/\\' ) . '/';

		spl_autoload_register(
			static function ( string $class_name ) use ( $base_dir ): void {
				if ( 0 !== strncmp( self::PREFIX, $class_name, strlen( self::PREFIX ) ) ) {
					return;
				}

				$relative = substr( $class_name, strlen( self::PREFIX ) );
				$path     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

				if ( is_readable( $path ) ) {
					require_once $path;
				}
			}
		);
	}
}
