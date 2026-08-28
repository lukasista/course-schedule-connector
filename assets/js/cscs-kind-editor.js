/**
 * The one thing the "Description from a course" panel needs a browser for.
 *
 * The panel is a select and a link, not a form: a form inside the block
 * editor's own form is invalid markup and browsers treat it as such. So the
 * link carries the chosen course itself, added at the moment of the click.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var button = document.getElementById( 'cscs-kind-import' );

		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function ( event ) {
			var select = document.getElementById( button.dataset.courseField );

			if ( ! select ) {
				return;
			}

			event.preventDefault();

			var url = new URL( button.href, window.location.origin );

			url.searchParams.set( 'course', select.value );
			window.location.href = url.toString();
		} );
	} );
} )();
