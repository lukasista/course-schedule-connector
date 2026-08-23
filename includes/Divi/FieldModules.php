<?php
/**
 * The Divi 5 field modules.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Divi;

use CSCS\Data\PostType;
use CSCS\Data\TrainerType;
use CSCS\Plugin;
use CSCS\Render\Assets;
use CSCS\Render\Fields;
use CSCS\Render\RestPreview;

defined( 'ABSPATH' ) || exit;

/**
 * A Divi module for every field, from the same catalogue as the blocks.
 *
 * The design groups are Divi's own, and there are three sets of them rather
 * than one: the module, the heading, and the value. That is the point. A page
 * builder that can only style a "price block" as a whole leaves you writing CSS
 * the moment the word and the number need to differ, which is immediately.
 *
 * Everything here is guarded. Divi is a commercial theme that may be absent,
 * switched off, or a version whose builder does not exist yet; the blocks, the
 * shortcode and the plugin's own templates keep working regardless.
 */
final class FieldModules {

	/**
	 * Visual Builder package name.
	 */
	public const PACKAGE = 'cscs-divi-fields';

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
		add_action( 'divi_module_library_modules_dependency_tree', array( $this, 'register_modules' ) );
		add_action( 'divi_visual_builder_assets_before_enqueue_scripts', array( $this, 'register_package' ) );
		add_action( 'divi_visual_builder_assets_after_enqueue_scripts', array( $this, 'hand_over_metadata' ) );
	}

	/**
	 * Adds the modules to Divi's dependency tree.
	 *
	 * @param mixed $tree Divi's dependency tree.
	 * @return void
	 */
	public function register_modules( $tree ): void {
		if ( ! is_object( $tree ) || ! method_exists( $tree, 'add_dependency' ) ) {
			return;
		}

		$tree->add_dependency( new FieldModuleDependency() );
	}

	/**
	 * Registers the script that edits the modules in the Visual Builder.
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
					'src'                => CSCS_URL . 'visual-builder/cscs-divi-fields.js',
					'deps'               => array( 'react', 'divi-module', 'divi-module-library', 'wp-hooks', 'wp-i18n' ),
					'enqueue_top_window' => false,
					'enqueue_app_window' => true,
				),
			)
		);
	}

	/**
	 * Hands the modules' metadata to the script, once the script exists.
	 *
	 * @return void
	 */
	public function hand_over_metadata(): void {
		if ( ! wp_script_is( self::PACKAGE, 'registered' ) ) {
			return;
		}

		wp_localize_script( self::PACKAGE, 'cscsDiviFields', $this->metadata() );
		wp_set_script_translations( self::PACKAGE, 'course-schedule-connector', CSCS_DIR . 'languages' );

		wp_enqueue_style( Assets::HANDLE );
	}

	/**
	 * Returns every module's metadata, translated, with this site's posts in it.
	 *
	 * The one thing a file cannot hold is which courses and trainers exist,
	 * because that is this site's and not the plugin's. So the files are read
	 * and the lists put in, and there is still one definition of what a module
	 * is rather than two that drift.
	 *
	 * @return array<string, mixed>
	 */
	private function metadata(): array {
		$modules = array();
		$sources = array(
			Fields::COURSE  => $this->sources( PostType::COURSE ),
			Fields::TRAINER => $this->sources( TrainerType::TRAINER ),
		);

		foreach ( Fields::all() as $name => $field ) {
			$directory = CSCS_DIR . 'divi/fields/' . $name;
			$metadata  = self::read( $directory . '/module.json' );

			if ( array() === $metadata ) {
				continue;
			}

			$metadata['title']  = (string) $field['title'];
			$metadata['titles'] = (string) $field['title'];

			$metadata['settings']['groups']['designHeadingText']['component']['props']['groupLabel'] =
				__( 'Heading text', 'course-schedule-connector' );
			$metadata['settings']['groups']['designValueText']['component']['props']['groupLabel'] =
				__( 'Value text', 'course-schedule-connector' );

			// Divi prints this beside every control in the group — "Text Size
			// of the heading" — so it is a phrase rather than a noun, and has
			// to be translated with the sentence it lands in mind. Written out
			// rather than looped, so that the strings are literal where the
			// extractor looks for them.
			$labels = array(
				'title' => __( 'of the heading', 'course-schedule-connector' ),
				'value' => __( 'of the value', 'course-schedule-connector' ),
			);

			foreach ( $labels as $element => $label ) {
				foreach ( array( 'font', 'spacing' ) as $property ) {
					$metadata['attributes'][ $element ]['settings']['decoration'][ $property ]['item']['component']['props']['fieldLabel'] = $label;
				}
			}

			$metadata['attributes']['field']['settings']['advanced']['source']['item']['component']['props']['options'] =
				$sources[ $field['context'] ];

			// Divi computes nothing from the server's defaults, so they are
			// handed over with the metadata. Without them there is no structure
			// to write a chosen value into, and every field silently refuses
			// every choice — which is exactly how this looked for an evening.
			$metadata['defaults'] = self::read( $directory . '/module-default-render-attributes.json' );

			$modules[] = $metadata;
		}

		return array(
			'modules' => $modules,
			'preview' => rest_url( RestPreview::NAMESPACE . '/field' ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			// Handed over here rather than fetched by the script through
			// wp.i18n. The builder loads its packages through Divi's own
			// manager, and a handle WordPress did not register is a handle
			// `wp_set_script_translations` cannot reach — the strings then
			// silently stay English while everything else on the panel is
			// translated. These arrive with the metadata, which demonstrably
			// works, because the module titles come the same way.
			'strings' => array(
				'empty' => __( 'This field is empty for this record, so it will not appear on the page.', 'course-schedule-connector' ),
				'error' => __( 'The field could not be loaded.', 'course-schedule-connector' ),
			),
		);
	}

	/**
	 * Returns the courses or trainers a module may be pointed at.
	 *
	 * @param string $post_type Post type.
	 * @return array<string, array<string, string>>
	 */
	private function sources( string $post_type ): array {
		// The first option carries an empty value on purpose here, unlike the
		// listing module's: "the one this page is about" is the useful default
		// and the one a theme builder template needs, so it has to be
		// choosable rather than merely initial.
		$options = array(
			'0' => array( 'label' => __( 'The one this page is about', 'course-schedule-connector' ) ),
		);

		$posts = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => 'publish',
				'numberposts'      => 200,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		foreach ( $posts as $post ) {
			$options[ (string) $post->ID ] = array( 'label' => get_the_title( $post ) );
		}

		return $options;
	}

	/**
	 * Reads one of the plugin's own JSON files.
	 *
	 * @param string $file Path.
	 * @return array<string, mixed>
	 */
	private static function read( string $file ): array {
		if ( ! is_readable( $file ) ) {
			return array();
		}

		$decoded = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the plugin's own file, not a remote one.

		return is_array( $decoded ) ? $decoded : array();
	}
}
