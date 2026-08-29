<?php
/**
 * The module, as Divi's dependency tree wants it.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Divi;

defined( 'ABSPATH' ) || exit;

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;

/**
 * Registers the module when Divi loads its own.
 *
 * Divi asks for a dependency object rather than a callback, and hands each one
 * a `load()` at the moment registration is safe. This class therefore names a
 * Divi interface, and for that reason it is never loaded unless Divi is there:
 * it is instantiated inside the hook Divi fires, and the plugin's autoloader
 * reads a file only when a class is actually used.
 */
final class ModuleDependency implements DependencyInterface {

	/**
	 * Registers the module.
	 *
	 * @return void
	 */
	public function load(): void {
		ModuleRegistration::register_module(
			CSCS_DIR . 'divi/cscs-display',
			array( 'render_callback' => array( ModuleRenderer::class, 'render_callback' ) )
		);
	}
}
