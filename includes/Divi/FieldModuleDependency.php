<?php
/**
 * The field modules, as Divi's dependency tree wants them.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Divi;

defined( 'ABSPATH' ) || exit;

use CSCS\Render\Fields;
use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;

/**
 * Registers every field module when Divi loads its own.
 *
 * One dependency object rather than twenty-three: Divi hands each one a
 * `load()` at the moment registration is safe, and there is nothing to be
 * gained by being handed that moment twenty-three times.
 *
 * Named a Divi interface, so it is never loaded unless Divi is there: it is
 * instantiated inside the hook Divi fires, and the autoloader reads a file only
 * when a class is actually used.
 */
final class FieldModuleDependency implements DependencyInterface {

	/**
	 * Registers the modules.
	 *
	 * @return void
	 */
	public function load(): void {
		foreach ( array_keys( Fields::all() ) as $name ) {
			$directory = CSCS_DIR . 'divi/fields/' . $name;

			if ( ! is_readable( $directory . '/module.json' ) ) {
				continue;
			}

			ModuleRegistration::register_module(
				$directory,
				array(
					'render_callback' => static function ( array $attrs, string $content, $block, $elements ) use ( $name ): string {
						return FieldModuleRenderer::render( $name, $attrs, $content, $block, $elements );
					},
				)
			);
		}
	}
}
