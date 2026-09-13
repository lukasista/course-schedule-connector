/**
 * The table column child module, in Divi's Visual Builder.
 *
 * Plain browser JavaScript against the globals Divi already exposes, the same
 * as the plugin's other builder scripts, and for the same reason: no compiled
 * file, no toolchain between a change and a working builder.
 *
 * A column child renders nothing on the page — it exists to be read by its
 * parent, in PHP, the way `FieldModuleRenderer` reads
 * `$block->parsed_block['innerBlocks']`. But the canvas still has to show
 * something for a person to select, open and drag: a card naming which column
 * this is, so the table below it in the layers panel reads the same way the
 * table itself will.
 */
( function () {
	'use strict';

	var config = window.cscsDiviColumn;

	if ( ! config || ! config.name ) {
		return;
	}

	var vendor = window.vendor || {};
	var React = vendor.React || window.React;
	var hooks = ( vendor.wp && vendor.wp.hooks ) || ( window.wp && window.wp.hooks );
	var i18n = ( vendor.wp && vendor.wp.i18n ) || ( window.wp && window.wp.i18n );
	var divi = window.divi || {};

	var ModuleContainer = divi.module ? divi.module.ModuleContainer : null;
	var elementClassnames = divi.module ? divi.module.elementClassnames : null;
	var registerModule = divi.moduleLibrary ? divi.moduleLibrary.registerModule : null;

	if ( ! React || ! hooks || ! ModuleContainer || ! registerModule ) {
		// Divi changed shape under us. Saying so once is more use than a module
		// that silently never appears.
		if ( window.console ) {
			window.console.warn( 'cscs: the Divi builder did not offer what the table column module needs.' );
		}

		return;
	}

	var __ = i18n ? i18n.__ : function ( text ) {
		return text;
	};

	/**
	 * The column options the server sent, keyed the way the select stores them.
	 *
	 * @return {Object} Column key to `{ label }`.
	 */
	function options() {
		var field =
			config.attributes &&
			config.attributes.column &&
			config.attributes.column.settings &&
			config.attributes.column.settings.advanced &&
			config.attributes.column.settings.advanced.field;

		var props = field && field.item && field.item.component && field.item.component.props;

		return ( props && props.options ) || {};
	}

	/**
	 * Reads which column this child names, the way the server does.
	 *
	 * @param {Object} attrs Module attributes.
	 * @return {string} Column key, or an empty string.
	 */
	function chosenColumn( attrs ) {
		var holder =
			attrs && attrs.column && attrs.column.advanced ? attrs.column.advanced.field : null;
		var value = holder && holder.desktop ? holder.desktop.value : '';

		if ( value && 'object' === typeof value ) {
			value = value.field || '';
		}

		return 'string' === typeof value ? value : '';
	}

	/**
	 * Adds the module's classnames, the way the server does.
	 *
	 * @param {Object} args Arguments.
	 * @return {void}
	 */
	function moduleClassnames( args ) {
		if ( ! elementClassnames || ! args || ! args.classnamesInstance ) {
			return;
		}

		args.classnamesInstance.add(
			elementClassnames( { attrs: ( args.attrs && args.attrs.module && args.attrs.module.decoration ) || {} } )
		);
	}

	var module = {
		metadata: config,
		placeholderContent: {},

		// Divi writes a chosen value into the structure the defaults describe.
		// Without them there is nothing to write into, and the field refuses
		// every choice without a word.
		defaultAttrs: config.defaults || {},
		renderers: {
			edit: function ( props ) {
				var column = chosenColumn( props.attrs );
				var label = column
					? ( options()[ column ] && options()[ column ].label ) || column
					: __( 'Choose a column in the module settings.', 'course-schedule-connector' );

				var card = React.createElement( 'div', { className: 'cscs-notice' }, label );

				var children = [];

				if ( props.elements && 'function' === typeof props.elements.styleComponents ) {
					children.push( props.elements.styleComponents( { attrName: 'module' } ) );
				}

				children.push( card );

				return React.createElement(
					ModuleContainer,
					{
						attrs: props.attrs,
						elements: props.elements,
						id: props.id,
						moduleClassName: config.moduleClassName,
						name: props.name,
						classnamesFunction: moduleClassnames,
					},
					children
				);
			},
		},
	};

	hooks.addAction(
		'divi.moduleLibrary.registerModuleLibraryStore.after',
		'cscs.diviColumn',
		function () {
			registerModule( config, module );
		}
	);
} )();
