/**
 * Editing the Courses and schedule block.
 *
 * Written as plain browser JavaScript against the packages WordPress already
 * loads, with no build step. A build would mean a compiled file in the
 * repository or a toolchain between a change and a working plugin, and this
 * block is one select box: the whole point of it is that a site manager has one
 * decision to make, and the code that offers it should be as small as the
 * decision.
 *
 * The preview is the real thing. It comes from the server through the block
 * renderer, so what the editor shows is what the page will show, rather than a
 * drawing of it that quietly stops matching.
 */
( function ( blocks, element, components, blockEditor, i18n, serverSideRender ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var sets = window.cscsBlockSets || [];

	blocks.registerBlockType( 'cscs/display', {
		edit: function ( props ) {
			var chosen = props.attributes.set || '';

			var controls = el(
				blockEditor.InspectorControls,
				{},
				el(
					components.PanelBody,
					{ title: __( 'Display set', 'course-schedule-connector' ) },
					el( components.SelectControl, {
						label: __( 'Which set to show', 'course-schedule-connector' ),
						value: chosen,
						options: sets,
						__nextHasNoMarginBottom: true,
						onChange: function ( value ) {
							props.setAttributes( { set: value } );
						},
					} ),
					el(
						'p',
						{ className: 'components-base-control__help' },
						__(
							'What the set shows — the columns, the rooms, how far ahead — is changed under iSport, Display sets. Every page using the set follows.',
							'course-schedule-connector'
						)
					)
				)
			);

			// Nothing chosen yet: say so here rather than asking the server to
			// render a listing that cannot exist.
			if ( '' === chosen ) {
				return el(
					element.Fragment,
					{},
					controls,
					el(
						components.Placeholder,
						{
							icon: 'calendar-alt',
							label: __( 'Courses and schedule', 'course-schedule-connector' ),
							instructions:
								sets.length > 1
									? __( 'Choose which display set this listing shows.', 'course-schedule-connector' )
									: __(
											'No display sets yet. Make one under iSport, Display sets, and it will appear here.',
											'course-schedule-connector'
									  ),
						},
						sets.length > 1
							? el( components.SelectControl, {
									value: chosen,
									options: sets,
									__nextHasNoMarginBottom: true,
									onChange: function ( value ) {
										props.setAttributes( { set: value } );
									},
							  } )
							: null
					)
				);
			}

			return el(
				element.Fragment,
				{},
				controls,
				el(
					'div',
					blockEditor.useBlockProps ? blockEditor.useBlockProps() : {},
					el( serverSideRender, {
						block: 'cscs/display',
						attributes: props.attributes,
					} )
				)
			);
		},

		// Rendered on the server, so nothing is saved into the post content.
		// A listing stored as markup would be a snapshot of a Tuesday.
		save: function () {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.components,
	window.wp.blockEditor,
	window.wp.i18n,
	window.wp.serverSideRender
);
