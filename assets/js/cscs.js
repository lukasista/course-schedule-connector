/**
 * Turning a listing's controls into a listing that changes in place.
 *
 * Everything here is polish. The week switcher, the room filter and the pager
 * are an ordinary form and ordinary links with the choice in the address:
 * without this file they load a page and work. With it they fetch the same
 * listing from the server and swap it in, which is faster and keeps the
 * visitor's place on the page.
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

	// Tells the stylesheet the controls will act on their own, so the "Show"
	// button beside the room list can go. Set before anything renders, so the
	// button never appears and then vanishes.
	document.documentElement.className += ' cscs-js';

	/**
	 * Reads the arguments a control asks for.
	 *
	 * @param {Element} listing The listing element.
	 * @param {string}  key     One of `page`, `week` or `room`.
	 * @param {number}  value   The value that control carries.
	 * @return {Object} Page, week and room.
	 */
	function argsFrom( listing, key, value ) {
		var args = {
			page: parseInt( listing.getAttribute( 'data-cscs-page' ) || '1', 10 ),
			week: parseInt( listing.getAttribute( 'data-cscs-week' ) || '0', 10 ),
			room: parseInt( listing.getAttribute( 'data-cscs-room' ) || '0', 10 ),
		};

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
	 * @param {string}  href    The address the control pointed at.
	 * @return {void}
	 */
	function swap( listing, args, href ) {
		var set = listing.getAttribute( 'data-cscs-set' );

		if ( ! set ) {
			window.location.href = href;

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
					// Nothing came back that could be shown. The control still
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
				announce( fresh );

				if ( window.history && window.history.pushState ) {
					window.history.pushState( {}, '', href );
				}
			} )
			.catch( function () {
				window.location.href = href;
			} );
	}

	/**
	 * Says out loud what the listing now shows.
	 *
	 * Pressing "next week" replaces a table under the reader's feet. Somebody
	 * looking at the screen sees that at once; somebody listening to it is
	 * given no sign that anything happened at all, because focus has not moved
	 * and nothing was navigated. So the listing carries a quiet live region and
	 * this puts a sentence in it — which is WCAG's status-message rule, and is
	 * also simply what a person would say if they were doing the pressing for
	 * you.
	 *
	 * @param {Element} listing The listing that has just arrived.
	 * @return {void}
	 */
	function announce( listing ) {
		var region = document.getElementById( 'cscs-status' );

		if ( ! region ) {
			region = document.createElement( 'p' );
			region.id = 'cscs-status';
			region.className = 'cscs-status';
			region.setAttribute( 'role', 'status' );
			region.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( region );
		}

		var said = listing.getAttribute( 'data-cscs-said' ) || '';
		var rows = listing.querySelectorAll( '.cscs-row' ).length;

		// Cleared first, because a live region handed the same words twice in a
		// row says them once.
		region.textContent = '';

		window.setTimeout( function () {
			region.textContent = said
				? said
				: String( rows );
		}, 60 );
	}

	/**
	 * Works out where a form would have gone had it been submitted.
	 *
	 * @param {HTMLFormElement} form The form.
	 * @return {string} The address.
	 */
	function formTarget( form ) {
		var parts = [];
		var fields = form.querySelectorAll( 'input[name], select[name]' );

		for ( var i = 0; i < fields.length; i++ ) {
			if ( '' === fields[ i ].value ) {
				continue;
			}

			parts.push(
				encodeURIComponent( fields[ i ].name ) + '=' + encodeURIComponent( fields[ i ].value )
			);
		}

		var action = form.getAttribute( 'action' ) || window.location.href.split( '?' )[ 0 ];

		return parts.length ? action + '?' + parts.join( '&' ) : action;
	}

	// Bound while the click travels down rather than up. A theme is entitled to
	// its own handler for links — Divi, among others, catches anything pointing
	// at a fragment on the same page and scrolls to it, stopping the click
	// dead. Listening first means the listing's own controls are never the
	// casualty of that, whatever theme the site ends up wearing.
	document.addEventListener(
		'click',
		function ( event ) {
			var control = event.target.closest ? event.target.closest( '[data-cscs-nav]' ) : null;

			if ( ! control || 'A' !== control.tagName ) {
				return;
			}

			// A middle click, or one with a modifier held, means a new tab.
			// That is the browser's business, not ours.
			if ( 0 !== event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
				return;
			}

			var listing = control.closest( '[data-cscs-set]' );

			if ( ! listing ) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();

			swap(
				listing,
				argsFrom(
					listing,
					control.getAttribute( 'data-cscs-nav' ),
					parseInt( control.getAttribute( 'data-cscs-value' ) || '0', 10 )
				),
				control.getAttribute( 'href' )
			);
		},
		true
	);

	// The room filter is a form, so that it submits and works without this
	// file. With it, choosing is enough.
	document.addEventListener( 'change', function ( event ) {
		var control = event.target;

		if ( ! control.getAttribute || ! control.getAttribute( 'data-cscs-nav' ) || 'SELECT' !== control.tagName ) {
			return;
		}

		var listing = control.closest( '[data-cscs-set]' );
		var form = control.closest( 'form' );

		if ( ! listing || ! form ) {
			return;
		}

		swap(
			listing,
			argsFrom( listing, control.getAttribute( 'data-cscs-nav' ), parseInt( control.value || '0', 10 ) ),
			formTarget( form )
		);
	} );

	// Somebody pressing the back button expects the listing they came from.
	window.addEventListener( 'popstate', function () {
		window.location.reload();
	} );
} )();
