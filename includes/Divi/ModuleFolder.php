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
 * Three shelves, one for each thing somebody designs.
 *
 * Divi's module list is alphabetical and long. Thirty-two modules scattered
 * through it — Price between Portfolio and Pricing Tables, Room between Row and
 * Search — is not a set of modules anybody can find, and worse, it is not
 * recognisably a set at all. Divi has the answer already: WooCommerce's modules
 * live in a folder called "Woo Modules", declared by a `folder` key on each
 * module and a folder registered in the builder.
 *
 * One folder was the first step and not enough of one. Thirty-two entries under
 * one heading is a shorter list to scroll and still the wrong question to ask
 * of it: somebody in the builder is designing a trainer's page, or a kind of
 * course, or a course, and wants the fields of that one thing. So: three.
 *
 * Three side by side rather than three inside an "iSport" one, which was tried
 * and which empties the list. Divi's folders do take a `path` and the store
 * does file them as a tree — but the selector that lists them keeps a folder
 * only if that folder directly holds a module:
 *
 *     pickBy( folders, folder => some( getChildModules( { moduleFolder: … } ) ) )
 *
 * A folder holding nothing but subfolders holds no modules, so it is dropped —
 * and everything beneath it goes with it. The names carry the prefix instead,
 * which sorts them together and reads as one set, exactly as the three sections
 * in the block editor do.
 */
final class ModuleFolder {

	/**
	 * The folder a course's modules stand in.
	 */
	public const COURSES = 'cscs-courses';

	/**
	 * The folder a kind of course's modules stand in.
	 */
	public const KINDS = 'cscs-kinds';

	/**
	 * The folder a trainer's modules stand in.
	 */
	public const TRAINERS = 'cscs-trainers';

	/**
	 * The name of the global the builder reads the folders from.
	 */
	public const GLOBAL = 'cscsDiviFolder';

	/**
	 * Returns the folder a module of a given context belongs in.
	 *
	 * This string is written into every generated `module.json` as `folder`,
	 * so changing one means regenerating them.
	 *
	 * @param string $context Field context — `course`, `trainer`, `kind`.
	 * @return string
	 */
	public static function path( string $context ): string {
		$folders = array(
			'course'  => self::COURSES,
			'kind'    => self::KINDS,
			'trainer' => self::TRAINERS,
		);

		return $folders[ $context ] ?? self::COURSES;
	}

	/**
	 * Returns every folder, in the shape Divi's `registerFolder` takes.
	 *
	 * The icons are Divi's own. There is no public way to add an icon to Divi's
	 * set — the builder exposes no registration for them — so a folder with a
	 * drawing of its own would mean reaching into Divi's internals for a
	 * picture, which is a poor trade for a picture.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function definitions(): array {
		return array(
			array(
				'name'     => self::COURSES,
				'path'     => '',
				/* translators: a folder of modules in the page builder */
				'title'    => __( 'iSport: courses', 'course-schedule-connector' ),
				'icon'     => 'divi/module-table-of-contents',
				'category' => 'module',
			),
			array(
				'name'     => self::KINDS,
				'path'     => '',
				/* translators: a folder of modules in the page builder */
				'title'    => __( 'iSport: kinds of course', 'course-schedule-connector' ),
				'icon'     => 'divi/module-filterable-portfolio',
				'category' => 'module',
			),
			array(
				'name'     => self::TRAINERS,
				'path'     => '',
				/* translators: a folder of modules in the page builder */
				'title'    => __( 'iSport: trainers', 'course-schedule-connector' ),
				'icon'     => 'divi/module-person',
				'category' => 'module',
			),
		);
	}
}
