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
	var ChildModulesContainer = divi.module ? divi.module.ChildModulesContainer : null;
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
	 * The first ten are named by hand because three of them are not the
	 * server's own key (`source` becomes `postId`) or not its own shape
	 * (`showLabel` is `"on"`/`"off"` in Divi and a boolean in the renderer) —
	 * a generic pass cannot know either of those on its own.
	 *
	 * Everything past them is read generically, from the same schema the
	 * panel itself is built from (`metadata.attributes.field.settings.advanced`),
	 * with its fallback read from the same defaults Divi writes a chosen
	 * value into (`metadata.defaults`). This is not a style choice: a
	 * hand-written list here is a second place PHP's own
	 * `FieldModuleRenderer::settings()` has to be kept in sync with by hand,
	 * and it already fell behind once — `imageSize`, every filter, and both
	 * `cardsLayout` and `cardLayout` were never in it, so the canvas always
	 * previewed a field of cards, a photograph, or a filtered listing with
	 * nothing but their built-in defaults, however the panel was actually
	 * set. A setting the panel offers is now a setting this asks about,
	 * without anyone having to remember to add it here too.
	 *
	 * @param {Object} attrs    Module attributes.
	 * @param {Object} metadata Module metadata from the server.
	 * @return {Object} Settings.
	 */
	function settings( attrs, metadata ) {
		var advanced =
			( metadata.attributes &&
				metadata.attributes.field &&
				metadata.attributes.field.settings &&
				metadata.attributes.field.settings.advanced ) ||
			{};
		var defaults =
			( metadata.defaults && metadata.defaults.field && metadata.defaults.field.advanced ) || {};
		var result = {
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
			imageLinkTarget: 'on' === setting( attrs, 'imageLinkTarget', 'off' ),
			filterLimit: parseInt( setting( attrs, 'filterLimit', '0' ), 10 ) || 0,
		};
		// Named above, under a different key, in a different shape, or not a
		// plain per-field setting at all — a generic pass must not repeat
		// any of these under their own name.
		var handled = {
			source: true,
			showLabel: true,
			label: true,
			labelTag: true,
			valueTag: true,
			layout: true,
			separator: true,
			gap: true,
			listStyle: true,
			emptyText: true,
			imageLinkTarget: true,
			filterLimit: true,
		};
		var key;

		for ( key in advanced ) {
			if ( ! Object.prototype.hasOwnProperty.call( advanced, key ) || handled[ key ] ) {
				continue;
			}

			result[ key ] = setting(
				attrs,
				key,
				defaults[ key ] && defaults[ key ].desktop ? String( defaults[ key ].desktop.value ) : ''
			);
		}

		return result;
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
	 * Puts the plugin's modules on a shelf of their own.
	 *
	 * Divi's module list is alphabetical, and twenty-four modules spread
	 * through it are not a set anybody can find. A folder is how Divi answers
	 * that for WooCommerce, and the modules ask to be in it by carrying a
	 * `folder` key. Both of the plugin's builder scripts do this, because
	 * either may load first and neither can wait for the other; registering the
	 * same folder twice registers the same folder.
	 *
	 * @return {void}
	 */
	function registerOwnFolder() {
		var folders = window.cscsDiviFolder;
		var register = divi.moduleLibrary ? divi.moduleLibrary.registerFolder : null;
		var i;

		if ( ! folders || 'function' !== typeof register ) {
			return;
		}

		// One shelf and three drawers: the shelf first, because a drawer whose
		// shelf does not exist yet is a drawer nobody can open.
		for ( i = 0; i < folders.length; i++ ) {
			if ( folders[ i ] && folders[ i ].name ) {
				register( folders[ i ] );
			}
		}
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
		// Which parts this field has is the metadata's answer, not this file's:
		// a heading and a value always, a picture on the picture fields, and on
		// a field that draws a table its heading row, its cells, its links, its
		// banding and each of its columns. An attribute that carries styles is
		// exactly one that declares what kind of element it is.
		var styled = Object.keys( metadata.attributes || {} ).filter( function ( key ) {
			return !! ( metadata.attributes[ key ] && metadata.attributes[ key ].elementType );
		} );

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

			styled.forEach( function ( key ) {
				children.push( elements.style( { attrName: key } ) );
			} );

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
					var args = settings( props.attrs, metadata );

					var preview = React.createElement( 'div', {
						className: 'cscs-notice',
						ref: function ( node ) {
							fill( node, field, args );
						},
					} );

					var children = [ styles( props ), preview ];

					// A field that names a child module (`course-schedule` today)
					// builds its table from whichever of those children are
					// present, in the order they were dragged into — the exact
					// order `Fields::columns_from_children()` reads on the server.
					// Divi does not draw a module's children on its own; a parent
					// has to ask for them by id, the same way its own Contact Form
					// asks for its Contact Fields.
					if (
						ChildModulesContainer &&
						metadata.childrenName &&
						metadata.childrenName.length &&
						props.childrenIds &&
						props.childrenIds.length
					) {
						children.push(
							React.createElement( ChildModulesContainer, {
								key: 'cscs-columns',
								ids: props.childrenIds,
								isLooped: props.isLooped,
								loopIndex: props.loopIndex,
								canvasId: props.canvasId,
							} )
						);
					}

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
			registerOwnFolder();

			config.modules.forEach( register );
		}
	);
} )();
