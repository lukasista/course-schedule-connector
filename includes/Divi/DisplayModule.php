<?php
/**
 * The Divi 5 module.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Divi;

use CSCS\Data\DisplaySet;
use CSCS\Plugin;
use CSCS\Render\Assets;
use CSCS\Render\RestPreview;

defined( 'ABSPATH' ) || exit;

/**
 * A Divi 5 module whose only content field is the display set.
 *
 * This is the arrangement the site owner asked for, in one screen: whoever
 * maintains the courses opens the module, chooses a set, and is done. What the
 * listing contains was decided under iSport → Display sets, and every page
 * using that set follows the change without anybody opening the builder at all.
 * The design groups beside it are Divi's own, and they belong to whoever owns
 * the design — enforced when the page is saved rather than by hiding fields.
 *
 * Everything here is guarded. Divi is a commercial theme that may be absent,
 * switched off, or a version whose builder does not exist yet; a plugin that
 * fatals when it is has no business being on WordPress.org, and the shortcode
 * and the block must keep working regardless.
 */
final class DisplayModule {

	/**
	 * Module name, and the block type it registers as.
	 *
	 * Deliberately not the same as the editor block: two block types cannot
	 * share a name, and these are two registrations of the same listing.
	 */
	public const NAME = 'cscs/divi-display';

	/**
	 * Visual Builder package name.
	 */
	private const PACKAGE = 'cscs-divi-display';

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
	 * Hooks the registration, if there is a Divi 5 to register with.
	 *
	 * @return void
	 */
	public function register(): void {
		// Divi hands each module a place in its dependency tree and calls it
		// when registration is safe. Every one of these hooks fires only when
		// Divi 5 is running, which is what keeps the plugin working without it.
		add_action( 'divi_module_library_modules_dependency_tree', array( $this, 'register_module' ) );
		add_action( 'divi_visual_builder_assets_before_enqueue_scripts', array( $this, 'register_package' ) );
		add_action( 'divi_visual_builder_assets_after_enqueue_scripts', array( $this, 'hand_over_metadata' ) );
		add_action( 'divi_visual_builder_assets_after_enqueue_styles', array( $this, 'enqueue_style' ) );
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

		$tree->add_dependency( new ModuleDependency() );
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
					'src'                => CSCS_URL . 'visual-builder/cscs-divi-display.js',
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
	 * The handle is the package name, and it is only registered while Divi
	 * enqueues its own scripts — so this waits until after that, rather than
	 * localising a script nothing has heard of yet and failing silently.
	 *
	 * @return void
	 */
	public function hand_over_metadata(): void {
		if ( ! wp_script_is( self::PACKAGE, 'registered' ) ) {
			return;
		}

		wp_localize_script( self::PACKAGE, 'cscsDiviModule', $this->metadata() );
		wp_set_script_translations( self::PACKAGE, 'course-schedule-connector', CSCS_DIR . 'languages' );
	}

	/**
	 * Puts the listing's stylesheet into the builder, so a preview looks right.
	 *
	 * @return void
	 */
	public function enqueue_style(): void {
		wp_enqueue_style( Assets::HANDLE );
	}

	/**
	 * Returns the module's metadata with this site's display sets in it.
	 *
	 * The Visual Builder needs the same metadata the server registered, and the
	 * one thing it cannot hold is the list of sets: that is this site's, not the
	 * plugin's. So the file is read and the options put in, and there is still
	 * one definition of what the module is rather than two that drift.
	 *
	 * @return array<string, mixed>
	 */
	private function metadata(): array {
		$file = CSCS_DIR . 'divi/cscs-display/module.json';

		if ( ! is_readable( $file ) ) {
			return array();
		}

		$metadata = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the plugin's own file, not a remote one.

		if ( ! is_array( $metadata ) ) {
			return array();
		}

		$metadata['attributes']['set']['settings']['advanced']['id']['item']['component']['props']['options'] = $this->options();
		$metadata['title']  = __( 'iSport listing', 'course-schedule-connector' );
		$metadata['titles'] = __( 'iSport listings', 'course-schedule-connector' );

		// The builder fetches its preview with a plain request rather than
		// through wp.apiFetch, which is not reliably present in the app window.
		$metadata['preview'] = rest_url( RestPreview::NAMESPACE . '/preview?set=' );
		$metadata['nonce']   = wp_create_nonce( 'wp_rest' );

		$metadata['attributes']['set']['settings']['advanced']['id']['item']['label']       = __( 'Display set', 'course-schedule-connector' );
		$metadata['attributes']['set']['settings']['advanced']['id']['item']['description'] = __( 'Which named configuration this listing follows. What it shows is changed under iSport, Display sets, and every page using the set follows.', 'course-schedule-connector' );

		return $metadata;
	}

	/**
	 * Returns the display sets, in the shape a Divi select field expects.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function options(): array {
		// No "choose one" entry: a select whose first option carries an empty
		// value has nothing to commit when it is picked, and the field then
		// refuses every choice made after it.
		$options = array();

		foreach ( $this->plugin->sets()->all() as $set ) {
			$options[ $set->id ] = array(
				'label' => sprintf(
					'%s (%s)',
					$set->name,
					DisplaySet::TYPE_SCHEDULE === $set->type
						? __( 'classes', 'course-schedule-connector' )
						: __( 'courses', 'course-schedule-connector' )
				),
			);
		}

		return $options;
	}
}
