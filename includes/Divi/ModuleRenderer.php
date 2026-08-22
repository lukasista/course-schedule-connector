<?php
/**
 * Rendering the Divi module.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Divi;

use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use WP_Block;

defined( 'ABSPATH' ) || exit;

/**
 * Turns the module's one attribute into a listing.
 *
 * The listing itself comes from the plugin's renderer, the same one the
 * shortcode and the block use. Divi contributes the wrapper: the classnames, the
 * design styles an administrator set, the custom CSS. Nothing about what a
 * listing contains is decided here, and nothing about how a Divi module is
 * built is decided in the renderer.
 *
 * Loaded only when Divi is present, since it names Divi's classes.
 */
final class ModuleRenderer {

	/**
	 * Renders the module on the front end.
	 *
	 * @param array<string, mixed> $attrs    Module attributes.
	 * @param string               $content  Block content.
	 * @param WP_Block             $block    Block.
	 * @param ModuleElements       $elements Module elements.
	 * @return string
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements ): string {
		unset( $content );

		$parent  = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );
		$listing = self::listing( $attrs );

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
				'children'            => $elements->style_components( array( 'attrName' => 'module' ) ) . $listing,
			)
		);
	}

	/**
	 * Renders the listing the chosen set describes.
	 *
	 * @param array<string, mixed> $attrs Module attributes.
	 * @return string
	 */
	public static function listing( array $attrs ): string {
		$id = self::set_id( $attrs );

		if ( '' === $id ) {
			return current_user_can( 'edit_posts' )
				? '<p class="cscs-notice">' . esc_html__( 'Choose a display set in the module settings.', 'course-schedule-connector' ) . '</p>'
				: '';
		}

		return \CSCS\Plugin::instance()->renderer()->render_id( $id );
	}

	/**
	 * Reads the chosen set out of the module's attributes.
	 *
	 * Divi stores every attribute by breakpoint and state, even one that has
	 * neither. The value is read by hand rather than through Divi's own helper
	 * so that a change in that helper cannot take the listing with it: the
	 * shape below is the stored JSON, and it is the same in every version that
	 * writes it.
	 *
	 * @param array<string, mixed> $attrs Module attributes.
	 * @return string
	 */
	public static function set_id( array $attrs ): string {
		$value = $attrs['set']['innerContent']['desktop']['value'] ?? '';

		if ( is_array( $value ) ) {
			$value = $value['set'] ?? '';
		}

		return sanitize_key( (string) $value );
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
	 * Adds the module's styles.
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
