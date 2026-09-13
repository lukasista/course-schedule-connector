<?php
/**
 * The table column module, as Divi's dependency tree wants it.
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
 * Named a Divi interface, so it is never loaded unless Divi is there: it is
 * instantiated inside the hook Divi fires, and the plugin's autoloader reads
 * a file only when a class is actually used.
 */
final class TableColumnModuleDependency implements DependencyInterface {

	/**
	 * Registers the module.
	 *
	 * The render callback answers every request with nothing: a column child
	 * exists to be read by its parent, in `FieldModuleRenderer`, not to draw
	 * anything of its own. Divi still asks every module to render, the same
	 * way WordPress asks every block to, whether or not the parent uses what
	 * comes back.
	 *
	 * @return void
	 */
	public function load(): void {
		ModuleRegistration::register_module(
			CSCS_DIR . 'divi/course-schedule-column',
			array(
				'render_callback' => static function (): string {
					return '';
				},
			)
		);
	}
}
