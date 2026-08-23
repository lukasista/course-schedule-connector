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
	var StyleContainer = divi.module ? divi.module.StyleContainer : null;
	var CssStyle = divi.module ? divi.module.CssStyle : null;
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
	 * Builds the part of a module that writes its CSS in the builder.
	 *
	 * This is the half of a Divi module that is easy to leave out, because nothing
	 * complains: without it a module registers, opens, offers every design setting
	 * and stores every value, and the canvas simply never changes. The settings
	 * only appear once the page is saved and the front end renders them, which
	 * makes a builder that cannot be designed in.
	 *
	 * Two things have to be right, and both are quiet when they are not. Every
	 * attribute that carries styles has to declare an `elementType` in its
	 * `module.json` — Divi decides from it which style components an attribute
	 * gets, and an attribute without one gets none. And the module's order class
	 * has to be on the elements before the styles are asked for, or the rules come
	 * out as ` .cscs-field__label`, with nothing in front of the space, and apply
	 * to every field on the page or to none.
	 *
	 * What it emits is the same list as {@see \CSCS\Divi\FieldModuleRenderer}
	 * emits in PHP, and for the same reason: the module itself, the heading and the
	 * value styled apart from each other, the picture where the field is one, and
	 * whatever custom CSS was written. The two have to agree, or the builder shows
	 * one design and the page another.
	 *
	 * @param {Object} metadata Module metadata from the server.
	 * @return {Function} The renderer.
	 */
	function stylesRenderer( metadata ) {
		// Only the picture fields declare an `image` element; asking for its
		// styles anywhere else would be asking about a selector that is not on
		// the page.
		var picture = !! ( metadata.attributes && metadata.attributes.image );

		return function ( props ) {
			var elements = props.elements;
			var attrs = props.attrs || {};
			var settings = props.settings || {};
			var orderClass = props.orderClass;
			var children = [];

			if ( ! elements || 'function' !== typeof elements.style ) {
				return null;
			}

			// Divi names the classes itself when it renders a module's styles on
			// their own. Inside the edit tree, where these are rendered, it has
			// not done it yet.
			if ( ! orderClass ) {
				orderClass = '.' + metadata.moduleOrderClassName + '_' + props.id;

				elements.setBaseOrderClass( '.' + metadata.moduleOrderClassName );
				elements.setOrderClass( orderClass );
				elements.setModuleNameClass( '.' + metadata.moduleClassName );
			}

			children.push(
				elements.style( {
					attrName: 'module',
					styleProps: {
						disabledOn: {
							disabledModuleVisibility: settings.disabledModuleVisibility,
						},
					},
				} )
			);

			children.push( elements.style( { attrName: 'title' } ) );
			children.push( elements.style( { attrName: 'value' } ) );

			if ( picture ) {
				children.push( elements.style( { attrName: 'image' } ) );
			}

			if ( CssStyle ) {
				children.push(
					React.createElement( CssStyle, {
						key: 'cscs-css',
						selector: orderClass,
						attr: attrs.css,
					} )
				);
			}

			if ( ! StyleContainer ) {
				return React.createElement( React.Fragment, null, children );
			}

			return React.createElement(
				StyleContainer,
				{
					mode: props.mode,
					state: props.state,
					noStyleTag: props.noStyleTag,
					isInsideStickyModule: props.isInsideStickyModule,
					stickyParentOrderClass: props.stickyParentOrderClass,
				},
				children
			);
		};
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

		// Built once: the renderer is the same for every copy of the module on
		// the page, and Divi hands it whichever one it is drawing.
		var styles = stylesRenderer( metadata );

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
						[ styles( props ), preview ]
					);
				},

				// Divi asks for this when it draws a module's styles on their own.
				// It does not do that for a module registered from a plugin — hence
				// the call in `edit` above, which is what actually reaches the
				// canvas — but a module that answers the question honestly costs
				// nothing and stops being wrong the day Divi asks.
				styles: styles,
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
