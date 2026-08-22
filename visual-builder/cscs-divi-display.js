/**
 * The iSport listing module, in Divi's Visual Builder.
 *
 * Plain browser JavaScript against the globals Divi already exposes, so the
 * plugin ships no compiled file and needs no toolchain between a change and a
 * working builder. Divi's own tutorial reads the same globals; the only reason
 * its example needs a build is JSX, and React.createElement says the same thing.
 *
 * The preview is fetched from the server rather than drawn again here. A module
 * that renders itself in React holds its markup twice — once in PHP for the
 * page, once in JavaScript for the builder — and the two drift apart quietly.
 * A listing has one definition, and this asks for it.
 */
( function () {
	'use strict';

	var metadata = window.cscsDiviModule;

	if ( ! metadata || ! metadata.name ) {
		return;
	}

	var React = window.React;
	var addAction = window.vendor && window.vendor.wp && window.vendor.wp.hooks
		? window.vendor.wp.hooks.addAction
		: window.wp.hooks.addAction;
	var apiFetch = window.wp.apiFetch;
	var __ = window.wp.i18n.__;

	var divi = window.divi || {};
	var ModuleContainer = divi.module ? divi.module.ModuleContainer : null;
	var registerModule = divi.moduleLibrary ? divi.moduleLibrary.registerModule : null;
	var elementClassnames = divi.module ? divi.module.elementClassnames : null;

	if ( ! ModuleContainer || ! registerModule ) {
		return;
	}

	/**
	 * Reads the chosen set out of the attributes, the way the server does.
	 *
	 * @param {Object} attrs Module attributes.
	 * @return {string} Set id, or an empty string.
	 */
	function chosenSet( attrs ) {
		var value = attrs && attrs.set && attrs.set.innerContent && attrs.set.innerContent.desktop
			? attrs.set.innerContent.desktop.value
			: '';

		if ( value && 'object' === typeof value ) {
			value = value.set || '';
		}

		return value || '';
	}

	/**
	 * The listing, fetched from the server and shown as it will appear.
	 *
	 * @param {Object} props Component props.
	 * @return {Object} React element.
	 */
	function Preview( props ) {
		var state = React.useState( { loading: true, html: '', set: null } );
		var view = state[ 0 ];
		var setView = state[ 1 ];
		var set = props.set;

		React.useEffect( function () {
			var cancelled = false;

			if ( ! set ) {
				setView( { loading: false, html: '', set: set } );

				return function () {};
			}

			setView( { loading: true, html: '', set: set } );

			apiFetch( { path: '/cscs/v1/preview?set=' + encodeURIComponent( set ) } )
				.then( function ( response ) {
					if ( ! cancelled ) {
						setView( { loading: false, html: response.found ? response.html : '', set: set } );
					}
				} )
				.catch( function () {
					if ( ! cancelled ) {
						setView( { loading: false, html: '', set: set } );
					}
				} );

			// The builder re-renders on every keystroke elsewhere on the page;
			// a reply that arrives after the module moved on must not overwrite
			// what is on screen.
			return function () {
				cancelled = true;
			};
		}, [ set ] );

		if ( ! set ) {
			return React.createElement(
				'p',
				{ className: 'cscs-notice' },
				__( 'Choose a display set in the module settings.', 'course-schedule-connector' )
			);
		}

		if ( view.loading ) {
			return React.createElement(
				'p',
				{ className: 'cscs-notice' },
				__( 'Loading the listing…', 'course-schedule-connector' )
			);
		}

		if ( '' === view.html ) {
			return React.createElement(
				'p',
				{ className: 'cscs-notice' },
				__( 'This display set no longer exists.', 'course-schedule-connector' )
			);
		}

		return React.createElement( 'div', { dangerouslySetInnerHTML: { __html: view.html } } );
	}

	/**
	 * Adds the module's classnames, the way the server does.
	 *
	 * @param {Object} args Arguments.
	 * @return {void}
	 */
	function moduleClassnames( args ) {
		if ( ! elementClassnames ) {
			return;
		}

		args.classnamesInstance.add(
			elementClassnames( { attrs: ( args.attrs && args.attrs.module && args.attrs.module.decoration ) || {} } )
		);
	}

	var module = {
		metadata: metadata,
		placeholderContent: {},
		renderers: {
			edit: function ( props ) {
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
					props.elements.styleComponents( { attrName: 'module' } ),
					React.createElement( Preview, { set: chosenSet( props.attrs ) } )
				);
			},
		},
	};

	addAction(
		'divi.moduleLibrary.registerModuleLibraryStore.after',
		'cscs.diviDisplay',
		function () {
			registerModule( metadata, module );
		}
	);
} )();
