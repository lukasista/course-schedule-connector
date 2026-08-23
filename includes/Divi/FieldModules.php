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

			// The content fields were written in English in the generated file
			// and handed over that way, so the one panel a person opens first
			// was the one panel still in English. Their wording is this site's
			// business, not the file's.
			foreach ( self::field_labels() as $key => $wording ) {
				if ( ! isset( $metadata['attributes']['field']['settings']['advanced'][ $key ] ) ) {
					continue;
				}

				$metadata['attributes']['field']['settings']['advanced'][ $key ]['item']['label']       = $wording['label'];
				$metadata['attributes']['field']['settings']['advanced'][ $key ]['item']['description'] = $wording['description'];

				if ( isset( $wording['options'] ) ) {
					$metadata['attributes']['field']['settings']['advanced'][ $key ]['item']['component']['props']['options'] = $wording['options'];
				}
			}

			foreach ( self::group_labels() as $group => $label ) {
				if ( isset( $metadata['settings']['groups'][ $group ] ) ) {
					$metadata['settings']['groups'][ $group ]['component']['props']['groupLabel'] = $label;
				}
			}

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
	 * Returns the wording of every content setting, in the site's language.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function field_labels(): array {
		$labels = array(
			'source'          => array(
				'label'       => __( 'Source', 'course-schedule-connector' ),
				'description' => __( 'Which course or trainer this shows. Left as it is, it follows the page — which is what a theme builder template wants.', 'course-schedule-connector' ),
			),
			'showLabel'       => array(
				'label'       => __( 'Show a heading', 'course-schedule-connector' ),
				'description' => __( 'Whether the field prints its name above or beside the value.', 'course-schedule-connector' ),
			),
			'label'           => array(
				'label'       => __( 'Heading', 'course-schedule-connector' ),
				'description' => __( 'What to call this field. Empty means the name it comes with.', 'course-schedule-connector' ),
			),
			'labelTag'        => array(
				'label'       => __( 'Heading element', 'course-schedule-connector' ),
				'description' => __( 'Which HTML element the heading is.', 'course-schedule-connector' ),
			),
			'valueTag'        => array(
				'label'       => __( 'Value element', 'course-schedule-connector' ),
				'description' => __( 'Which HTML element the value is.', 'course-schedule-connector' ),
			),
			'layout'          => array(
				'label'       => __( 'Arrangement', 'course-schedule-connector' ),
				'description' => __( 'Heading above the value, or beside it.', 'course-schedule-connector' ),
				'options'     => array(
					'stack'  => array( 'label' => __( 'Heading above', 'course-schedule-connector' ) ),
					'inline' => array( 'label' => __( 'Side by side', 'course-schedule-connector' ) ),
				),
			),
			'separator'       => array(
				'label'       => __( 'After the heading', 'course-schedule-connector' ),
				'description' => __( 'A colon, a dash — printed right after the heading.', 'course-schedule-connector' ),
			),
			'gap'             => array(
				'label'       => __( 'Gap', 'course-schedule-connector' ),
				'description' => __( 'Between the heading and the value.', 'course-schedule-connector' ),
			),
			'listStyle'       => array(
				'label'       => __( 'Bullets', 'course-schedule-connector' ),
				'description' => __( 'What the list is marked with.', 'course-schedule-connector' ),
				'options'     => array(
					'disc'    => array( 'label' => __( 'Round', 'course-schedule-connector' ) ),
					'circle'  => array( 'label' => __( 'Hollow', 'course-schedule-connector' ) ),
					'square'  => array( 'label' => __( 'Square', 'course-schedule-connector' ) ),
					'ordered' => array( 'label' => __( 'Numbered', 'course-schedule-connector' ) ),
					'none'    => array( 'label' => __( 'None', 'course-schedule-connector' ) ),
				),
			),
			'emptyText'       => array(
				'label'       => __( 'When there is nothing to show', 'course-schedule-connector' ),
				'description' => __( 'Left empty, the module disappears rather than printing a heading over a blank space.', 'course-schedule-connector' ),
			),
			'imageSize'       => array(
				'label'       => __( 'Size', 'course-schedule-connector' ),
				'description' => __( 'Which of the sizes WordPress made of this picture to serve. Larger is not better: a portrait shown at 300 pixels costs the visitor nothing extra if 300 pixels is what is sent.', 'course-schedule-connector' ),
				'options'     => self::image_sizes(),
			),
			'imageAlt'        => array(
				'label'       => __( 'Alternative text', 'course-schedule-connector' ),
				'description' => __( 'What the picture says to somebody who cannot see it. Empty means the name of the course or trainer, which is usually right.', 'course-schedule-connector' ),
			),
			'imageLink'       => array(
				'label'       => __( 'Links to', 'course-schedule-connector' ),
				'description' => __( 'Where the picture takes a visitor who clicks it.', 'course-schedule-connector' ),
				'options'     => array(
					'none'   => array( 'label' => __( 'Nowhere', 'course-schedule-connector' ) ),
					'post'   => array( 'label' => __( 'Its own page', 'course-schedule-connector' ) ),
					'file'   => array( 'label' => __( 'The picture at full size', 'course-schedule-connector' ) ),
					'custom' => array( 'label' => __( 'An address of your own', 'course-schedule-connector' ) ),
				),
			),
			'imageLinkUrl'    => array(
				'label'       => __( 'Address', 'course-schedule-connector' ),
				'description' => __( 'Used when the picture links to an address of your own.', 'course-schedule-connector' ),
			),
			'imageLinkTarget' => array(
				'label'       => __( 'Open in a new window', 'course-schedule-connector' ),
				'description' => __( 'A new window is a surprise, so it is off unless somebody asks for it.', 'course-schedule-connector' ),
			),
		);

		return $labels;
	}

	/**
	 * Returns the design and content group names, in the site's language.
	 *
	 * @return array<string, string>
	 */
	private static function group_labels(): array {
		return array(
			'designHeadingText'  => __( 'Heading text', 'course-schedule-connector' ),
			'designValueText'    => __( 'Value text', 'course-schedule-connector' ),
			'contentPicture'     => __( 'Picture', 'course-schedule-connector' ),
			'contentPictureLink' => __( 'Link', 'course-schedule-connector' ),
		);
	}

	/**
	 * Returns the image sizes this site has, as a Divi select expects them.
	 *
	 * Read from the site rather than listed: a theme registers sizes, and a
	 * fixed list would refuse the one somebody added for exactly this.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function image_sizes(): array {
		$options = array();

		foreach ( Fields::sizes() as $size ) {
			$options[ $size ] = array( 'label' => $size );
		}

		return $options;
	}

	/**
	 * Returns the courses or trainers a module may be pointed at.	/**
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
