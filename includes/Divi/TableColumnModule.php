<?php
/**
 * The child module a table's columns are built from.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Divi;

defined( 'ABSPATH' ) || exit;

use CSCS\Render\Fields;

/**
 * A Divi child module that names one column of a table field.
 *
 * Today a table field such as *Upcoming classes* decides which of its columns
 * to show, and in what order, through a numbered setting per candidate column
 * — the "intermediate step" `Fields::ordered_columns()` describes. This module
 * is the replacement for the course's own timetable, `course-schedule`: it is
 * declared as that field's only child (`childrenName` in its `module.json`),
 * so a person builds the table by dragging column children in the layers
 * panel rather than typing numbers into a settings panel, and Divi's own
 * reordering is the column order.
 *
 * The module carries no design of its own beyond what every Divi module
 * already offers. Which column looks like what is a question the parent field
 * already answers — each candidate column has its own typography, declared
 * once in the generated module and unrelated to this — so nothing about that
 * needed to move for this to work, and did not.
 *
 * A column child renders nothing on its own: it exists to be read by its
 * parent's own render, the same way {@see FieldModuleRenderer} already reads
 * `$block->parsed_block['innerBlocks']`. An instance with no column children
 * — every page saved before this module existed — keeps behaving exactly as
 * it always has; see `Fields::columns_from_children()`.
 *
 * Everything here is guarded. Divi is a commercial theme that may be absent,
 * switched off, or a version whose builder does not exist yet.
 */
final class TableColumnModule {

	/**
	 * Module name.
	 *
	 * Also the child block/module name `Fields::columns_from_children()` is
	 * asked to look for when `course-schedule` renders.
	 */
	public const NAME = 'cscs/divi-course-schedule-column';

	/**
	 * The field this column belongs to — `course-schedule` today, and the
	 * only context this module knows the candidate columns of.
	 */
	private const FIELD = 'course-schedule';

	/**
	 * Visual Builder package name.
	 */
	private const PACKAGE = 'cscs-divi-course-schedule-column';

	/**
	 * Hooks the registration, if there is a Divi 5 to register with.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'divi_module_library_modules_dependency_tree', array( $this, 'register_module' ) );
		add_action( 'divi_visual_builder_assets_before_enqueue_scripts', array( $this, 'register_package' ) );
		add_action( 'divi_visual_builder_assets_after_enqueue_scripts', array( $this, 'hand_over_metadata' ) );
	}

	/**
	 * Adds the module to Divi's dependency tree.
	 *
	 * @param mixed $tree Divi's dependency tree.
	 * @return void
	 */
	public function register_module( $tree ): void {
		if ( ! is_object( $tree ) || ! method_exists( $tree, 'add_dependency' ) ) {
			return;
		}

		$tree->add_dependency( new TableColumnModuleDependency() );
	}

	/**
	 * Registers the script that edits the module in the Visual Builder.
	 *
	 * @return void
	 */
	public function register_package(): void {
		if ( ! class_exists( '\ET\Builder\VisualBuilder\Assets\PackageBuildManager' ) ) {
			return;
		}

		\ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build(
			array(
				'name'    => self::PACKAGE,
				'version' => CSCS_VERSION,
				'script'  => array(
					'src'                => CSCS_URL . 'visual-builder/cscs-divi-course-schedule-column.js',
					'deps'               => array( 'react', 'divi-module', 'divi-module-library', 'wp-hooks', 'wp-i18n' ),
					'enqueue_top_window' => false,
					'enqueue_app_window' => true,
				),
			)
		);
	}

	/**
	 * Hands the module's metadata to the script, once the script exists.
	 *
	 * @return void
	 */
	public function hand_over_metadata(): void {
		if ( ! wp_script_is( self::PACKAGE, 'registered' ) ) {
			return;
		}

		$metadata = $this->metadata();

		wp_localize_script( self::PACKAGE, 'cscsDiviColumn', $metadata );
		wp_set_script_translations( self::PACKAGE, 'course-schedule-connector', CSCS_DIR . 'languages' );
	}

	/**
	 * Returns the module's metadata with this field's columns in it.
	 *
	 * @return array<string, mixed>
	 */
	private function metadata(): array {
		$file = CSCS_DIR . 'divi/course-schedule-column/module.json';

		if ( ! is_readable( $file ) ) {
			return array();
		}

		$metadata = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the plugin's own file, not a remote one.

		if ( ! is_array( $metadata ) ) {
			return array();
		}

		$metadata['attributes']['column']['settings']['advanced']['field']['item']['component']['props']['options'] = $this->options();
		$metadata['title']  = __( 'Table column', 'course-schedule-connector' );
		$metadata['titles'] = __( 'Table columns', 'course-schedule-connector' );

		$metadata['attributes']['column']['settings']['advanced']['field']['item']['label']       = __( 'Which column', 'course-schedule-connector' );
		$metadata['attributes']['column']['settings']['advanced']['field']['item']['description'] = __( 'What this column of the table shows. Its place among the other column children is its place in the table.', 'course-schedule-connector' );

		// The builder computes nothing from the server's defaults, so they are
		// handed over with the metadata — see DisplayModule for the evening
		// this rule cost the first time it was learned.
		$metadata['defaults'] = $this->defaults();

		return $metadata;
	}

	/**
	 * Reads the module's default attributes.
	 *
	 * @return array<string, mixed>
	 */
	private function defaults(): array {
		$file = CSCS_DIR . 'divi/course-schedule-column/module-default-render-attributes.json';

		if ( ! is_readable( $file ) ) {
			return array();
		}

		$decoded = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the plugin's own file, not a remote one.

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Returns this field's candidate columns, in the shape a Divi select
	 * field expects.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function options(): array {
		// No "choose one" entry, the same reason the display module's set
		// picker has none: a select whose first option carries an empty value
		// has nothing to commit when it is picked, and every choice made after
		// it is refused.
		$options = array();

		foreach ( (array) ( Fields::get( self::FIELD )['columns'] ?? array() ) as $column ) {
			$options[ (string) $column ] = array( 'label' => Fields::column_label( (string) $column ) );
		}

		return $options;
	}
}
