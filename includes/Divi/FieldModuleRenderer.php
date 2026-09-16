<?php
/**
 * Rendering a field module.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Divi;

defined( 'ABSPATH' ) || exit;

use CSCS\Render\FieldRenderer;
use CSCS\Render\Assets;
use CSCS\Render\Fields;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use WP_Block;

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

		$parent   = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );
		$children = (array) ( $block->parsed_block['innerBlocks'] ?? array() );
		$field    = self::field( $name, $attrs, $children );

		// A field with nothing to say leaves the page entirely, wrapper and
		// all. Returning an empty module instead is not the same thing: a Divi
		// column is a flex container with a gap between its children, so a
		// module of no height still costs one gap — the reader sees a hole
		// where a trainer happens not to have filled something in, and there is
		// nothing on the page to explain it.
		if ( '' === trim( $field ) ) {
			return '';
		}

		// The stylesheet reaches a page only where something renders, and a
		// module rendered inside a Divi theme builder template is that
		// something: the plugin's own single-trainer template never runs there,
		// so nothing else would ask for it and the table would lose its rule,
		// its spacing and the fold that turns it into cards on a telephone.
		wp_enqueue_style( Assets::HANDLE );

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
	 * @param string               $name     Field name.
	 * @param array<string, mixed> $attrs    Module attributes.
	 * @param array<int, mixed>    $children The module's child blocks, if any.
	 * @return string
	 */
	public static function field( string $name, array $attrs, array $children = array() ): string {
		return FieldRenderer::render(
			\CSCS\Plugin::instance(),
			$name,
			self::settings( $attrs, $name, $children )
		);
	}

	/**
	 * The classes Divi needs on the field's own markup.
	 *
	 * A button that does not carry `et_pb_button` is a button Divi will not
	 * style: the Button panel writes its CSS against that class, and so does
	 * the button styling set for the site as a whole. Adding it here rather
	 * than in the renderer keeps it where it belongs — this class is the only
	 * one in the plugin that is allowed to know Divi's class names, and a site
	 * without Divi never sees it.
	 *
	 * It is public because the builder's canvas does not go through this class
	 * at all: it draws a module by asking `/cscs/v1/field` for the markup, and
	 * that route renders the field with the plugin's own renderer. Without this
	 * the anchor there carries `cscs-button` and not `et_pb_button` — so the
	 * Button panel's CSS, which names both, matched nothing on the canvas while
	 * matching perfectly on the page. Settings that appeared to do nothing, one
	 * class away.
	 *
	 * @param string $name Field name.
	 * @return string
	 */
	public static function extra_class( string $name ): string {
		$field = Fields::get( $name ) ?? array();

		return 'button' === (string) ( $field['signup'] ?? '' ) ? 'et_pb_button' : '';
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
	 * A field piloting the column-children mechanism has its numbered column
	 * settings overridden by whatever children are actually present: a table
	 * with no children falls back to the numbers above, unchanged, exactly as
	 * it always has.
	 *
	 * @param array<string, mixed> $attrs    Module attributes.
	 * @param string               $name     Field name.
	 * @param array<int, mixed>    $children The module's child blocks, if any.
	 * @return array<string, mixed>
	 */
	private static function settings( array $attrs, string $name = '', array $children = array() ): array {
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

		// `cardsLayout` and `cardLayout` are the one setting here Divi's own
		// native Layout widget writes, not a plain select — a small object of
		// its own keys (`display`, `flexDirection`, `justifyContent`, …), the
		// same shape `module.decoration.layout` holds for the module itself.
		// `$read()` above exists for scalars: handed an array, it takes the
		// array's first VALUE with `reset()` and throws the rest away, which
		// for a decoration setting like `showLabel` recovers the one thing a
		// responsive/hover wrapper was hiding it behind — and for this object
		// would keep only `display` and lose direction, alignment, gap, and
		// everything else that makes the widget worth having. This reads the
		// object whole and leaves turning it into CSS to
		// `FieldRenderer::card_rules()`, the one place that already knows how.
		//
		// A page built before the widget existed still has the plain
		// 'row'/'column' string the old select wrote, and nothing will ever
		// resave it just by being viewed. Coercing anything non-array to an
		// empty array — as if only the object shape ever existed — silently
		// threw that string away and left `card_rules()` nothing to draw,
		// which is exactly the page-render gap that shipping this without a
		// live check on an untouched saved module would have missed. The
		// string is passed through instead, unexamined here: validating it is
		// `FieldRenderer::layout_value()`'s job, and it already does that for
		// both shapes.
		$read_layout = static function ( string $key ) use ( $attrs ) {
			$value = $attrs['field']['advanced'][ $key ]['desktop']['value'] ?? null;

			if ( is_array( $value ) ) {
				return $value;
			}

			return is_string( $value ) ? $value : array();
		};

		$columns = array();

		foreach ( (array) ( Fields::get( $name )['columns'] ?? array() ) as $column ) {
			$key             = Fields::column_order_attribute( (string) $column );
			$columns[ $key ] = $read( $key );
		}

		$columns = Fields::apply_children_columns(
			$name,
			$columns,
			Fields::columns_from_children( $name, $children, 'cscs/divi-' . $name . '-column' )
		);

		return $columns + array(
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
			'filterGenders'   => $read( 'filterGenders' ),
			'filterLevels'    => $read( 'filterLevels' ),
			'filterAgeMin'    => $read( 'filterAgeMin' ),
			'filterAgeMax'    => $read( 'filterAgeMax' ),
			'filterSort'      => $read( 'filterSort' ),
			'filterOrder'     => $read( 'filterOrder', 'asc' ),
			'filterLimit'     => (int) $read( 'filterLimit', '0' ),
			'linkText'        => $read( 'linkText' ),
			'detailText'      => $read( 'detailText' ),
			'cardsLayout'     => $read_layout( 'cardsLayout' ),
			'cardLayout'      => $read_layout( 'cardLayout' ),
			'cardsGap'        => $read( 'cardsGap' ),
			'cardImageRatio'  => $read( 'cardImageRatio' ),
			'cardImageMargin' => $read( 'cardImageMargin' ),
			'extraClass'      => self::extra_class( $name ),
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
					// and the value styled apart from each other, the picture
					// where the field is one, and every part of a table where the
					// field draws one. A module that declares a setting and never
					// emits its CSS is worse than one that does not offer it at
					// all: the field accepts a value and nothing happens.
					//
					// The list is the catalogue's rather than this file's, so a
					// part added to a field arrives here without anybody having to
					// remember that this line exists.
					...array_map(
						// No return type: `style()` answers with whatever the
						// element needs — a string for some, a list of style
						// declarations for others — and `Style::add()` takes
						// both. Promising a string here is how a page of fields
						// became a fatal error.
						static function ( string $element ) use ( $elements ) {
							return $elements->style( array( 'attrName' => $element ) );
						},
						array_keys( Fields::style_elements( self::definition( (string) $args['name'] ) ) )
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
	 * Returns the definition of the field a module's name belongs to.
	 *
	 * @param string $module Module name, `cscs/divi-course-price` and the like.
	 * @return array<string, mixed>
	 */
	private static function definition( string $module ): array {
		return Fields::get( (string) preg_replace( '#^cscs/divi-#', '', $module ) ) ?? array();
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
