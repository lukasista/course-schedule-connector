/**
 * The field modules, in Divi's Visual Builder.
 *
 * Plain browser JavaScript against the globals Divi already exposes, so the
 * plugin ships no compiled file and needs no toolchain between a change and a
 * working builder.
 *
 * Two things here were learned by breaking them, and are worth not learning
 * again. The builder renders with Divi's own copy of React, at
 * `window.vendor.React`; a component built with any other copy may not call a
 * hook, and the mismatch surfaces as the builder's "something went wrong" panel
 * with nothing to say why. So this takes Divi's React and uses no hooks
 * regardless — the preview is filled in through a ref, which is plain React and
 * cannot be got wrong.
 *
 * And the preview is fetched rather than drawn again here. A module that
 * renders itself in React holds its markup twice — once in PHP for the page,
 * once in JavaScript for the builder — and the two drift apart quietly. A field
 * has one definition, and this asks for it.
 */
( function () {
	'use strict';

	var config = window.cscsDiviFields;

	if ( ! config || ! config.modules || ! config.modules.length ) {
		return;
	}

	var vendor = window.vendor || {};
	var React = vendor.React || window.React;
	var hooks = ( vendor.wp && vendor.wp.hooks ) || ( window.wp && window.wp.hooks );
	var divi = window.divi || {};

	var ModuleContainer = divi.module ? divi.module.ModuleContainer : null;
	var elementClassnames = divi.module ? divi.module.elementClassnames : null;
	var registerModule = divi.moduleLibrary ? divi.moduleLibrary.registerModule : null;

	if ( ! React || ! hooks || ! ModuleContainer || ! registerModule ) {
		if ( window.console ) {
			window.console.warn( 'cscs: the Divi builder did not offer what the iSport field modules need.' );
		}

		return;
	}

	var strings = config.strings || {};

	/**
	 * Returns one of the strings the server handed over.
	 *
	 * @param {string} key      Which string.
	 * @param {string} fallback What to say if the server sent none.
	 * @return {string} The text.
	 */
	function say( key, fallback ) {
		return strings[ key ] || fallback;
	}

	/**
	 * Reads one stored setting, the way the server does.
	 *
	 * Divi stores every attribute by breakpoint and state, even one that has
	 * neither.
	 *
	 * @param {Object} attrs    Module attributes.
	 * @param {string} key      Setting name.
	 * @param {string} fallback What it means when nothing is stored.
	 * @return {string} The value.
	 */
	function setting( attrs, key, fallback ) {
		var holder =
			attrs && attrs.field && attrs.field.advanced ? attrs.field.advanced[ key ] : null;
		var value = holder && holder.desktop ? holder.desktop.value : '';

		if ( value && 'object' === typeof value ) {
			value = value[ key ] || '';
		}

		if ( ! value && 0 !== value ) {
			return fallback;
		}

		return String( value );
	}

	/**
	 * Gathers the settings the preview needs, in the renderer's own shape.
	 *
	 * @param {Object} attrs Module attributes.
	 * @return {Object} Settings.
	 */
	function settings( attrs ) {
		return {
			postId: parseInt( setting( attrs, 'source', '0' ), 10 ) || 0,
			showLabel: 'on' === setting( attrs, 'showLabel', 'off' ),
			label: setting( attrs, 'label', '' ),
			labelTag: setting( attrs, 'labelTag', 'h3' ),
			valueTag: setting( attrs, 'valueTag', 'div' ),
			layout: setting( attrs, 'layout', 'stack' ),
			separator: setting( attrs, 'separator', '' ),
			gap: setting( attrs, 'gap', '' ),
			listStyle: setting( attrs, 'listStyle', 'disc' ),
			emptyText: setting( attrs, 'emptyText', '' ),
		};
	}

	/**
	 * Fills one element with the field the server renders.
	 *
	 * Written against the DOM rather than React state, so that no hook is
	 * needed and a reply arriving after the module moved on cannot overwrite
	 * what is on screen: the element remembers what it asked about.
	 *
	 * @param {Element} node  The element to fill.
	 * @param {string}  field Field name.
	 * @param {Object}  args  Settings.
	 * @return {void}
	 */
	function fill( node, field, args ) {
		if ( ! node ) {
			return;
		}

		var query = JSON.stringify( args );
		var asked = field + '|' + query;

		if ( node.getAttribute( 'data-cscs-field' ) === asked ) {
			return;
		}

		node.setAttribute( 'data-cscs-field', asked );

		var url =
			config.preview +
			'?name=' +
			encodeURIComponent( field ) +
			'&settings=' +
			encodeURIComponent( query );

		window
			.fetch( url, {
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': config.nonce },
			} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( body ) {
				// The module may have been changed while the answer was in
				// flight.
				if ( node.getAttribute( 'data-cscs-field' ) !== asked ) {
					return;
				}

				if ( body && body.found && body.html ) {
					node.className = '';
					node.innerHTML = body.html;

					return;
				}

				// The server answering with nothing is not a failure: it is the
				// field saying it has nothing to print, and on the page it will
				// simply not be there. The builder still has to show something,
				// or the module could not be selected again.
				node.className = 'cscs-notice';
				node.textContent = say(
					'empty',
					'This field is empty for this record, so it will not appear on the page.'
				);
			} )
			.catch( function () {
				if ( node.getAttribute( 'data-cscs-field' ) === asked ) {
					node.className = 'cscs-notice';
					node.textContent = say( 'error', 'The field could not be loaded.' );
				}
			} );
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
			elementClassnames( {
				attrs: ( args.attrs && args.attrs.module && args.attrs.module.decoration ) || {},
			} )
		);
	}

	/**
	 * Registers one module.
	 *
	 * @param {Object} metadata Module metadata from the server.
	 * @return {void}
	 */
	function register( metadata ) {
		// "cscs/divi-course-price" → "course-price": the field the server knows.
		var field = String( metadata.name ).replace( /^cscs\/divi-/, '' );

		var module = {
			metadata: metadata,
			placeholderContent: {},

			// Divi writes a chosen value into the structure the defaults
			// describe. Without them there is nothing to write into, and every
			// field refuses every choice without a word.
			defaultAttrs: metadata.defaults || {},
			renderers: {
				edit: function ( props ) {
					var args = settings( props.attrs );

					var preview = React.createElement( 'div', {
						className: 'cscs-notice',
						ref: function ( node ) {
							fill( node, field, args );
						},
					} );

					var children = [];

					// styleComponents is what puts an administrator's design into
					// the preview. If a version of Divi does not offer it, the
					// field is still worth showing.
					//
					// Asking for the sub-elements here as well — title, value,
					// image — was tried and changed nothing: inside the Theme
					// Builder, Divi generates no CSS for these modules at all
					// until the layout is saved, while on an ordinary page it
					// does. The settings are stored and rendered correctly
					// either way; it is the live preview in that one editor
					// that lags, and guessing further at Divi's internals to
					// chase it would cost more than it is worth.
					if ( props.elements && 'function' === typeof props.elements.styleComponents ) {
						children.push( props.elements.styleComponents( { attrName: 'module' } ) );
					}

					children.push( preview );

					return React.createElement(
						ModuleContainer,
						{
							attrs: props.attrs,
							elements: props.elements,
							id: props.id,
							moduleClassName: metadata.moduleClassName,
							name: props.name,
							classnamesFunction: moduleClassnames,
						},
						children
					);
				},
			},
		};

		registerModule( metadata, module );
	}

	hooks.addAction(
		'divi.moduleLibrary.registerModuleLibraryStore.after',
		'cscs.diviFields',
		function () {
			config.modules.forEach( register );
		}
	);
} )();
