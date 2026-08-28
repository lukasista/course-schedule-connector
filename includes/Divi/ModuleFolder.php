<?php
/**
 * The plugin's own shelf in Divi's module list.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Divi;

defined( 'ABSPATH' ) || exit;

/**
 * One folder, so that twenty-four modules are one entry rather than twenty-four.
 *
 * Divi's module list is alphabetical and long. Twenty-four modules scattered
 * through it — Price between Portfolio and Pricing Tables, Room between Row and
 * Search — is not a set of modules anybody can find, and worse, it is not
 * recognisably a set at all. Divi has the answer already: WooCommerce's modules
 * live in a folder called "Woo Modules", declared by a `folder` key on each
 * module and a folder registered in the builder. This is the same thing under
 * the plugin's own name.
 *
 * The definition lives here rather than in either module registrar because both
 * of them hand it over — whichever of the two scripts the builder loads first
 * registers the folder, and registering it twice is registering the same folder.
 */
final class ModuleFolder {

	/**
	 * What the folder is called in the metadata.
	 *
	 * This string is written into every generated `module.json` as `folder`,
	 * so changing it means regenerating them.
	 */
	public const NAME = 'cscs-modules';

	/**
	 * The name of the global the builder reads the folder from.
	 */
	public const GLOBAL = 'cscsDiviFolder';

	/**
	 * Returns the folder, in the shape Divi's `registerFolder` takes.
	 *
	 * The icon is one of Divi's own. There is no public way to add an icon to
	 * Divi's set — the builder exposes no registration for them — so a folder
	 * with a drawing of its own would mean reaching into Divi's internals for a
	 * picture, which is a poor trade for a picture.
	 *
	 * @return array<string, string>
	 */
	public static function definition(): array {
		return array(
			'name'     => self::NAME,
			'path'     => '',
			'title'    => __( 'iSport', 'course-schedule-connector' ),
			'icon'     => 'divi/module-table-of-contents',
			'category' => 'module',
		);
	}
}
