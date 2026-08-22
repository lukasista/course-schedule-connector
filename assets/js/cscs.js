/**
 * Turning a listing's links into a listing that changes in place.
 *
 * Everything here is polish. The week switcher, the room filter and the pager
 * are ordinary links with the choice in the address: without this file they
 * load a page and work. With it they fetch the same listing from the server and
 * swap it in, which is faster and keeps the visitor's place on the page.
 *
 * That order is deliberate. A control that only works when a script has loaded
 * is a control that sometimes does not work, and a timetable is exactly the
 * kind of page somebody opens on a bad connection in a corridor.
 */
( function () {
	'use strict';

	var config = window.cscsListing || {};

	if ( ! config.endpoint || ! window.fetch ) {
		return;
	}

	/**
	 * Reads the arguments a link asks for.
	 *
	 * @param {Element} listing The listing element.
	 * @param {Element} link    The link that was followed.
	 * @return {Object} Page, week and room.
	 */
	function argsFrom( listing, link ) {
		var args = {
			page: parseInt( listing.getAttribute( 'data-cscs-page' ) || '1', 10 ),
			week: parseInt( listing.getAttribute( 'data-cscs-week' ) || '0', 10 ),
			room: parseInt( listing.getAttribute( 'data-cscs-room' ) || '0', 10 ),
		};

		var key = link.getAttribute( 'data-cscs-nav' );
		var value = parseInt( link.getAttribute( 'data-cscs-value' ) || '0', 10 );

		args[ key ] = value;

		// The server does the same: another week or another room starts again
		// at the first page, because page four of last week means nothing here.
		if ( 'page' !== key ) {
			args.page = 1;
		}

		return args;
	}

	/**
	 * Fetches a listing and puts it in place of the old one.
	 *
	 * @param {Element} listing The listing element.
	 * @param {Object}  args    Page, week and room.
	 * @param {string}  href    The address the link pointed at.
	 * @return {void}
	 */
	function swap( listing, args, href ) {
		var set = listing.getAttribute( 'data-cscs-set' );

		if ( ! set ) {
			return;
		}

		listing.setAttribute( 'aria-busy', 'true' );

		var url = config.endpoint
			+ '?set=' + encodeURIComponent( set )
			+ '&page=' + args.page
			+ '&week=' + args.week
			+ '&room=' + args.room
			+ '&url=' + encodeURIComponent( window.location.href.split( '#' )[ 0 ].split( '?' )[ 0 ] );

		window
			.fetch( url, { credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.ok ? response.json() : null;
			} )
			.then( function ( body ) {
				if ( ! body || ! body.html ) {
					// Nothing came back that could be shown. The link still
					// points where it always did, so following it properly is
					// the honest way out.
					window.location.href = href;

					return;
				}

				var holder = document.createElement( 'div' );

				holder.innerHTML = body.html;

				var fresh = holder.querySelector( '[data-cscs-set]' );

				if ( ! fresh ) {
					window.location.href = href;

					return;
				}

				listing.parentNode.replaceChild( fresh, listing );

				if ( window.history && window.history.pushState ) {
					window.history.pushState( {}, '', href );
				}
			} )
			.catch( function () {
				window.location.href = href;
			} );
	}

	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest ? event.target.closest( '[data-cscs-nav]' ) : null;

		if ( ! link ) {
			return;
		}

		var listing = link.closest( '[data-cscs-set]' );

		if ( ! listing ) {
			return;
		}

		event.preventDefault();

		swap( listing, argsFrom( listing, link ), link.getAttribute( 'href' ) );
	} );

	// Somebody pressing the back button expects the listing they came from.
	window.addEventListener( 'popstate', function () {
		window.location.reload();
	} );
} )();
