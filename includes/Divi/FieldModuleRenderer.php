<?php
/**
 * Rendering a field module.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Divi;

use CSCS\Render\FieldRenderer;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use WP_Block;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a module's settings into a field.
 *
 * The field itself comes from the plugin's own renderer, the same one the
 * blocks and the templates use. Divi contributes the wrapper and the design:
 * the classnames, the styles an administrator set on the module, on the heading
 * and on the value, and the custom CSS.
 *
 * The typography is deliberately left to Divi here, where the Gutenberg blocks
 * carry their own. Both editors offer the same two sets of settings; each does
 * it in the way its users already know, and neither has to learn the other's.
 *
 * Loaded only when Divi is present, since it names Divi's classes.
 */
final class FieldModuleRenderer {

	/**
	 * Renders one field module on the front end.
	 *
	 * @param string               $name     Field name.
	 * @param array<string, mixed> $attrs    Module attributes.
	 * @param string               $content  Block content.
	 * @param WP_Block             $block    Block.
	 * @param ModuleElements       $elements Module elements.
	 * @return string
	 */
	public static function render( string $name, array $attrs, string $content, WP_Block $block, ModuleElements $elements ): string {
		unset( $content );

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );
		$field  = self::field( $name, $attrs );

		// A field with nothing to say leaves the page entirely, wrapper and
		// all. Returning an empty module instead is not the same thing: a Divi
		// column is a flex container with a gap between its children, so a
		// module of no height still costs one gap — the reader sees a hole
		// where a trainer happens not to have filled something in, and there is
		// nothing on the page to explain it.
		if ( '' === trim( $field ) ) {
			return '';
		}

		return Module::render(
			array(
				'orderIndex'          => $block->parsed_block['orderIndex'],
				'storeInstance'       => $block->parsed_block['storeInstance'],
				'attrs'               => $attrs,
				'elements'            => $elements,
				'id'                  => $block->parsed_block['id'],
				'name'                => $block->block_type->name,
				'moduleCategory'      => $block->block_type->category,
				'classnamesFunction'  => array( self::class, 'module_classnames' ),
				'stylesComponent'     => array( self::class, 'module_styles' ),
				'scriptDataComponent' => array( self::class, 'module_script_data' ),
				'parentAttrs'         => $parent->attrs ?? array(),
				'parentId'            => $parent->id ?? '',
				'parentName'          => $parent->blockName ?? '',
				'children'            => $elements->style_components( array( 'attrName' => 'module' ) ) . $field,
			)
		);
	}

	/**
	 * Renders the field the module's settings describe.
	 *
	 * @param string               $name  Field name.
	 * @param array<string, mixed> $attrs Module attributes.
	 * @return string
	 */
	public static function field( string $name, array $attrs ): string {
		return FieldRenderer::render(
			\CSCS\Plugin::instance(),
			$name,
			self::settings( $attrs )
		);
	}

	/**
	 * Reads the module's settings out of Divi's attributes.
	 *
	 * Divi stores every attribute by breakpoint and state, even one that has
	 * neither. The values are read by hand rather than through Divi's own helper
	 * so that a change in that helper cannot take the page with it: the shape
	 * below is the stored JSON, and it is the same in every version that writes
	 * it.
	 *
	 * Nothing about typography is read. In Divi the heading and the value are
	 * styled by Divi's own font groups, whose CSS lands on
	 * `.cscs-field__label` and `.cscs-field__value` — so the renderer is handed
	 * no inline styles to fight with.
	 *
	 * @param array<string, mixed> $attrs Module attributes.
	 * @return array<string, mixed>
	 */
	private static function settings( array $attrs ): array {
		$read = static function ( string $key, string $fallback = '' ) use ( $attrs ): string {
			$value = $attrs['field']['advanced'][ $key ]['desktop']['value'] ?? null;

			if ( is_array( $value ) ) {
				$value = reset( $value );
			}

			if ( null === $value || '' === $value ) {
				return $fallback;
			}

			return (string) $value;
		};

		return array(
			'postId'          => (int) $read( 'source', '0' ),
			'showLabel'       => 'on' === $read( 'showLabel', 'off' ),
			'label'           => $read( 'label' ),
			'labelTag'        => $read( 'labelTag', 'h3' ),
			'valueTag'        => $read( 'valueTag', 'div' ),
			'layout'          => $read( 'layout', 'stack' ),
			'separator'       => $read( 'separator' ),
			'gap'             => $read( 'gap' ),
			'listStyle'       => $read( 'listStyle', 'disc' ),
			'emptyText'       => $read( 'emptyText' ),
			'imageSize'       => $read( 'imageSize', 'large' ),
			'imageAlt'        => $read( 'imageAlt' ),
			'imageLink'       => $read( 'imageLink', 'none' ),
			'imageLinkUrl'    => $read( 'imageLinkUrl' ),
			'imageLinkTarget' => 'on' === $read( 'imageLinkTarget', 'off' ),
		);
	}

	/**
	 * Adds the module's classnames.
	 *
	 * @param array<string, mixed> $args Arguments.
	 * @return void
	 */
	public static function module_classnames( array $args ): void {
		$attrs = $args['attrs'] ?? array();

		$args['classnamesInstance']->add(
			ElementClassnames::classnames(
				array( 'attrs' => $attrs['module']['decoration'] ?? array() )
			)
		);
	}

	/**
	 * Adds the module's styles, including the heading's and the value's.
	 *
	 * @param array<string, mixed> $args Arguments.
	 * @return void
	 */
	public static function module_styles( array $args ): void {
		$attrs    = $args['attrs'] ?? array();
		$elements = $args['elements'];
		$settings = $args['settings'] ?? array();

		Style::add(
			array(
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => array(
					$elements->style(
						array(
							'attrName'   => 'module',
							'styleProps' => array(
								'disabledOn' => array(
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								),
							),
						)
					),
					// The sets that make these modules worth having: the heading
					// and the value styled apart from each other, and — where
					// the field is a picture — the picture itself. A module that
					// declares a setting and never emits its CSS is worse than
					// one that does not offer it at all: the field accepts a
					// value and nothing happens.
					$elements->style( array( 'attrName' => 'title' ) ),
					$elements->style( array( 'attrName' => 'value' ) ),
					$elements->style( array( 'attrName' => 'image' ) ),
					CssStyle::style(
						array(
							'selector' => $args['orderClass'],
							'attr'     => $attrs['css'] ?? array(),
						)
					),
				),
			)
		);
	}

	/**
	 * Adds the module's script data.
	 *
	 * @param array<string, mixed> $args Arguments.
	 * @return void
	 */
	public static function module_script_data( array $args ): void {
		$args['elements']->script_data( array( 'attrName' => 'module' ) );
	}
}
