/**
 * Editing the field blocks.
 *
 * Plain browser JavaScript against the packages WordPress already loads, with
 * no build step — the same choice the listing block made, and for the same
 * reason: a compiled file in the repository is a second copy of the truth, and
 * a toolchain between a change and a working plugin is a toolchain somebody has
 * to keep working.
 *
 * One `edit` serves every field. The blocks differ in what they show, which the
 * server decides, and not in how they are configured, which is what this file
 * is. Sixteen copies of the same inspector would be sixteen places to fix the
 * day somebody wants a new setting on all of them.
 *
 * The preview is the real thing, fetched from the server through the block
 * renderer, so what the editor shows is what the page will show rather than a
 * drawing of it that quietly stops matching.
 */
( function ( blocks, element, components, blockEditor, i18n, data, coreData, serverSideRender ) {
	'use strict';

	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;
	var catalogue = window.cscsFields || {};
	var fields = catalogue.fields || [];
	var postTypes = catalogue.postTypes || {};
	var sizes = catalogue.sizes || [];
	var audience = catalogue.audience || { genders: {}, levels: {} };

	if ( ! fields.length ) {
		return;
	}

	/**
	 * Reads a theme setting, whatever this version of WordPress calls the hook.
	 *
	 * `useSetting` became `useSettings` and the old name is on its way out. A
	 * block that only knows one of them stops offering the theme's own palette
	 * on half the sites it runs on, silently.
	 *
	 * @param {string} path Setting path, e.g. "color.palette".
	 * @return {Array} The setting, or an empty list.
	 */
	function useThemeSetting( path ) {
		var value;

		if ( 'function' === typeof blockEditor.useSettings ) {
			value = blockEditor.useSettings( path )[ 0 ];
		} else if ( 'function' === typeof blockEditor.useSetting ) {
			value = blockEditor.useSetting( path );
		}

		if ( ! value ) {
			return [];
		}

		// A palette may arrive grouped by where it came from — theme, core,
		// custom — rather than as one list.
		if ( Array.isArray( value ) ) {
			return value;
		}

		return [].concat( value.theme || [], value.custom || [], value.default || [] );
	}

	/**
	 * Makes a select control out of a list of plain values.
	 *
	 * @param {Object} props   Block props.
	 * @param {string} attr    Attribute name.
	 * @param {string} label   Control label.
	 * @param {Array}  options Options, {label, value}.
	 * @return {Object} The control.
	 */
	function select( props, attr, label, options ) {
		return el( components.SelectControl, {
			label: label,
			value: props.attributes[ attr ] || '',
			options: options,
			__nextHasNoMarginBottom: true,
			__next40pxDefaultSize: true,
			onChange: function ( value ) {
				var change = {};
				change[ attr ] = value;
				props.setAttributes( change );
			},
		} );
	}

	/**
	 * Makes a text control.
	 *
	 * @param {Object} props Block props.
	 * @param {string} attr  Attribute name.
	 * @param {string} label Control label.
	 * @param {string} help  Help text.
	 * @return {Object} The control.
	 */
	function text( props, attr, label, help ) {
		return el( components.TextControl, {
			label: label,
			help: help,
			value: props.attributes[ attr ] || '',
			__nextHasNoMarginBottom: true,
			__next40pxDefaultSize: true,
			onChange: function ( value ) {
				var change = {};
				change[ attr ] = value;
				props.setAttributes( change );
			},
		} );
	}

	var TAGS = [
		{ label: 'H1', value: 'h1' },
		{ label: 'H2', value: 'h2' },
		{ label: 'H3', value: 'h3' },
		{ label: 'H4', value: 'h4' },
		{ label: 'H5', value: 'h5' },
		{ label: 'H6', value: 'h6' },
		{ label: 'P', value: 'p' },
		{ label: 'DIV', value: 'div' },
		{ label: 'SPAN', value: 'span' },
		{ label: 'STRONG', value: 'strong' },
		{ label: 'EM', value: 'em' },
	];

	function blank( label ) {
		return { label: label || __( '— default —', 'course-schedule-connector' ), value: '' };
	}

	/**
	 * The typography and colour settings of one element.
	 *
	 * The heading and the value each get their own copy. That is the whole
	 * reason this block exists rather than a paragraph with a shortcode in it:
	 * "Price" and "4 160 Kč" are two things a designer wants to treat
	 * differently, and WordPress's own block supports style a block as one.
	 *
	 * @param {Object}  props    Block props.
	 * @param {string}  prefix   "label", "value", "tableHead" or "tableCell".
	 * @param {string}  title    Panel title.
	 * @param {Array}   palette  Theme colours.
	 * @param {Array}   families Theme font families.
	 * @param {boolean} boxed    Whether this element also takes a padding and a
	 *                           background — a table cell does, a heading in a
	 *                           field does not.
	 * @return {Object} The panel.
	 */
	function elementPanel( props, prefix, title, palette, families, boxed ) {
		var familyOptions = [ blank() ].concat(
			families.map( function ( family ) {
				return { label: family.name || family.slug, value: family.fontFamily };
			} )
		);

		return el(
			components.PanelBody,
			{ title: title, initialOpen: false },
			text(
				props,
				prefix + 'FontSize',
				__( 'Size', 'course-schedule-connector' ),
				__( 'A length with its unit — 18px, 1.25rem — or empty to leave it to the theme.', 'course-schedule-connector' )
			),
			familyOptions.length > 1
				? select( props, prefix + 'FontFamily', __( 'Font', 'course-schedule-connector' ), familyOptions )
				: text( props, prefix + 'FontFamily', __( 'Font', 'course-schedule-connector' ) ),
			select( props, prefix + 'FontWeight', __( 'Weight', 'course-schedule-connector' ), [
				blank(),
				{ label: __( 'Light (300)', 'course-schedule-connector' ), value: '300' },
				{ label: __( 'Regular (400)', 'course-schedule-connector' ), value: '400' },
				{ label: __( 'Medium (500)', 'course-schedule-connector' ), value: '500' },
				{ label: __( 'Semibold (600)', 'course-schedule-connector' ), value: '600' },
				{ label: __( 'Bold (700)', 'course-schedule-connector' ), value: '700' },
				{ label: __( 'Black (900)', 'course-schedule-connector' ), value: '900' },
			] ),
			select( props, prefix + 'FontStyle', __( 'Style', 'course-schedule-connector' ), [
				blank(),
				{ label: __( 'Upright', 'course-schedule-connector' ), value: 'normal' },
				{ label: __( 'Italic', 'course-schedule-connector' ), value: 'italic' },
			] ),
			text(
				props,
				prefix + 'LineHeight',
				__( 'Line height', 'course-schedule-connector' ),
				__( 'A number — 1.4 — or a length.', 'course-schedule-connector' )
			),
			text(
				props,
				prefix + 'LetterSpacing',
				__( 'Letter spacing', 'course-schedule-connector' ),
				__( 'A length, which may be negative.', 'course-schedule-connector' )
			),
			select( props, prefix + 'TextTransform', __( 'Capitals', 'course-schedule-connector' ), [
				blank(),
				{ label: __( 'As written', 'course-schedule-connector' ), value: 'none' },
				{ label: __( 'UPPERCASE', 'course-schedule-connector' ), value: 'uppercase' },
				{ label: __( 'lowercase', 'course-schedule-connector' ), value: 'lowercase' },
				{ label: __( 'First Letters', 'course-schedule-connector' ), value: 'capitalize' },
			] ),
			select( props, prefix + 'TextDecoration', __( 'Decoration', 'course-schedule-connector' ), [
				blank(),
				{ label: __( 'None', 'course-schedule-connector' ), value: 'none' },
				{ label: __( 'Underline', 'course-schedule-connector' ), value: 'underline' },
				{ label: __( 'Struck through', 'course-schedule-connector' ), value: 'line-through' },
			] ),
			select( props, prefix + 'Align', __( 'Alignment', 'course-schedule-connector' ), [
				blank(),
				{ label: __( 'Left', 'course-schedule-connector' ), value: 'left' },
				{ label: __( 'Centre', 'course-schedule-connector' ), value: 'center' },
				{ label: __( 'Right', 'course-schedule-connector' ), value: 'right' },
				{ label: __( 'Justified', 'course-schedule-connector' ), value: 'justify' },
			] ),
			text(
				props,
				prefix + 'Margin',
				__( 'Margin', 'course-schedule-connector' ),
				__( 'Up to four lengths, the CSS way: "0 0 4px 0".', 'course-schedule-connector' )
			),
			boxed
				? text(
						props,
						prefix + 'Padding',
						__( 'Padding', 'course-schedule-connector' ),
						__( 'Up to four lengths, the CSS way: "8px 12px".', 'course-schedule-connector' )
				  )
				: null,
			colour( props, prefix + 'Colour', __( 'Colour', 'course-schedule-connector' ), palette ),
			boxed
				? colour( props, prefix + 'Background', __( 'Background', 'course-schedule-connector' ), palette )
				: null
		);
	}

	/**
	 * Makes a colour control out of the theme's own palette.
	 *
	 * @param {Object} props   Block props.
	 * @param {string} attr    Attribute name.
	 * @param {string} label   Control label.
	 * @param {Array}  palette Theme colours.
	 * @return {Object} The control.
	 */
	function colour( props, attr, label, palette ) {
		return el(
			'div',
			{ className: 'cscs-colour' },
			el( 'p', { className: 'components-base-control__label' }, label ),
			el( blockEditor.ColorPalette, {
				colors: palette,
				value: props.attributes[ attr ] || '',
				onChange: function ( value ) {
					var change = {};
					change[ attr ] = value || '';
					props.setAttributes( change );
				},
			} )
		);
	}

	/**
	 * The panel for the marks of a list inside the value.
	 *
	 * The words of an item are already the value's own typography; this is
	 * about the mark in front of them and the space around it. Left alone,
	 * nothing is written and the theme's own list styling stands.
	 *
	 * @param {Object} props   Block props.
	 * @param {Array}  palette The theme's colours.
	 * @return {Object} The panel.
	 */
	function bulletPanel( props, palette ) {
		return el(
			components.PanelBody,
			{ title: __( 'Bullets', 'course-schedule-connector' ), initialOpen: false },
			select( props, 'bulletStyle', __( 'Marked with', 'course-schedule-connector' ), [
				{ label: __( 'As the theme says', 'course-schedule-connector' ), value: '' },
				{ label: __( 'Round', 'course-schedule-connector' ), value: 'disc' },
				{ label: __( 'Hollow', 'course-schedule-connector' ), value: 'circle' },
				{ label: __( 'Square', 'course-schedule-connector' ), value: 'square' },
				{ label: __( 'Numbered', 'course-schedule-connector' ), value: 'decimal' },
				{ label: __( 'Lettered', 'course-schedule-connector' ), value: 'lower-alpha' },
				{ label: __( 'None', 'course-schedule-connector' ), value: 'none' },
			] ),
			colour( props, 'bulletColour', __( 'Mark colour', 'course-schedule-connector' ), palette ),
			text(
				props,
				'bulletIndent',
				__( 'Indent', 'course-schedule-connector' ),
				__( 'How far the list sits from the left — 1.5em, 24px.', 'course-schedule-connector' )
			),
			text(
				props,
				'bulletGap',
				__( 'Between items', 'course-schedule-connector' ),
				__( 'Space added between one item and the next.', 'course-schedule-connector' )
			)
		);
	}

	/**
	 * The panels a field that draws a table gets, on top of the usual ones.
	 *
	 * A table is not one thing. Its heading row, its cells, the links inside
	 * them, the rule between them, the banding behind them and the width of
	 * each column are each what somebody means when they say the table looks
	 * wrong, and until now none of them could be reached from here.
	 *
	 * @param {Object} props    Block props.
	 * @param {Object} field    Field description from the server.
	 * @param {Array}  palette  Theme colours.
	 * @param {Array}  families Theme font families.
	 * @return {Array} The panels.
	 */
	function tablePanels( props, field, palette, families ) {
		var columns = field.columns || [];

		if ( ! columns.length ) {
			return [];
		}

		return [
			elementPanel(
				props,
				'tableHead',
				__( 'Table heading', 'course-schedule-connector' ),
				palette,
				families,
				true
			),
			elementPanel(
				props,
				'tableCell',
				__( 'Table cell', 'course-schedule-connector' ),
				palette,
				families,
				true
			),
			el(
				components.PanelBody,
				{ title: __( 'Table link', 'course-schedule-connector' ), initialOpen: false },
				select( props, 'tableLinkFontWeight', __( 'Weight', 'course-schedule-connector' ), [
					blank(),
					{ label: __( 'Regular (400)', 'course-schedule-connector' ), value: '400' },
					{ label: __( 'Semibold (600)', 'course-schedule-connector' ), value: '600' },
					{ label: __( 'Bold (700)', 'course-schedule-connector' ), value: '700' },
				] ),
				select( props, 'tableLinkTextDecoration', __( 'Decoration', 'course-schedule-connector' ), [
					blank(),
					{ label: __( 'None', 'course-schedule-connector' ), value: 'none' },
					{ label: __( 'Underline', 'course-schedule-connector' ), value: 'underline' },
				] ),
				colour( props, 'tableLinkColour', __( 'Colour', 'course-schedule-connector' ), palette ),
				el(
					'p',
					{ className: 'components-base-control__help' },
					__( 'Left alone, a link keeps the colour the theme gives it.', 'course-schedule-connector' )
				)
			),
			el(
				components.PanelBody,
				{ title: __( 'Lines and banding', 'course-schedule-connector' ), initialOpen: false },
				text(
					props,
					'tableLineWidth',
					__( 'Line thickness', 'course-schedule-connector' ),
					__( 'The rule under each row. A length — 1px, 2px.', 'course-schedule-connector' )
				),
				colour( props, 'tableLineColour', __( 'Line colour', 'course-schedule-connector' ), palette ),
				colour( props, 'tableStripe', __( 'Every other row', 'course-schedule-connector' ), palette ),
				el(
					'p',
					{ className: 'components-base-control__help' },
					__( 'A colour here bands the table: every second row takes it.', 'course-schedule-connector' )
				)
			),
			el(
				components.PanelBody,
				{ title: __( 'Columns', 'course-schedule-connector' ), initialOpen: false },
				columns.map( function ( column ) {
					return el(
						Fragment,
						{ key: column.key },
						text(
							props,
							column.attribute + 'Width',
							i18n.sprintf(
								/* translators: %s: the name of a column, "Price" and the like. */
								__( 'Width of %s', 'course-schedule-connector' ),
								column.label
							),
							__( 'A length or a percentage. Empty lets the table decide.', 'course-schedule-connector' )
						),
						select(
							props,
							column.attribute + 'Align',
							i18n.sprintf(
								/* translators: %s: the name of a column, "Price" and the like. */
								__( 'Alignment of %s', 'course-schedule-connector' ),
								column.label
							),
							[
								blank(),
								{ label: __( 'Left', 'course-schedule-connector' ), value: 'left' },
								{ label: __( 'Centre', 'course-schedule-connector' ), value: 'center' },
								{ label: __( 'Right', 'course-schedule-connector' ), value: 'right' },
							]
						)
					);
				} )
			),
		];
	}

	/**
	 * The panel that decides which course or trainer the block is about.
	 *
	 * @param {Object} props    Block props.
	 * @param {string} postType Post type this field belongs to.
	 * @return {Object} The panel.
	 */
	function sourcePanel( props, postType ) {
		var posts = data.useSelect(
			function ( pick ) {
				return pick( coreData.store ).getEntityRecords( 'postType', postType, {
					per_page: 100,
					status: 'publish',
					orderby: 'title',
					order: 'asc',
					_fields: 'id,title',
				} );
			},
			[ postType ]
		);

		var options = [
			{
				label: __( 'The one this page is about', 'course-schedule-connector' ),
				value: 0,
			},
		];

		( posts || [] ).forEach( function ( post ) {
			options.push( {
				label: ( post.title && post.title.rendered ) || '#' + post.id,
				value: post.id,
			} );
		} );

		return el(
			components.PanelBody,
			{ title: __( 'What this shows', 'course-schedule-connector' ), initialOpen: true },
			el( components.SelectControl, {
				label: __( 'Source', 'course-schedule-connector' ),
				value: String( props.attributes.postId || 0 ),
				options: options.map( function ( option ) {
					return { label: option.label, value: String( option.value ) };
				} ),
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
				onChange: function ( value ) {
					props.setAttributes( { postId: parseInt( value, 10 ) || 0 } );
				},
			} ),
			el(
				'p',
				{ className: 'components-base-control__help' },
				__(
					'Left as it is, the block shows whichever course or trainer the page is about — which is what a template in a theme builder wants, and what makes one design serve them all.',
					'course-schedule-connector'
				)
			)
		);
	}

	/**
	 * Turns a vocabulary into a select's options, with an "any" first.
	 *
	 * @param {Object} words Key to label.
	 * @param {string} any   What the empty choice is called.
	 * @return {Array} Options.
	 */
	function choices( words, any ) {
		var options = [ { label: any, value: '' } ];

		Object.keys( words || {} ).forEach( function ( key ) {
			options.push( { label: words[ key ], value: key } );
		} );

		return options;
	}

	/**
	 * Which of the courses under this heading to print.
	 *
	 * A kind of course is what the gym calls a card — "Gymnastika" — and one
	 * card is sometimes wanted as two: the girls' hours and the boys'. Filing
	 * twenty-two courses under a kind invented to hold half of them is a lot of
	 * clicking to say something the courses already say, so the block asks
	 * instead, and the same kind can appear twice on a page saying two
	 * different things.
	 *
	 * @param {Object} props Block props.
	 * @return {Object} The panel.
	 */
	function coursesPanel( props ) {
		return el(
			components.PanelBody,
			{ title: __( 'Which courses', 'course-schedule-connector' ), initialOpen: false },
			select(
				props,
				'filterGenders',
				__( 'Who the course is for', 'course-schedule-connector' ),
				choices( audience.genders, __( 'Everybody', 'course-schedule-connector' ) )
			),
			select(
				props,
				'filterLevels',
				__( 'At what level', 'course-schedule-connector' ),
				choices( audience.levels, __( 'Every level', 'course-schedule-connector' ) )
			),
			text(
				props,
				'filterAgeMin',
				__( 'Age from', 'course-schedule-connector' ),
				__( 'Leaves out courses that finish below this age. Empty means no floor.', 'course-schedule-connector' )
			),
			text(
				props,
				'filterAgeMax',
				__( 'Age to', 'course-schedule-connector' ),
				__( 'Leaves out courses that start above this age. Empty means no ceiling.', 'course-schedule-connector' )
			),
			select( props, 'filterSort', __( 'Order by', 'course-schedule-connector' ), [
				{ label: __( 'As they are filed', 'course-schedule-connector' ), value: '' },
				{ label: __( 'Name', 'course-schedule-connector' ), value: 'name' },
				{ label: __( 'When it starts', 'course-schedule-connector' ), value: 'start' },
				{ label: __( 'Price', 'course-schedule-connector' ), value: 'price' },
				{ label: __( 'Places free', 'course-schedule-connector' ), value: 'places' },
			] ),
			select( props, 'filterOrder', __( 'Which way', 'course-schedule-connector' ), [
				{ label: __( 'Ascending', 'course-schedule-connector' ), value: 'asc' },
				{ label: __( 'Descending', 'course-schedule-connector' ), value: 'desc' },
			] ),
			el( components.TextControl, {
				label: __( 'At most', 'course-schedule-connector' ),
				help: __( 'How many rows to print. Empty or zero means all of them.', 'course-schedule-connector' ),
				type: 'number',
				min: 0,
				value: props.attributes.filterLimit || 0,
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
				onChange: function ( value ) {
					props.setAttributes( { filterLimit: parseInt( value, 10 ) || 0 } );
				},
			} )
		);
	}

	/**
	 * Builds the link panel.
	 *
	 * The shape is the block: one for a button, one for a link. All that is
	 * left to say here is what it says.
	 *
	 * @param {Object} props Block props.
	 * @return {Object} The panel.
	 */
	function linkPanel( props ) {
		return el(
			components.PanelBody,
			{ title: __( 'Link', 'course-schedule-connector' ), initialOpen: true },
			text(
				props,
				'linkText',
				__( 'Link text', 'course-schedule-connector' ),
				__( 'Left empty, the wording set in iSport → Settings is used.', 'course-schedule-connector' )
			)
		);
	}

	/**
	 * The settings a picture has and a price does not.
	 *
	 * A field showing an image is a different shape from one showing a number,
	 * and giving both the same panel is what left the photograph without the
	 * settings anybody would look for on it: which size to serve, what it says
	 * to a reader who cannot see it, and whether it is a link.
	 *
	 * @param {Object} props Block props.
	 * @return {Object} The panel.
	 */
	function picturePanel( props ) {
		var links = props.attributes.imageLink || 'none';

		return el(
			components.PanelBody,
			{ title: __( 'Picture', 'course-schedule-connector' ), initialOpen: false },
			select(
				props,
				'imageSize',
				__( 'Size', 'course-schedule-connector' ),
				sizes.map( function ( size ) {
					return { label: size, value: size };
				} )
			),
			el(
				'p',
				{ className: 'components-base-control__help' },
				__(
					'Larger is not better: a portrait shown at 300 pixels costs the visitor nothing extra if 300 pixels is what is sent.',
					'course-schedule-connector'
				)
			),
			text(
				props,
				'imageAlt',
				__( 'Alternative text', 'course-schedule-connector' ),
				__(
					'What the picture says to somebody who cannot see it. Empty means the name of the course or trainer, which is usually right.',
					'course-schedule-connector'
				)
			),
			select( props, 'imageLink', __( 'Links to', 'course-schedule-connector' ), [
				{ label: __( 'Nowhere', 'course-schedule-connector' ), value: 'none' },
				{ label: __( 'Its own page', 'course-schedule-connector' ), value: 'post' },
				{ label: __( 'The picture at full size', 'course-schedule-connector' ), value: 'file' },
				{ label: __( 'An address of your own', 'course-schedule-connector' ), value: 'custom' },
			] ),
			'custom' === links
				? text( props, 'imageLinkUrl', __( 'Address', 'course-schedule-connector' ) )
				: null,
			'none' === links
				? null
				: el( components.ToggleControl, {
						label: __( 'Open in a new window', 'course-schedule-connector' ),
						help: __(
							'A new window is a surprise, so it is off unless somebody asks for it.',
							'course-schedule-connector'
						),
						checked: !! props.attributes.imageLinkTarget,
						__nextHasNoMarginBottom: true,
						onChange: function ( value ) {
							props.setAttributes( { imageLinkTarget: value } );
						},
				  } )
		);
	}

	/**
	 * Registers one field block.
	 *
	 * @param {Object} field Field description from the server.
	 * @return {void}
	 */
	function registerField( field ) {
		var postType = postTypes[ field.context ];

		blocks.registerBlockType( field.name, {
			edit: function ( props ) {
				// A field whose label is null has no heading at all — the name
				// of a course is already one, and a photograph and a button are
				// not things a heading sits above. None of the settings that
				// describe one are drawn for those.
				var heads = false !== field.heads;
				var palette = useThemeSetting( 'color.palette' );
				var families = useThemeSetting( 'typography.fontFamilies' );
				var blockProps = blockEditor.useBlockProps();

				var controls = el(
					Fragment,
					{},
					el(
						blockEditor.InspectorControls,
						{},
						sourcePanel( props, postType ),
						field.filters ? coursesPanel( props ) : null,
						field.signup ? linkPanel( props ) : null,
						field.image ? picturePanel( props ) : null,
						el(
							components.PanelBody,
							{ title: __( 'Content', 'course-schedule-connector' ), initialOpen: true },
							text(
								props,
								'emptyText',
								__( 'When there is nothing to show', 'course-schedule-connector' ),
								__( 'Left empty, the block disappears rather than printing a heading over a blank space.', 'course-schedule-connector' )
							)
						)
					),
					el(
					blockEditor.InspectorControls,
					{ group: 'styles' },
					el(
						components.PanelBody,
						{
							title: heads
								? __( 'Heading and layout', 'course-schedule-connector' )
								: __( 'Value', 'course-schedule-connector' ),
							initialOpen: false,
						},
						heads
							? el( components.ToggleControl, {
									label: __( 'Show a heading', 'course-schedule-connector' ),
									checked: !! props.attributes.showLabel,
									__nextHasNoMarginBottom: true,
									onChange: function ( value ) {
										props.setAttributes( { showLabel: value } );
									},
							  } )
							: null,
						heads && props.attributes.showLabel
							? text(
									props,
									'label',
									__( 'Heading', 'course-schedule-connector' ),
									field.label
										? i18n.sprintf(
												/* translators: %s: the heading the field uses by default. */
												__( 'Empty means “%s”.', 'course-schedule-connector' ),
												field.label
										  )
										: __( 'What to call this field.', 'course-schedule-connector' )
							  )
							: null,
						heads && props.attributes.showLabel
							? select( props, 'labelTag', __( 'Heading element', 'course-schedule-connector' ), TAGS )
							: null,
						heads && props.attributes.showLabel
							? text(
									props,
									'separator',
									__( 'After the heading', 'course-schedule-connector' ),
									__( 'A colon, a dash — printed right after the heading. Useful side by side.', 'course-schedule-connector' )
							  )
							: null,
						select( props, 'valueTag', __( 'Value element', 'course-schedule-connector' ), TAGS ),
						heads
							? select( props, 'layout', __( 'Arrangement', 'course-schedule-connector' ), [
									{ label: __( 'Heading above', 'course-schedule-connector' ), value: 'stack' },
									{ label: __( 'Side by side', 'course-schedule-connector' ), value: 'inline' },
							  ] )
							: null,
						heads
							? text(
									props,
									'gap',
									__( 'Gap', 'course-schedule-connector' ),
									__( 'Between the heading and the value.', 'course-schedule-connector' )
							  )
							: null,
						'list' === field.kind
							? select( props, 'listStyle', __( 'Bullets', 'course-schedule-connector' ), [
									{ label: __( 'Round', 'course-schedule-connector' ), value: 'disc' },
									{ label: __( 'Hollow', 'course-schedule-connector' ), value: 'circle' },
									{ label: __( 'Square', 'course-schedule-connector' ), value: 'square' },
									{ label: __( 'Numbered', 'course-schedule-connector' ), value: 'ordered' },
									{ label: __( 'None', 'course-schedule-connector' ), value: 'none' },
							  ] )
							: null,
					),
					heads
						? elementPanel(
								props,
								'label',
								__( 'Heading text', 'course-schedule-connector' ),
								palette,
								families
						  )
						: null,
					elementPanel(
						props,
						'value',
						__( 'Value text', 'course-schedule-connector' ),
						palette,
						families
					),
					field.bullets ? bulletPanel( props, palette ) : null,
					tablePanels( props, field, palette, families ),
					el(
						components.PanelBody,
						{ title: __( 'Animation', 'course-schedule-connector' ), initialOpen: false },
						select( props, 'animation', __( 'On first sight', 'course-schedule-connector' ), [
							{ label: __( 'None', 'course-schedule-connector' ), value: 'none' },
							{ label: __( 'Fade in', 'course-schedule-connector' ), value: 'fade' },
							{ label: __( 'Rise', 'course-schedule-connector' ), value: 'slide-up' },
							{ label: __( 'Fall', 'course-schedule-connector' ), value: 'slide-down' },
							{ label: __( 'From the left', 'course-schedule-connector' ), value: 'slide-left' },
							{ label: __( 'From the right', 'course-schedule-connector' ), value: 'slide-right' },
							{ label: __( 'Grow', 'course-schedule-connector' ), value: 'zoom' },
						] ),
						'none' !== ( props.attributes.animation || 'none' )
							? el( components.RangeControl, {
									label: __( 'Duration (ms)', 'course-schedule-connector' ),
									value: props.attributes.animationDuration,
									min: 0,
									max: 3000,
									step: 50,
									__nextHasNoMarginBottom: true,
									__next40pxDefaultSize: true,
									onChange: function ( value ) {
										props.setAttributes( { animationDuration: value } );
									},
							  } )
							: null,
						'none' !== ( props.attributes.animation || 'none' )
							? el( components.RangeControl, {
									label: __( 'Delay (ms)', 'course-schedule-connector' ),
									value: props.attributes.animationDelay,
									min: 0,
									max: 3000,
									step: 50,
									__nextHasNoMarginBottom: true,
									__next40pxDefaultSize: true,
									onChange: function ( value ) {
										props.setAttributes( { animationDelay: value } );
									},
							  } )
							: null,
						el(
							'p',
							{ className: 'components-base-control__help' },
							__(
								'Animations run when the block first comes into view, and never for a visitor who has asked their computer to keep still.',
								'course-schedule-connector'
							)
						)
					)
					)
				);

				return el(
					Fragment,
					{},
					controls,
					el(
						'div',
						blockProps,
						el( serverSideRender, {
							block: field.name,
							attributes: props.attributes,
							// An empty answer is not a failure: it is the field
							// saying it has nothing to print, and on the page it
							// will simply not be there. The editor still has to
							// show something, or the block could not be selected
							// again.
							EmptyResponsePlaceholder: function () {
								return el(
									components.Placeholder,
									{
										icon: field.icon,
										label: field.title,
										instructions: __(
											'This field is empty for this record, so it will not appear on the page.',
											'course-schedule-connector'
										),
									}
								);
							},
						} )
					)
				);
			},

			// Rendered on the server, so nothing is saved into the post but the
			// settings themselves. A change to the markup then reaches every
			// page that was ever built with the block, rather than only the
			// ones edited since.
			save: function () {
				return null;
			},
		} );
	}

	fields.forEach( registerField );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.components,
	window.wp.blockEditor,
	window.wp.i18n,
	window.wp.data,
	window.wp.coreData,
	window.wp.serverSideRender
);
