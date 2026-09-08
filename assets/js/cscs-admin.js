/**
 * Progress feedback for the long-running buttons on the iSport screens.
 *
 * Synchronising reaches the remote system several times and can take the
 * better part of a minute. Without this the page simply sat there, and the
 * honest reading of a page that sits there is that the button did nothing —
 * so people pressed it again.
 */
( function () {
	'use strict';

	var texts = window.cscsAdminText || {};

	function busy( form, label ) {
		var progress = form.querySelector( '.cscs-progress' );
		var message = form.querySelector( '.cscs-progress__text' );
		var buttons = form.querySelectorAll( 'button[type="submit"]' );
		var i;

		for ( i = 0; i < buttons.length; i++ ) {
			buttons[ i ].disabled = true;
			buttons[ i ].setAttribute( 'aria-disabled', 'true' );
		}

		if ( message ) {
			message.textContent = label;
		}

		if ( progress ) {
			progress.hidden = false;
		}
	}

	function ready() {
		var forms = document.querySelectorAll( '.cscs-actions' );
		var i;

		for ( i = 0; i < forms.length; i++ ) {
			( function ( form ) {
				form.addEventListener( 'submit', function ( event ) {
					var pressed = event.submitter;
					var action = pressed && pressed.value ? pressed.value : '';

					busy( form, texts[ action ] || texts.working || '' );
				} );
			} )( forms[ i ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', ready );
	} else {
		ready();
	}
} )();
