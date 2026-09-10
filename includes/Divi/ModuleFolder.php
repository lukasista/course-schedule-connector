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
 * One shelf, and three drawers in it.
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
 * course, or a course, and wants the fields of that one thing. Divi keeps its
 * folders as a tree — the store files them under `path/name` and the list asks
 * for the children of a path — so the three drawers below are subfolders of the
 * one shelf, and every module names the drawer it belongs in.
 *
 * The definition lives here rather than in either module registrar because both
 * of them hand it over — whichever of the two scripts the builder loads first
 * registers the folders, and registering the same folder twice registers the
 * same folder.
 */
final class ModuleFolder {

	/**
	 * The shelf everything of the plugin's stands on.
	 *
	 * This string is written into every generated `module.json` as the first
	 * part of `folder`, so changing it means regenerating them.
	 */
	public const NAME = 'cscs-modules';

	/**
	 * The name of the global the builder reads the folders from.
	 */
	public const GLOBAL = 'cscsDiviFolder';

	/**
	 * Returns the folder a field of a given context belongs in.
	 *
	 * @param string $context Field context — `course`, `trainer`, `kind`.
	 * @return string Folder path, as Divi files it.
	 */
	public static function path( string $context ): string {
		$drawers = array(
			'course'  => 'courses',
			'kind'    => 'kinds',
			'trainer' => 'trainers',
		);

		return self::NAME . '/' . ( $drawers[ $context ] ?? 'courses' );
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
				'name'     => self::NAME,
				'path'     => '',
				'title'    => __( 'iSport', 'course-schedule-connector' ),
				'icon'     => 'divi/module-table-of-contents',
				'category' => 'module',
			),
			array(
				'name'     => 'courses',
				'path'     => self::NAME,
				'title'    => __( 'Courses', 'course-schedule-connector' ),
				'icon'     => 'divi/module-blog',
				'category' => 'module',
			),
			array(
				'name'     => 'kinds',
				'path'     => self::NAME,
				'title'    => __( 'Kinds of course', 'course-schedule-connector' ),
				'icon'     => 'divi/module-filterable-portfolio',
				'category' => 'module',
			),
			array(
				'name'     => 'trainers',
				'path'     => self::NAME,
				'title'    => __( 'Trainers', 'course-schedule-connector' ),
				'icon'     => 'divi/module-person',
				'category' => 'module',
			),
		);
	}
}
