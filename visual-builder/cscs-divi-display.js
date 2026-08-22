/**
 * The iSport listing module, in Divi's Visual Builder.
 *
 * Plain browser JavaScript against the globals Divi already exposes, so the
 * plugin ships no compiled file and needs no toolchain between a change and a
 * working builder. Divi's own tutorial reads the same globals; the only reason
 * its example needs a build is JSX, and React.createElement says the same thing.
 *
 * Two things here were learned by breaking them.
 *
 * The builder renders with Divi's own copy of React, at window.vendor.React.
 * A component built with any other copy — WordPress's own, for instance — may
 * not call a hook: React tracks hook state per copy, and the mismatch surfaces
 * as the builder's "something went wrong" panel with nothing to say why. So
 * this component takes Divi's React, and uses no hooks regardless: the preview
 * is filled in through a ref, which is plain React and cannot be got wrong.
 *
 * The preview itself is fetched from the server rather than drawn again here. A
 * module that renders itself in React holds its markup twice — once in PHP for
 * the page, once in JavaScript for the builder — and the two drift apart
 * quietly. A listing has one definition, and this asks for it.
 */
( function () {
	'use strict';

	var config = window.cscsDiviModule;

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
			window.console.warn( 'cscs: the Divi builder did not offer what the iSport module needs.' );
		}

		return;
	}

	var __ = i18n ? i18n.__ : function ( text ) {
		return text;
	};

	/**
	 * Reads the chosen set out of the attributes, the way the server does.
	 *
	 * @param {Object} attrs Module attributes.
	 * @return {string} Set id, or an empty string.
	 */
	function chosenSet( attrs ) {
		var set = attrs && attrs.set ? attrs.set : null;

		if ( ! set ) {
			return '';
		}

		// Under `advanced`, where Divi keeps a setting that is not an element's
		// own content; the older place is still read so that a page saved
		// before that was understood keeps working.
		var holder = ( set.advanced && set.advanced.id ) || set.innerContent || null;
		var value = holder && holder.desktop ? holder.desktop.value : '';

		if ( value && 'object' === typeof value ) {
			value = value.id || value.set || '';
		}

		return 'string' === typeof value ? value : '';
	}

	/**
	 * Fills one element with the listing the server renders.
	 *
	 * Written against the DOM rather than React state, so that no hook is
	 * needed and a reply arriving after the module moved on cannot overwrite
	 * what is on screen: the element remembers which set it asked about.
	 *
	 * @param {Element} node The element to fill.
	 * @param {string}  set  Set id.
	 * @return {void}
	 */
	function fill( node, set ) {
		if ( ! node ) {
			return;
		}

		if ( '' === set ) {
			node.textContent = __( 'Choose a display set in the module settings.', 'course-schedule-connector' );
			node.className = 'cscs-notice';

			return;
		}

		if ( node.getAttribute( 'data-cscs-set' ) === set ) {
			return;
		}

		node.setAttribute( 'data-cscs-set', set );
		node.className = 'cscs-notice';
		node.textContent = __( 'Loading the listing…', 'course-schedule-connector' );

		window
			.fetch( config.preview + encodeURIComponent( set ), {
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': config.nonce },
			} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( body ) {
				// The module may have been pointed at something else while the
				// answer was in flight.
				if ( node.getAttribute( 'data-cscs-set' ) !== set ) {
					return;
				}

				if ( body && body.found ) {
					node.className = 'cscs';
					node.innerHTML = body.html;

					return;
				}

				node.className = 'cscs-notice';
				node.textContent = __( 'This display set no longer exists.', 'course-schedule-connector' );
			} )
			.catch( function () {
				if ( node.getAttribute( 'data-cscs-set' ) === set ) {
					node.className = 'cscs-notice';
					node.textContent = __( 'The listing could not be loaded.', 'course-schedule-connector' );
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
			elementClassnames( { attrs: ( args.attrs && args.attrs.module && args.attrs.module.decoration ) || {} } )
		);
	}

	var module = {
		metadata: config,
		placeholderContent: {},
		renderers: {
			edit: function ( props ) {
				var set = chosenSet( props.attrs );

				var preview = React.createElement( 'div', {
					className: 'cscs-notice',
					ref: function ( node ) {
						fill( node, set );
					},
				} );

				var children = [];

				// styleComponents is what puts an administrator's design into
				// the preview. If a version of Divi does not offer it, the
				// listing is still worth showing.
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
		'cscs.diviDisplay',
		function () {
			registerModule( config, module );
		}
	);
} )();
