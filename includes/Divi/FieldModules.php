<?php
/**
 * The Divi 5 field modules.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Divi;

defined( 'ABSPATH' ) || exit;

use CSCS\Data\Audience;
use CSCS\Plugin;
use CSCS\Render\Assets;
use CSCS\Render\Fields;
use CSCS\Render\RestPreview;

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

		wp_localize_script( self::PACKAGE, ModuleFolder::GLOBAL, ModuleFolder::definitions() );
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

		// Built from the catalogue rather than listed here, and this is not
		// tidiness. A hand-written list of contexts is a list that falls behind
		// the day somebody adds one: the kinds did, and every kind module
		// answered the builder with a select whose options were null, which
		// Divi shows as "this content cannot be displayed" — in the settings
		// panel, where the module is unusable, while the page itself rendered
		// perfectly and said nothing was wrong.
		$sources = array();

		foreach ( Fields::all() as $field ) {
			$context = (string) $field['context'];

			if ( ! isset( $sources[ $context ] ) ) {
				$sources[ $context ] = $this->sources( Fields::post_type( $context ) );
			}
		}

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
				'title'       => __( 'of the heading', 'course-schedule-connector' ),
				'value'       => __( 'of the value', 'course-schedule-connector' ),
				'tableHead'   => __( 'of the table heading', 'course-schedule-connector' ),
				'tableCell'   => __( 'of the table cell', 'course-schedule-connector' ),
				'tableLink'   => __( 'of the link in a table', 'course-schedule-connector' ),
				'tableStripe' => __( 'of the banded row', 'course-schedule-connector' ),
			);

			foreach ( (array) $field['columns'] as $column ) {
				/* translators: %s: the name of a column, "Price" and the like. */
				$labels[ Fields::column_attribute( (string) $column ) ] = sprintf( __( 'of the %s column', 'course-schedule-connector' ), Fields::column_label( (string) $column ) );
			}

			foreach ( $labels as $element => $label ) {
				$decoration = $metadata['attributes'][ $element ]['settings']['decoration'] ?? array();

				foreach ( array_keys( $decoration ) as $property ) {
					$metadata['attributes'][ $element ]['settings']['decoration'][ $property ]['item']['component']['props']['fieldLabel'] = $label;
				}
			}

			$metadata['attributes']['field']['settings']['advanced']['source']['item']['component']['props']['options'] =
				$sources[ (string) $field['context'] ] ?? array();

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

			// The one label that depends on the field rather than the key. On a
			// field that is itself a heading the value *is* the heading, and
			// the panel says so — same wording the heading of every other field
			// uses, so it is the same string and needs no translation of its
			// own.
			if ( ! empty( $field['headline'] ) && isset( $metadata['attributes']['field']['settings']['advanced']['valueTag'] ) ) {
				$metadata['attributes']['field']['settings']['advanced']['valueTag']['item']['label'] =
					__( 'Heading element', 'course-schedule-connector' );
			}

			$groups = self::group_labels();

			foreach ( (array) $field['columns'] as $column ) {
				/* translators: %s: the name of a column, "Price" and the like. */
				$groups[ 'design' . ucfirst( Fields::column_attribute( (string) $column ) ) ] = sprintf( __( 'Column: %s', 'course-schedule-connector' ), Fields::column_label( (string) $column ) );
			}

			foreach ( $groups as $group => $label ) {
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
	 * Turns a list of labels into a select's options, with an "any" first.
	 *
	 * @param array<string, string> $labels Key to label.
	 * @param string                $any    What the empty choice is called.
	 * @return array<string, array<string, string>>
	 */
	private static function choices( array $labels, string $any ): array {
		$options = array( '' => array( 'label' => $any ) );

		foreach ( $labels as $key => $label ) {
			$options[ (string) $key ] = array( 'label' => (string) $label );
		}

		return $options;
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
			'separator'       => array(
				'label'       => __( 'After the heading', 'course-schedule-connector' ),
				'description' => __( 'A colon, a dash — printed right after the heading.', 'course-schedule-connector' ),
			),
			'gap'             => array(
				'label'       => __( 'Gap', 'course-schedule-connector' ),
				'description' => __( 'Between the heading and the value.', 'course-schedule-connector' ),
			),
			'detailText'      => array(
				'label'       => __( 'Wording of the details link', 'course-schedule-connector' ),
				'description' => __( 'What the column is called and what each link says. Left empty, the name the column comes with.', 'course-schedule-connector' ),
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
			'filterGenders'   => array(
				'label'       => __( 'Who the course is for', 'course-schedule-connector' ),
				'description' => __( 'Left as it is, everybody. A course whose name says nothing about this is never shown by a setting that asks.', 'course-schedule-connector' ),
				'options'     => self::choices( Audience::genders(), __( 'Everybody', 'course-schedule-connector' ) ),
			),
			'filterLevels'    => array(
				'label'       => __( 'At what level', 'course-schedule-connector' ),
				'description' => __( 'Left as it is, every level.', 'course-schedule-connector' ),
				'options'     => self::choices( Audience::levels(), __( 'Every level', 'course-schedule-connector' ) ),
			),
			'filterAgeMin'    => array(
				'label'       => __( 'Age from', 'course-schedule-connector' ),
				'description' => __( 'Leaves out courses that finish below this age. Empty means no floor.', 'course-schedule-connector' ),
			),
			'filterAgeMax'    => array(
				'label'       => __( 'Age to', 'course-schedule-connector' ),
				'description' => __( 'Leaves out courses that start above this age. Empty means no ceiling.', 'course-schedule-connector' ),
			),
			'linkStyle'       => array(
				'label'       => __( 'Show as', 'course-schedule-connector' ),
				'description' => __( 'A button carries the plugin\'s own button look; a plain link carries none. Colour, background, spacing and border are available to both either way.', 'course-schedule-connector' ),
				'options'     => array(
					'button' => array( 'label' => __( 'Button', 'course-schedule-connector' ) ),
					'link'   => array( 'label' => __( 'Plain link', 'course-schedule-connector' ) ),
				),
			),
			'linkText'        => array(
				'label'       => __( 'Link text', 'course-schedule-connector' ),
				'description' => __( 'What the link says. Left empty, the wording set in iSport → Settings is used.', 'course-schedule-connector' ),
			),
			'filterSort'      => array(
				'label'       => __( 'Order by', 'course-schedule-connector' ),
				'description' => __( 'Left as it is, the order the courses are filed in.', 'course-schedule-connector' ),
				'options'     => array(
					''       => array( 'label' => __( 'As they are filed', 'course-schedule-connector' ) ),
					'name'   => array( 'label' => __( 'Name', 'course-schedule-connector' ) ),
					'start'  => array( 'label' => __( 'When it starts', 'course-schedule-connector' ) ),
					'price'  => array( 'label' => __( 'Price', 'course-schedule-connector' ) ),
					'places' => array( 'label' => __( 'Places free', 'course-schedule-connector' ) ),
				),
			),
			'filterOrder'     => array(
				'label'       => __( 'Which way', 'course-schedule-connector' ),
				'description' => __( 'Up or down.', 'course-schedule-connector' ),
				'options'     => array(
					'asc'  => array( 'label' => __( 'Ascending', 'course-schedule-connector' ) ),
					'desc' => array( 'label' => __( 'Descending', 'course-schedule-connector' ) ),
				),
			),
			'filterLimit'     => array(
				'label'       => __( 'At most', 'course-schedule-connector' ),
				'description' => __( 'How many rows to print. Empty or zero means all of them.', 'course-schedule-connector' ),
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
			'designLayout'       => __( 'Layout', 'course-schedule-connector' ),
			'designHeadingText'  => __( 'Heading text', 'course-schedule-connector' ),
			'designValueText'    => __( 'Value text', 'course-schedule-connector' ),
			'designTableHead'    => __( 'Table heading', 'course-schedule-connector' ),
			'designTableCell'    => __( 'Table cell', 'course-schedule-connector' ),
			'designTableLink'    => __( 'Table link', 'course-schedule-connector' ),
			'designTableRow'     => __( 'Banded row', 'course-schedule-connector' ),
			'contentCourses'     => __( 'Which courses', 'course-schedule-connector' ),
			'contentPicture'     => __( 'Picture', 'course-schedule-connector' ),
			'contentPictureLink' => __( 'Link', 'course-schedule-connector' ),
			'contentLink'        => __( 'Link', 'course-schedule-connector' ),
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
	 * Returns the courses, trainers or kinds a module may be pointed at.
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
