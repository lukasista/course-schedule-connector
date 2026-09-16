<?php
/**
 * Turning one field and its settings into markup.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

defined( 'ABSPATH' ) || exit;

use CSCS\Data\KindType;
use CSCS\Data\PostType;
use CSCS\Data\TrainerRepository;
use CSCS\Data\TrainerType;
use CSCS\Plugin;

/**
 * Draws a heading and a value, exactly as somebody arranged them.
 *
 * A field block has to give away as much control as a page builder does, and a
 * page builder's control is mostly typography — and typography of two things,
 * not one. "Price" wants to be small and quiet; "4 160 Kč" wants to be large.
 * Both live in one block, so both need their own settings, and neither can be
 * had from WordPress's own block supports, which style a block as a whole.
 *
 * So the heading and the value each carry their own set here, written into
 * inline styles. Everything a browser will be handed is checked against a
 * pattern or a list first: an attribute arrives from a saved post, a saved post
 * can be edited by anybody who may edit posts, and "it came from our own
 * editor" is not a claim about safety.
 */
final class FieldRenderer {

	/**
	 * The settings both the heading and the value hold.
	 *
	 * @return array<int, string>
	 */
	public static function element_settings(): array {
		return array(
			'FontSize',
			'FontFamily',
			'FontWeight',
			'FontStyle',
			'LineHeight',
			'LetterSpacing',
			'TextTransform',
			'TextDecoration',
			'Colour',
			'Align',
			'Margin',
		);
	}

	/**
	 * The settings the two halves of a table hold.
	 *
	 * A heading row and a body cell are two things a designer treats
	 * differently — the heading small and quiet in capitals, the cell plain —
	 * so each carries its own set, exactly as the field's heading and value do.
	 *
	 * @return array<int, string>
	 */
	public static function table_settings(): array {
		return array(
			'FontSize',
			'FontFamily',
			'FontWeight',
			'FontStyle',
			'LineHeight',
			'LetterSpacing',
			'TextTransform',
			'TextDecoration',
			'Colour',
			'Align',
			'Background',
			'Padding',
		);
	}

	/**
	 * The settings a link inside a table holds.
	 *
	 * Short on purpose. A link in a cell is the cell's text in another colour;
	 * giving it a font size of its own is offering a way to make a table look
	 * broken.
	 *
	 * @return array<int, string>
	 */
	public static function link_settings(): array {
		return array(
			'Colour',
			'FontWeight',
			'TextDecoration',
		);
	}

	/**
	 * Returns the attributes every field block and module understands.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return array<string, array<string, mixed>>
	 */
	public static function attributes( array $field ): array {
		$attributes = array(
			'postId'            => array(
				'type'    => 'number',
				'default' => 0,
			),
			'valueTag'          => array(
				'type'    => 'string',
				'default' => 'div',
			),
			'listStyle'         => array(
				'type'    => 'string',
				'default' => 'disc',
			),
			'emptyText'         => array(
				'type'    => 'string',
				'default' => '',
			),
			'animation'         => array(
				'type'    => 'string',
				'default' => 'none',
			),
			'animationDuration' => array(
				'type'    => 'number',
				'default' => 600,
			),
			'animationDelay'    => array(
				'type'    => 'number',
				'default' => 0,
			),
		);

		// Everything a heading needs, and only where there is one. A field
		// whose label is `null` has no heading — so no switch to show one, no
		// wording for it, no element to render it as, nothing to print after
		// it, and no arrangement or gap, both of which describe where the
		// heading sits relative to the value. A setting that cannot change
		// anything is worse than a missing one: it is read, tried, and
		// disbelieved.
		if ( Fields::heads( $field ) ) {
			$attributes['showLabel'] = array(
				'type'    => 'boolean',
				'default' => (bool) ( $field['heading'] ?? false ),
			);
			$attributes['label']     = array(
				'type'    => 'string',
				'default' => '',
			);
			$attributes['labelTag']  = array(
				'type'    => 'string',
				'default' => 'h3',
			);
			$attributes['layout']    = array(
				'type'    => 'string',
				'default' => 'stack',
			);
			$attributes['separator'] = array(
				'type'    => 'string',
				'default' => '',
			);
			$attributes['gap']       = array(
				'type'    => 'string',
				'default' => '',
			);
		}

		// Where each column of a table sits, counting from one; nought leaves it
		// out. The catalogue's order is what the places start as.
		foreach ( array_values( (array) ( $field['columns'] ?? array() ) ) as $index => $column ) {
			$attributes[ Fields::column_order_attribute( (string) $column ) ] = array(
				'type'    => 'string',
				'default' => (string) ( $index + 1 ),
			);
		}

		// The wording of the way into a course, where the table offers one.
		if ( in_array( 'detail', (array) ( $field['columns'] ?? array() ), true ) ) {
			$attributes['detailText'] = array(
				'type'    => 'string',
				'default' => '',
			);
		}

		if ( ! empty( $field['image'] ) ) {
			// A field showing a picture is a different shape from one showing a
			// price, and giving both the same settings is what left the
			// photograph module without the ones anybody would look for on it.
			$attributes['imageSize']       = array(
				'type'    => 'string',
				'default' => 'large',
			);
			$attributes['imageAlt']        = array(
				'type'    => 'string',
				'default' => '',
			);
			$attributes['imageLink']       = array(
				'type'    => 'string',
				'default' => 'none',
			);
			$attributes['imageLinkUrl']    = array(
				'type'    => 'string',
				'default' => '',
			);
			$attributes['imageLinkTarget'] = array(
				'type'    => 'boolean',
				'default' => false,
			);
		}

		if ( ! empty( $field['cards'] ) ) {
			// A grid of trainer cards is not a heading and a value, and not a
			// table either — the same handful of settings mean the same thing
			// in Divi and in Gutenberg, read by `card_rules()` below rather
			// than through a native panel only one editor could ever have
			// offered. `imageSize` is deliberately spelled the same as the
			// single-picture fields' own: it is the same choice, WordPress's
			// registered sizes, just asked once per card instead of once.
			$attributes['cardsLayout']     = array(
				'type'    => 'string',
				'default' => '',
			);
			$attributes['cardLayout']      = array(
				'type'    => 'string',
				'default' => '',
			);
			$attributes['cardsGap']        = array(
				'type'    => 'string',
				'default' => '',
			);
			$attributes['imageSize']       = array(
				'type'    => 'string',
				'default' => 'large',
			);
			$attributes['cardImageRatio']  = array(
				'type'    => 'string',
				'default' => '',
			);
			$attributes['cardImageMargin'] = array(
				'type'    => 'string',
				'default' => '',
			);
		}

		if ( ! empty( $field['bullets'] ) ) {
			// A value that can hold a list is a value somebody will want the
			// marks of that list to obey. The words are already covered by the
			// value's own typography; these are about the mark and the space
			// around it.
			$attributes['bulletStyle']  = array(
				'type'    => 'string',
				'default' => '',
			);
			$attributes['bulletColour'] = array(
				'type'    => 'string',
				'default' => '',
			);
			$attributes['bulletIndent'] = array(
				'type'    => 'string',
				'default' => '',
			);
			$attributes['bulletGap']    = array(
				'type'    => 'string',
				'default' => '',
			);
		}

		if ( '' !== (string) ( $field['signup'] ?? '' ) ) {
			// What the way into iSport says. Which shape it takes is the field
			// itself — a button and a link are two modules, because a page
			// builder styles the two through different panels and remembers
			// them through different presets.
			$attributes['linkText'] = array(
				'type'    => 'string',
				'default' => '',
			);
		}

		if ( ! empty( $field['filters'] ) ) {
			// Which of the courses under this heading to show. Stored as
			// comma-separated keys rather than as arrays, because Divi's
			// attributes are strings by breakpoint and state and a list there
			// would have to be encoded anyway — one shape for both editors.
			$attributes['filterGenders'] = array(
				'type'    => 'string',
				'default' => '',
			);
			$attributes['filterLevels']  = array(
				'type'    => 'string',
				'default' => '',
			);
			$attributes['filterAgeMin']  = array(
				'type'    => 'string',
				'default' => '',
			);
			$attributes['filterAgeMax']  = array(
				'type'    => 'string',
				'default' => '',
			);
			$attributes['filterSort']    = array(
				'type'    => 'string',
				'default' => '',
			);
			$attributes['filterOrder']   = array(
				'type'    => 'string',
				'default' => 'asc',
			);
			$attributes['filterLimit']   = array(
				'type'    => 'number',
				'default' => 0,
			);
		}

		foreach ( array( 'label', 'value' ) as $element ) {
			foreach ( self::element_settings() as $setting ) {
				$attributes[ $element . $setting ] = array(
					'type'    => 'string',
					'default' => '',
				);
			}
		}

		$columns = (array) ( $field['columns'] ?? array() );

		if ( array() === $columns ) {
			return $attributes;
		}

		// A field that draws a table has more parts than a heading and a value,
		// and until now none of them could be reached: the heading row, the
		// cells, the links in them, the rule between them, the banding behind
		// them, and the width of each column.
		foreach ( array( 'tableHead', 'tableCell' ) as $element ) {
			foreach ( self::table_settings() as $setting ) {
				$attributes[ $element . $setting ] = array(
					'type'    => 'string',
					'default' => '',
				);
			}
		}

		foreach ( self::link_settings() as $setting ) {
			$attributes[ 'tableLink' . $setting ] = array(
				'type'    => 'string',
				'default' => '',
			);
		}

		foreach ( array( 'tableLineWidth', 'tableLineColour', 'tableStripe' ) as $setting ) {
			$attributes[ $setting ] = array(
				'type'    => 'string',
				'default' => '',
			);
		}

		foreach ( $columns as $column ) {
			$attribute = Fields::column_attribute( (string) $column );

			$attributes[ $attribute . 'Width' ] = array(
				'type'    => 'string',
				'default' => '',
			);
			$attributes[ $attribute . 'Align' ] = array(
				'type'    => 'string',
				'default' => '',
			);
		}

		return $attributes;
	}

	/**
	 * Renders one field.
	 *
	 * @param Plugin               $plugin     Plugin instance.
	 * @param string               $name       Field name.
	 * @param array<string, mixed> $attributes Settings.
	 * @param string               $wrapper    Extra attributes for the outer element.
	 * @return string
	 */
	public static function render( Plugin $plugin, string $name, array $attributes, string $wrapper = '' ): string {
		$field = Fields::get( $name );

		if ( null === $field ) {
			return '';
		}

		$post = self::post( $field, $attributes );

		if ( ! $post instanceof \WP_Post ) {
			return self::notice(
				__( 'This block shows a field of a course or a trainer, and this page is neither. Choose one in the block settings.', 'course-schedule-connector' )
			);
		}

		$value = Fields::value( $plugin, $name, $post, $attributes );
		$body  = self::body( $value, $attributes );

		if ( '' === $body ) {
			$empty = trim( (string) ( $attributes['emptyText'] ?? '' ) );

			// A field with nothing to say is left out rather than printed
			// empty, exactly as an empty column is dropped from a listing. A
			// heading over a blank space reads as a broken page, not as "none".
			if ( '' === $empty ) {
				return '';
			}

			$body = '<span class="cscs-field__empty">' . esc_html( $empty ) . '</span>';
		}

		$label = self::label( $field, $attributes );
		$style = self::element_style( 'value', $attributes );

		$value_tag = self::tag( (string) ( $attributes['valueTag'] ?? 'div' ) );

		$html = sprintf(
			'<%1$s class="cscs-field__value"%2$s>%3$s</%1$s>',
			$value_tag,
			'' === $style ? '' : ' style="' . esc_attr( $style ) . '"',
			$body
		);

		$scoped = self::scoped_style( $field, $attributes );

		return sprintf(
			'<div %1$s>%2$s%3$s%4$s</div>',
			self::wrapper_attributes( $name, $attributes, $wrapper, $scoped['class'] ),
			$scoped['style'],
			$label,
			$html
		);
	}

	/**
	 * Builds the CSS a styled table needs, and the class it hangs on.
	 *
	 * A table cannot be styled the way the heading and the value are. Those are
	 * one element each and take an inline style; a table is a heading row, a
	 * body, cells, links and a rule between them, all drawn by a shared
	 * template that knows nothing about this block's settings. So the block
	 * writes rules instead, scoped to a class named after the settings
	 * themselves — two tables designed the same way share one class and one set
	 * of rules, and a table nobody has designed writes nothing at all.
	 *
	 * Which is also why the plugin's stylesheet does not carry these as custom
	 * properties with defaults. A rule like `.cscs-table a { color: var(--…) }`
	 * exists whether or not anybody set the property, and an unset custom
	 * property does not fall back to the theme's own rule — it falls back to
	 * nothing, and every link in every table loses the colour the theme gave
	 * it. Rules that exist only when somebody asked for them cannot do that.
	 *
	 * @param array<string, mixed> $field      Field definition.
	 * @param array<string, mixed> $attributes Settings.
	 * @return array{class: string, style: string}
	 */
	private static function scoped_style( array $field, array $attributes ): array {
		$rules = array_merge(
			self::table_rules( (array) ( $field['columns'] ?? array() ), $attributes ),
			empty( $field['bullets'] ) ? array() : self::bullet_rules( $attributes ),
			empty( $field['cards'] ) ? array() : self::card_rules( $attributes )
		);

		if ( array() === $rules ) {
			return array(
				'class' => '',
				'style' => '',
			);
		}

		// The class is named after what it says, so two blocks designed alike
		// share one rule and a block nobody has designed writes nothing at all.
		$class = 'cscs-scope--' . substr( md5( wp_json_encode( $rules ) ?: '' ), 0, 10 );
		$css   = '';

		foreach ( $rules as $selector => $declarations ) {
			$css .= str_replace( '{{scope}}', '.' . $class, $selector ) . '{' . $declarations . '}';
		}

		return array(
			'class' => $class,
			'style' => '<style>' . $css . '</style>',
		);
	}

	/**
	 * Returns the table's rules, selector to declarations, or nothing.
	 *
	 * @param array<int, string>   $columns    The columns this table has.
	 * @param array<string, mixed> $attributes Settings.
	 * @return array<string, string>
	 */
	private static function table_rules( array $columns, array $attributes ): array {
		if ( array() === $columns ) {
			return array();
		}

		$read = static function ( string $key ) use ( $attributes ): string {
			return trim( (string) ( $attributes[ $key ] ?? '' ) );
		};

		$rules = array();

		foreach ( array(
			'{{scope}} .cscs-table thead th' => 'tableHead',
			'{{scope}} .cscs-table tbody td' => 'tableCell',
			'{{scope}} .cscs-table tbody a'  => 'tableLink',
		) as $selector => $element ) {
			$style = self::element_style( $element, $attributes );

			if ( '' !== $style ) {
				$rules[ $selector ] = $style;
			}
		}

		$stripe = self::colour( $read( 'tableStripe' ) );

		if ( '' !== $stripe ) {
			$rules['{{scope}} .cscs-table tbody tr:nth-child(even)'] = 'background-color:' . $stripe . ';';
		}

		$width  = self::length( $read( 'tableLineWidth' ) );
		$colour = self::colour( $read( 'tableLineColour' ) );

		if ( '' !== $width || '' !== $colour ) {
			$rules['{{scope}} .cscs-table th,{{scope}} .cscs-table td'] = sprintf(
				'border-bottom:%s solid %s;',
				'' === $width ? '1px' : $width,
				'' === $colour ? 'var(--cscs-border)' : $colour
			);
		}

		foreach ( $columns as $column ) {
			$attribute   = Fields::column_attribute( (string) $column );
			$declaration = '';
			$size        = self::length( $read( $attribute . 'Width' ) );
			$align       = self::one_of( $read( $attribute . 'Align' ), array( 'left', 'center', 'right' ) );

			if ( '' !== $size ) {
				$declaration .= 'width:' . $size . ';';
			}

			if ( '' !== $align ) {
				$declaration .= 'text-align:' . $align . ';';
			}

			if ( '' !== $declaration ) {
				$rules[ '{{scope}} .cscs-table .cscs-col-' . sanitize_html_class( (string) $column ) ] = $declaration;
			}
		}

		return $rules;
	}

	/**
	 * Returns the rules that dress a list, or nothing where none was asked for.
	 *
	 * The mark in front of an item is styled through `::marker`, which is what
	 * the mark actually is — colouring the item itself would colour the words
	 * with it, and the words already have the value's own typography.
	 *
	 * @param array<string, mixed> $attributes Settings.
	 * @return array<string, string>
	 */
	private static function bullet_rules( array $attributes ): array {
		$read = static function ( string $key ) use ( $attributes ): string {
			return trim( (string) ( $attributes[ $key ] ?? '' ) );
		};

		$rules = array();
		$style = self::one_of(
			$read( 'bulletStyle' ),
			array( 'disc', 'circle', 'square', 'decimal', 'lower-alpha', 'upper-alpha', 'none' )
		);
		$indent = self::length( $read( 'bulletIndent' ) );
		$list   = '';

		if ( '' !== $style ) {
			$list .= 'list-style-type:' . $style . ';';
		}

		if ( '' !== $indent ) {
			$list .= 'padding-inline-start:' . $indent . ';';
		}

		if ( '' !== $list ) {
			$rules['{{scope}} .cscs-field__value ul,{{scope}} .cscs-field__value ol'] = $list;
		}

		$gap = self::length( $read( 'bulletGap' ) );

		if ( '' !== $gap ) {
			$rules['{{scope}} .cscs-field__value li + li'] = 'margin-top:' . $gap . ';';
		}

		$colour = self::colour( $read( 'bulletColour' ) );

		if ( '' !== $colour ) {
			$rules['{{scope}} .cscs-field__value li::marker'] = 'color:' . $colour . ';';
		}

		return $rules;
	}

	/**
	 * Returns the rules a grid of trainer cards needs, or nothing where
	 * nothing was asked for.
	 *
	 * `cardsLayout` and `cardLayout` carry Divi's own native Layout value now
	 * — {@see self::layout_value()} for the two shapes that can mean — turned
	 * into CSS by calling the exact function Divi's own module decoration
	 * calls, `Layout::style_declaration()`, so a row, a centred row, a
	 * wrapped grid or a manual CSS grid all come out exactly the way Divi's
	 * own Layout group would have drawn them, on `.cscs-cards` for how the
	 * cards sit next to each other and on `.cscs-card` for how the photograph
	 * and the name sit inside one. Gutenberg, which has no such widget, still
	 * reaches the same rules through a bare `"row"` or `"column"` string —
	 * the other shape {@see self::layout_value()} accepts.
	 *
	 * @param array<string, mixed> $attributes Settings.
	 * @return array<string, string>
	 */
	private static function card_rules( array $attributes ): array {
		$read = static function ( string $key ) use ( $attributes ): string {
			return trim( (string) ( $attributes[ $key ] ?? '' ) );
		};

		$rules = array();
		$cards = self::layout_declaration( $attributes['cardsLayout'] ?? '' );
		$gap   = self::length( $read( 'cardsGap' ) );

		if ( '' !== $gap ) {
			$cards .= 'gap:' . $gap . ';';
		}

		if ( '' !== $cards ) {
			$rules['{{scope}} .cscs-cards'] = $cards;
		}

		$card = self::layout_declaration( $attributes['cardLayout'] ?? '' );

		if ( '' !== $card ) {
			$rules['{{scope}} .cscs-card'] = $card;
		}

		$ratio  = self::aspect_ratio( $read( 'cardImageRatio' ) );
		$margin = self::spacing( $read( 'cardImageMargin' ) );
		$image  = '';

		if ( '' !== $ratio ) {
			$image .= 'aspect-ratio:' . $ratio . ';object-fit:cover;width:100%;';
		}

		if ( '' !== $margin ) {
			$image .= 'margin:' . $margin . ';';
		}

		if ( '' !== $image ) {
			$rules['{{scope}} .cscs-card__image'] = $image;
		}

		return $rules;
	}

	/**
	 * Turns a `cardsLayout`/`cardLayout` value into a CSS declaration
	 * string, or nothing where nothing was asked for.
	 *
	 * @param mixed $value Raw stored value.
	 * @return string
	 */
	private static function layout_declaration( $value ): string {
		$clean = self::layout_value( $value );

		if ( array() === $clean ) {
			return '';
		}

		$declaration      = '';
		$declaration_class = '\ET\Builder\Packages\StyleLibrary\Declarations\Layout\Layout';

		if ( class_exists( $declaration_class ) ) {
			$declaration = (string) $declaration_class::style_declaration(
				array(
					'attrValue'  => $clean,
					'returnType' => 'string',
					'render'     => array( 'display' => true ),
				)
			);
		} elseif ( isset( $clean['flexDirection'] ) ) {
			// Divi is not active to ask — a page can still be rendered by
			// Gutenberg alone, or by Divi mid-upgrade. Direction is the one
			// part of this worth keeping without it: the rest (alignment,
			// wrap, grid) is Divi's own panel drawing a value nothing here
			// can safely turn into CSS on its own.
			$declaration = 'display:flex;flex-direction:' . $clean['flexDirection'] . ';';
		}

		// Divi's own declaration only ever sets the custom properties its own
		// base stylesheet reads a gap from (`--horizontal-gap`,
		// `--vertical-gap`), never `row-gap`/`column-gap` themselves. Setting
		// them directly here — independently of each other, so choosing one
		// does not silently zero the other — means a visible gap does not
		// depend on that stylesheet being the one currently loaded.
		if ( isset( $clean['rowGap'] ) ) {
			$declaration .= 'row-gap:' . $clean['rowGap'] . ';';
		}

		if ( isset( $clean['columnGap'] ) ) {
			$declaration .= 'column-gap:' . $clean['columnGap'] . ';';
		}

		return $declaration;
	}

	/**
	 * Turns a stored `cardsLayout`/`cardLayout` value into the shape Divi's
	 * own `Layout::style_declaration()` expects, checking every part of it
	 * against what that part is allowed to be.
	 *
	 * The value arrives in one of two shapes. Divi's own native Layout widget
	 * — {@see the `designCardsLayout`/`designCardLayout` groups in
	 * `tools/build-divi-modules.php`} — writes a small object of its own
	 * named keys (`display`, `flexDirection`, `justifyContent`, …), the same
	 * object `module.decoration.layout` holds for the module itself.
	 * Gutenberg, which has no such widget, writes a bare `"row"` or
	 * `"column"` string, and a page saved before this change still has one
	 * stored that way. Both mean something; only the shape differs.
	 *
	 * @param mixed $value Raw stored value.
	 * @return array<string, string>
	 */
	private static function layout_value( $value ): array {
		if ( is_string( $value ) ) {
			$direction = self::one_of( $value, array( 'row', 'column' ) );

			return '' === $direction ? array() : array(
				'display'       => 'flex',
				'flexDirection' => $direction,
			);
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$raw = static function ( string $key ) use ( $value ): string {
			return trim( (string) ( $value[ $key ] ?? '' ) );
		};

		$pairs = array(
			'display'              => self::one_of( $raw( 'display' ), array( 'flex', 'grid', 'block' ) ),
			'flexDirection'        => self::one_of( $raw( 'flexDirection' ), array( 'row', 'column', 'row-reverse', 'column-reverse' ) ),
			'justifyContent'       => self::one_of( $raw( 'justifyContent' ), array( 'flex-start', 'flex-end', 'center', 'space-between', 'space-around', 'space-evenly' ) ),
			'alignItems'           => self::one_of( $raw( 'alignItems' ), array( 'flex-start', 'flex-end', 'center', 'stretch', 'baseline' ) ),
			'flexWrap'             => self::one_of( $raw( 'flexWrap' ), array( 'nowrap', 'wrap', 'wrap-reverse' ) ),
			'alignContent'         => self::one_of( $raw( 'alignContent' ), array( 'flex-start', 'flex-end', 'center', 'stretch', 'space-between', 'space-around', 'space-evenly' ) ),
			'columnGap'            => self::length( $raw( 'columnGap' ) ),
			'rowGap'               => self::length( $raw( 'rowGap' ) ),
			'gridColumnWidths'     => self::one_of( $raw( 'gridColumnWidths' ), array( 'equal', 'equalMinimum', 'equalFixed', 'auto', 'manual' ) ),
			'gridColumnCount'      => self::grid_count( $raw( 'gridColumnCount' ) ),
			'gridColumnMinWidth'   => self::length( $raw( 'gridColumnMinWidth' ) ),
			'gridColumnWidth'      => self::length( $raw( 'gridColumnWidth' ) ),
			'gridTemplateColumns'  => self::track_list( $raw( 'gridTemplateColumns' ) ),
			'gridAutoColumns'      => self::track_list( $raw( 'gridAutoColumns' ) ),
			'collapseEmptyColumns' => self::one_of( $raw( 'collapseEmptyColumns' ), array( 'on', 'off' ) ),
			'gridRowHeights'       => self::one_of( $raw( 'gridRowHeights' ), array( 'auto', 'equal', 'minimum', 'fixed', 'manual' ) ),
			'gridRowCount'         => self::grid_count( $raw( 'gridRowCount' ) ),
			'gridRowMinHeight'     => self::length( $raw( 'gridRowMinHeight' ) ),
			'gridRowHeight'        => self::length( $raw( 'gridRowHeight' ) ),
			'gridTemplateRows'     => self::track_list( $raw( 'gridTemplateRows' ) ),
			'gridAutoRows'         => self::track_list( $raw( 'gridAutoRows' ) ),
			'gridAutoFlow'         => self::one_of( $raw( 'gridAutoFlow' ), array( 'row', 'column' ) ),
			'gridDensity'          => self::one_of( $raw( 'gridDensity' ), array( 'dense', 'sparse' ) ),
			'gridJustifyItems'     => self::one_of( $raw( 'gridJustifyItems' ), array( 'start', 'end', 'center', 'stretch' ) ),
		);

		$clean = array();

		foreach ( $pairs as $key => $one_pair ) {
			if ( '' !== $one_pair ) {
				$clean[ $key ] = $one_pair;
			}
		}

		return $clean;
	}

	/**
	 * Returns a small positive count — a grid's column or row count — or
	 * nothing.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function grid_count( string $value ): string {
		return 1 === preg_match( '/^[1-9]\d{0,2}$/', $value ) ? $value : '';
	}

	/**
	 * Returns a raw CSS track-list value — a "manual" grid's own
	 * `grid-template-columns`/`-rows` or `grid-auto-columns`/`-rows` — or
	 * nothing.
	 *
	 * Divi lets a manual grid take genuinely open-ended CSS here — "1fr
	 * 2fr", "repeat(3, minmax(100px, 1fr))" — so this cannot be an
	 * allow-list the way a direction or an alignment can be. What it can do
	 * is refuse anything built from characters a track list never needs: no
	 * `;`, `{`, `}`, `<` or quote can end this declaration and start
	 * another one.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function track_list( string $value ): string {
		return 1 === preg_match( '/^[A-Za-z0-9 .,%()\/-]{1,200}$/', $value ) ? $value : '';
	}

	/**
	 * Returns a CSS aspect ratio, or nothing.
	 *
	 * @param string $value Value, "4/3" or "4 / 3".
	 * @return string
	 */
	private static function aspect_ratio( string $value ): string {
		$value = str_replace( ' ', '', $value );

		return 1 === preg_match( '/^\d+(\.\d+)?\/\d+(\.\d+)?$/', $value ) ? $value : '';
	}

	/**
	 * Returns the post a field is standing on.
	 *
	 * @param array<string, mixed> $field      Field definition.
	 * @param array<string, mixed> $attributes Settings.
	 * @return \WP_Post|null
	 */
	private static function post( array $field, array $attributes ): ?\WP_Post {
		$wanted = (int) ( $attributes['postId'] ?? 0 );
		$type   = Fields::post_type( (string) $field['context'] );

		if ( 0 !== $wanted ) {
			$chosen = get_post( $wanted );

			return $chosen instanceof \WP_Post && $type === $chosen->post_type ? $chosen : null;
		}

		// Nothing chosen means "whichever course or trainer this page is
		// about", which is what a theme builder template needs and what makes
		// one design serve them all.
		//
		// The question is answered by the request, not by the global post. In a
		// Divi theme builder template — the whole reason this option exists —
		// the global post while the layout renders is the layout itself, so
		// asking `get_post()` there returns a template and the module reports
		// that the page is neither a course nor a trainer, on a page that
		// plainly is one. `get_queried_object()` is what the visitor asked for
		// and it does not move.
		foreach ( self::candidates() as $candidate ) {
			if ( $candidate instanceof \WP_Post && $type === $candidate->post_type ) {
				return $candidate;
			}
		}

		$borrowed = self::borrowed( $type );

		if ( $borrowed instanceof \WP_Post ) {
			return $borrowed;
		}

		return self::sample( $type );
	}

	/**
	 * Returns the post a field can reach from the course the page is about.
	 *
	 * The whole point of a theme builder template is one design for every
	 * course, and a design of a course is not only the course: it is the person
	 * who runs it and the kind of thing it is. Those modules used to answer
	 * "this page is neither a course nor a trainer" on a page that was plainly
	 * a course — true of the module and useless to the reader, and the reason a
	 * global template could not be built.
	 *
	 * Only from a course, and only where the answer is one thing. A course has
	 * exactly one trainer. It has exactly one kind, though a kind may have
	 * several pages — this gym publishes the girls' gymnastics and the boys'
	 * apart — so the pages are narrowed by the audience each asks for, and
	 * where two would still both take the course the answer is none. Guessing
	 * would put a boy's course under a heading that says girls.
	 *
	 * The other directions are deliberately not travelled: a trainer has many
	 * courses and a kind has many of both, so there is nothing to borrow that
	 * would not be a choice made on somebody's behalf.
	 *
	 * @param string $type Post type the field is about.
	 * @return \WP_Post|null
	 */
	private static function borrowed( string $type ): ?\WP_Post {
		if ( PostType::COURSE === $type ) {
			return null;
		}

		foreach ( self::candidates() as $candidate ) {
			if ( ! $candidate instanceof \WP_Post || PostType::COURSE !== $candidate->post_type ) {
				continue;
			}

			$found = 0;

			if ( TrainerType::TRAINER === $type ) {
				$found = ( new TrainerRepository() )->for_course( (int) $candidate->ID );
			}

			if ( KindType::KIND === $type ) {
				$found = Plugin::instance()->kinds()->page_for_course( (int) $candidate->ID );
			}

			if ( 0 === $found ) {
				continue;
			}

			$post = get_post( $found );

			if ( $post instanceof \WP_Post ) {
				return $post;
			}
		}

		return null;
	}

	/**
	 * Returns the posts this request could reasonably be about, best first.
	 *
	 * @return array<int, mixed>
	 */
	private static function candidates(): array {
		$candidates = array();

		if ( function_exists( 'is_singular' ) && is_singular() ) {
			$candidates[] = get_queried_object();
		}

		// The block renderer sets the global post from the `post_id` the editor
		// sends, which is how a block previewing inside a course finds it.
		$candidates[] = get_post();

		return $candidates;
	}

	/**
	 * Returns something to draw, while a template is being designed.
	 *
	 * A theme builder template is about no particular course: it is about all
	 * of them. Every field on it would therefore preview as "this page is
	 * neither", which is true and useless — you cannot lay out a page whose
	 * every element refuses to appear. So the builder is shown a real record,
	 * the way Divi shows sample text in its own dynamic modules.
	 *
	 * Only ever in the editor. This is reached in a REST request from somebody
	 * who may edit posts, and a visitor's page is not a REST request, so no
	 * front end can ever borrow a course it was not pointed at.
	 *
	 * @param string $type Post type.
	 * @return \WP_Post|null
	 */
	private static function sample( string $type ): ?\WP_Post {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST || ! current_user_can( 'edit_posts' ) ) {
			return null;
		}

		$posts = get_posts(
			array(
				'post_type'        => $type,
				'post_status'      => 'publish',
				'numberposts'      => 1,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);

		return array() === $posts ? null : $posts[0];
	}

	/**
	 * Renders the heading.
	 *
	 * @param array<string, mixed> $field      Field definition.
	 * @param array<string, mixed> $attributes Settings.
	 * @return string
	 */
	private static function label( array $field, array $attributes ): string {
		// The catalogue has the last word, not the stored attributes: a page
		// saved while the field still offered a heading must not go on printing
		// one after the field stopped being that kind of thing.
		if ( ! Fields::heads( $field ) || empty( $attributes['showLabel'] ) ) {
			return '';
		}

		$text = trim( (string) ( $attributes['label'] ?? '' ) );
		$text = '' === $text ? (string) ( $field['label'] ?? '' ) : $text;

		if ( '' === $text ) {
			return '';
		}

		$separator = (string) ( $attributes['separator'] ?? '' );
		$style     = self::element_style( 'label', $attributes );

		return sprintf(
			'<%1$s class="cscs-field__label"%2$s>%3$s%4$s</%1$s>',
			self::tag( (string) ( $attributes['labelTag'] ?? 'h3' ) ),
			'' === $style ? '' : ' style="' . esc_attr( $style ) . '"',
			esc_html( $text ),
			'' === $separator ? '' : '<span class="cscs-field__separator">' . esc_html( $separator ) . '</span>'
		);
	}

	/**
	 * Renders the value itself, according to what kind of thing it is.
	 *
	 * @param array{kind: string, text: string, list: array<int, string>, html: string} $value      Value.
	 * @param array<string, mixed>                                                      $attributes Settings.
	 * @return string
	 */
	private static function body( array $value, array $attributes ): string {
		if ( Fields::LIST === $value['kind'] ) {
			if ( array() === $value['list'] ) {
				return '';
			}

			$style = self::list_style( (string) ( $attributes['listStyle'] ?? 'disc' ) );
			$tag   = 'ordered' === (string) ( $attributes['listStyle'] ?? '' ) ? 'ol' : 'ul';
			$items = '';

			foreach ( $value['list'] as $line ) {
				$items .= '<li>' . esc_html( $line ) . '</li>';
			}

			return sprintf(
				'<%1$s class="cscs-field__list"%2$s>%3$s</%1$s>',
				$tag,
				'' === $style ? '' : ' style="' . esc_attr( $style ) . '"',
				$items
			);
		}

		if ( Fields::HTML === $value['kind'] ) {
			return $value['html'];
		}

		$text = trim( $value['text'] );

		// Several lines that belong together — the days a course meets — are
		// kept apart, because joining them back into a sentence is exactly the
		// thing separating day from time was meant to stop.
		return '' === $text ? '' : nl2br( esc_html( $text ) );
	}

	/**
	 * Builds the outer element's attributes.
	 *
	 * @param string               $name       Field name.
	 * @param array<string, mixed> $attributes Settings.
	 * @param string               $wrapper     Attributes contributed by the editor.
	 * @param string               $extra_class One more class, where the table has styles of its own.
	 * @return string
	 */
	private static function wrapper_attributes( string $name, array $attributes, string $wrapper, string $extra_class = '' ): string {
		$classes = array(
			'cscs',
			'cscs-field',
			'cscs-field--' . sanitize_html_class( $name ),
			'cscs-field--' . ( 'inline' === (string) ( $attributes['layout'] ?? '' ) ? 'inline' : 'stack' ),
		);

		if ( '' !== $extra_class ) {
			$classes[] = $extra_class;
		}

		$style     = '';
		$gap       = self::length( (string) ( $attributes['gap'] ?? '' ) );
		$animation = self::animation( (string) ( $attributes['animation'] ?? 'none' ) );

		if ( '' !== $gap ) {
			$style .= '--cscs-field-gap:' . $gap . ';';
		}

		$extra = '';

		if ( '' !== $animation ) {
			$classes[] = 'cscs-animate';
			$duration  = max( 0, min( 10000, (int) ( $attributes['animationDuration'] ?? 600 ) ) );
			$delay     = max( 0, min( 10000, (int) ( $attributes['animationDelay'] ?? 0 ) ) );
			$style    .= sprintf( '--cscs-animation-duration:%dms;--cscs-animation-delay:%dms;', $duration, $delay );
			$extra     = ' data-cscs-animation="' . esc_attr( $animation ) . '"';
		}

		$own = sprintf(
			'class="%s"%s',
			esc_attr( implode( ' ', $classes ) ),
			'' === $style ? '' : ' style="' . esc_attr( $style ) . '"'
		);

		// The editor hands over the classes and styles the block supports
		// produced. They are merged rather than replaced, because a background
		// colour chosen in the sidebar and a gap chosen here are both real.
		if ( '' !== $wrapper ) {
			return self::merge_attributes( $wrapper, implode( ' ', $classes ), $style ) . $extra;
		}

		return $own . $extra;
	}

	/**
	 * Merges the editor's wrapper attributes with the block's own.
	 *
	 * @param string $wrapper Attributes from the editor.
	 * @param string $classes Classes to add.
	 * @param string $style   Style to add.
	 * @return string
	 */
	private static function merge_attributes( string $wrapper, string $classes, string $style ): string {
		if ( 1 === preg_match( '/class="([^"]*)"/', $wrapper, $found ) ) {
			$wrapper = str_replace(
				$found[0],
				'class="' . esc_attr( trim( $found[1] . ' ' . $classes ) ) . '"',
				$wrapper
			);
		} else {
			$wrapper .= ' class="' . esc_attr( $classes ) . '"';
		}

		if ( '' === $style ) {
			return $wrapper;
		}

		if ( 1 === preg_match( '/style="([^"]*)"/', $wrapper, $found ) ) {
			return str_replace(
				$found[0],
				'style="' . esc_attr( rtrim( $found[1], ';' ) . ';' . $style ) . '"',
				$wrapper
			);
		}

		return $wrapper . ' style="' . esc_attr( $style ) . '"';
	}

	/**
	 * Builds the inline style of one element.
	 *
	 * @param string               $element    `label` or `value`.
	 * @param array<string, mixed> $attributes Settings.
	 * @return string
	 */
	private static function element_style( string $element, array $attributes ): string {
		$read = static function ( string $setting ) use ( $element, $attributes ): string {
			return trim( (string) ( $attributes[ $element . $setting ] ?? '' ) );
		};

		$rules = array(
			'font-size'        => self::length( $read( 'FontSize' ) ),
			'font-family'      => self::font_family( $read( 'FontFamily' ) ),
			'font-weight'      => self::one_of(
				$read( 'FontWeight' ),
				array( '100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold', 'lighter', 'bolder' )
			),
			'font-style'       => self::one_of( $read( 'FontStyle' ), array( 'normal', 'italic', 'oblique' ) ),
			'line-height'      => self::line_height( $read( 'LineHeight' ) ),
			'letter-spacing'   => self::length( $read( 'LetterSpacing' ) ),
			'text-transform'   => self::one_of( $read( 'TextTransform' ), array( 'none', 'uppercase', 'lowercase', 'capitalize' ) ),
			'text-decoration'  => self::one_of( $read( 'TextDecoration' ), array( 'none', 'underline', 'line-through', 'overline' ) ),
			'color'            => self::colour( $read( 'Colour' ) ),
			'text-align'       => self::one_of( $read( 'Align' ), array( 'left', 'center', 'right', 'justify' ) ),
			'margin'           => self::spacing( $read( 'Margin' ) ),
			// Only a table's parts declare these, so for a heading or a value
			// they read as nothing and print nothing.
			'background-color' => self::colour( $read( 'Background' ) ),
			'padding'          => self::spacing( $read( 'Padding' ) ),
		);

		$style = '';

		foreach ( $rules as $property => $value ) {
			if ( '' !== $value ) {
				$style .= $property . ':' . $value . ';';
			}
		}

		return $style;
	}

	/**
	 * Returns a heading tag, or a safe one.
	 *
	 * @param string $tag Tag.
	 * @return string
	 */
	private static function tag( string $tag ): string {
		$allowed = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span', 'strong', 'em', 'figcaption' );

		return in_array( strtolower( $tag ), $allowed, true ) ? strtolower( $tag ) : 'div';
	}

	/**
	 * Returns a CSS length, or nothing.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function length( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		if ( 1 === preg_match( '/^-?\d+(\.\d+)?(px|rem|em|%|vw|vh|pt|ch)$/', $value ) ) {
			return $value;
		}

		if ( 1 === preg_match( '/^-?\d+(\.\d+)?$/', $value ) ) {
			return $value . 'px';
		}

		return self::variable( $value );
	}

	/**
	 * Returns a unitless or unit line height, or nothing.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function line_height( string $value ): string {
		if ( 1 === preg_match( '/^\d+(\.\d+)?$/', $value ) ) {
			return $value;
		}

		return self::length( $value );
	}

	/**
	 * Returns a colour, or nothing.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function colour( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		$hex = sanitize_hex_color( $value );

		if ( is_string( $hex ) && '' !== $hex ) {
			return $hex;
		}

		if ( 1 === preg_match( '/^rgba?\(\s*[\d.]+\s*,\s*[\d.]+\s*,\s*[\d.]+\s*(,\s*[\d.]+\s*)?\)$/', $value ) ) {
			return $value;
		}

		return self::variable( $value );
	}

	/**
	 * Returns a CSS custom property reference, or nothing.
	 *
	 * The theme's own palette and type scale arrive as `var(--wp--preset--…)`,
	 * and refusing them would mean a block that cannot use the colours the site
	 * is built from. The shape is checked rather than trusted: a variable name
	 * and nothing else, so no expression can be smuggled through in one.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function variable( string $value ): string {
		return 1 === preg_match( '/^var\(\s*--[A-Za-z0-9_-]+\s*\)$/', $value ) ? $value : '';
	}

	/**
	 * Returns a value from a list, or nothing.
	 *
	 * @param string             $value   Value.
	 * @param array<int, string> $allowed Allowed values.
	 * @return string
	 */
	private static function one_of( string $value, array $allowed ): string {
		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Returns a font family, or nothing.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function font_family( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		$variable = self::variable( $value );

		if ( '' !== $variable ) {
			return $variable;
		}

		// Family names, commas, and the quotes a name with a space needs.
		return 1 === preg_match( '/^[A-Za-z0-9 ,\'"_-]{1,200}$/', $value ) ? $value : '';
	}

	/**
	 * Returns up to four lengths, as a margin.
	 *
	 * "auto" is accepted as its own keyword alongside a length, because
	 * `margin: 0 auto` — centring a fixed-width photograph — is the one thing
	 * somebody filling in an image margin is most likely to actually want.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function spacing( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		$parts = preg_split( '/\s+/', trim( $value ) );
		$parts = is_array( $parts ) ? array_slice( $parts, 0, 4 ) : array();
		$clean = array();

		foreach ( $parts as $part ) {
			if ( '0' === $part || 'auto' === $part ) {
				$clean[] = $part;
				continue;
			}

			$length = self::length( (string) $part );

			if ( '' === $length ) {
				return '';
			}

			$clean[] = $length;
		}

		return implode( ' ', $clean );
	}

	/**
	 * Returns the list-style rule for a chosen kind of list.
	 *
	 * @param string $style Chosen style.
	 * @return string
	 */
	private static function list_style( string $style ): string {
		$allowed = array( 'disc', 'circle', 'square', 'none', 'ordered' );

		if ( ! in_array( $style, $allowed, true ) ) {
			return '';
		}

		return 'ordered' === $style ? 'list-style-type:decimal;' : 'list-style-type:' . $style . ';';
	}

	/**
	 * Returns an animation name, or nothing.
	 *
	 * @param string $animation Animation.
	 * @return string
	 */
	private static function animation( string $animation ): string {
		$allowed = array( 'fade', 'slide-up', 'slide-down', 'slide-left', 'slide-right', 'zoom' );

		return in_array( $animation, $allowed, true ) ? $animation : '';
	}

	/**
	 * Returns a note only an editor sees.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function notice( string $text ): string {
		return current_user_can( 'edit_posts' )
			? '<p class="cscs-notice">' . esc_html( $text ) . '</p>'
			: '';
	}
}
