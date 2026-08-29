<?php
/**
 * The field blocks.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

use CSCS\Data\Audience;
use CSCS\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers a named block for every field, out of one definition.
 *
 * There is no block.json here and no directory per block, because there is no
 * per-block code: sixteen folders holding sixteen near-identical files is a
 * maintenance bill paid every time one of them needs changing. The catalogue in
 * {@see Fields} is read once and turned into registrations, so a field added
 * there appears in the inserter under its own name with nothing else written.
 *
 * The supports are deliberately generous. A block that shows a price and
 * refuses to be given a background is a block somebody works around, and the
 * whole point of these is that a page can be built out of them without opening
 * a stylesheet.
 */
final class FieldBlocks {

	/**
	 * Editor script handle.
	 */
	public const SCRIPT = 'cscs-field-blocks';

	/**
	 * Front-end script handle, for the animations.
	 */
	public const MOTION = 'cscs-motion';

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
	 * Hooks the registration.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_blocks' ), 20 );
	}

	/**
	 * Registers every field block.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			self::SCRIPT,
			CSCS_URL . 'blocks/fields/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-data', 'wp-core-data', 'wp-server-side-render' ),
			CSCS_VERSION,
			true
		);

		wp_set_script_translations( self::SCRIPT, 'course-schedule-connector', CSCS_DIR . 'languages' );

		wp_localize_script( self::SCRIPT, 'cscsFields', $this->catalogue() );

		wp_register_script(
			self::MOTION,
			CSCS_URL . 'assets/js/cscs-motion.js',
			array(),
			CSCS_VERSION,
			true
		);

		foreach ( Fields::all() as $name => $field ) {
			register_block_type(
				'cscs/' . $name,
				array(
					'api_version'           => 3,
					'title'                 => (string) $field['title'],
					'description'           => (string) $field['description'],
					'category'              => 'widgets',
					'icon'                  => (string) $field['icon'],
					'keywords'              => array( 'isport', 'course', 'trainer' ),
					'textdomain'            => 'course-schedule-connector',
					'attributes'            => FieldRenderer::attributes( $field ),
					'supports'              => self::supports(),
					'editor_script_handles' => array( self::SCRIPT ),
					'style_handles'         => array( Assets::HANDLE ),
					'view_script_handles'   => array( self::MOTION ),
					'render_callback'       => function ( array $attributes, string $content, $block ) use ( $name ): string {
						unset( $content, $block );

						return FieldRenderer::render(
							$this->plugin,
							$name,
							$attributes,
							function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : ''
						);
					},
				)
			);
		}
	}

	/**
	 * Returns what WordPress lets an editor change about these blocks.
	 *
	 * Everything core offers, and on purpose. The heading and the value have
	 * settings of their own besides — see {@see FieldRenderer} — because block
	 * supports style a block as one thing, and this block is two.
	 *
	 * @return array<string, mixed>
	 */
	private static function supports(): array {
		return array(
			'html'                 => false,
			'anchor'               => true,
			'align'                => array( 'wide', 'full' ),
			'className'            => true,
			'color'                => array(
				'background' => true,
				'text'       => true,
				'gradients'  => true,
				'link'       => true,
			),
			'typography'           => array(
				'fontSize'                       => true,
				'lineHeight'                     => true,
				'__experimentalFontFamily'       => true,
				'__experimentalFontWeight'       => true,
				'__experimentalFontStyle'        => true,
				'__experimentalTextTransform'    => true,
				'__experimentalTextDecoration'   => true,
				'__experimentalLetterSpacing'    => true,
				'__experimentalDefaultControls'  => array(
					'fontSize' => true,
				),
			),
			'spacing'              => array(
				'margin'                        => true,
				'padding'                       => true,
				'blockGap'                      => true,
				'__experimentalDefaultControls' => array(
					'padding' => true,
				),
			),
			'__experimentalBorder' => array(
				'color'                         => true,
				'radius'                        => true,
				'style'                         => true,
				'width'                         => true,
				'__experimentalDefaultControls' => array(
					'width' => true,
					'color' => true,
				),
			),
			'shadow'               => true,
			'dimensions'           => array(
				'minHeight' => true,
			),
			'position'             => array(
				'sticky' => true,
			),
			'interactivity'        => array(
				'clientNavigation' => true,
			),
		);
	}

	/**
	 * Returns what the editor script needs to know about the fields.
	 *
	 * @return array<string, mixed>
	 */
	private function catalogue(): array {
		$fields = array();

		foreach ( Fields::all() as $name => $field ) {
			$columns = array();

			// Which columns a table has decides how many width and alignment
			// controls the block offers, and what each of them is called.
			foreach ( (array) $field['columns'] as $column ) {
				$columns[] = array(
					'key'       => (string) $column,
					'label'     => Fields::column_label( (string) $column ),
					'attribute' => Fields::column_attribute( (string) $column ),
				);
			}

			$fields[] = array(
				'name'    => 'cscs/' . $name,
				'field'   => $name,
				'context' => (string) $field['context'],
				'kind'    => (string) $field['kind'],
				'title'   => (string) $field['title'],
				'label'   => (string) $field['label'],
				'icon'    => (string) $field['icon'],
				'heading' => (bool) $field['heading'],
				'image'   => (bool) $field['image'],
				'bullets' => (bool) $field['bullets'],
				'filters' => (bool) $field['filters'],
				'columns' => $columns,
			);
		}

		$post_types = array();

		foreach ( Fields::all() as $field ) {
			$post_types[ (string) $field['context'] ] = Fields::post_type( (string) $field['context'] );
		}

		return array(
			'fields'    => $fields,
			// Asked of the catalogue rather than listed, so that a context
			// added to it cannot be missing from here.
			'postTypes' => $post_types,
			'settings'  => FieldRenderer::element_settings(),
			// The vocabulary the filters offer. Read from the same place the
			// course pages read it, so a gym that words its levels differently
			// through the filters sees its own words here too.
			'audience'  => array(
				'genders' => Audience::genders(),
				'levels'  => Audience::levels(),
			),
			// The sizes are this site's, not the plugin's: a theme registers
			// them, and a list written here would refuse the one somebody added
			// for exactly this picture.
			'sizes'     => Fields::sizes(),
		);
	}
}
