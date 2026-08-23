/**
 * The repeating rows on the trainer screen.
 *
 * Nothing here is load-bearing. The form works with the script switched off:
 * every list always offers one spare line, empty lines are dropped when the
 * post is saved, and clearing a line is how you remove it. This only saves the
 * round trip — a page reload to gain a second empty box is a poor way to spend
 * somebody's afternoon.
 *
 * Written against the DOM, with no build step and no dependency on the block
 * editor's packages: this screen is the classic editor, and a script that needs
 * compiling to add a text input would be out of all proportion to the job.
 */
( function () {
	'use strict';

	/**
	 * Makes a fresh, empty copy of a row.
	 *
	 * @param {Element} row An existing row to copy the markup of.
	 * @return {Element|null} The new row.
	 */
	function blankRow( row ) {
		if ( ! row ) {
			return null;
		}

		var copy = row.cloneNode( true );
		var field = copy.querySelector( 'input' );

		if ( field ) {
			field.value = '';
		}

		return copy;
	}

	/**
	 * Wires one list.
	 *
	 * @param {Element} group The container of one repeating list.
	 * @return {void}
	 */
	function wire( group ) {
		var list = group.querySelector( '.cscs-repeat__list' );

		if ( ! list ) {
			return;
		}

		group.addEventListener( 'click', function ( event ) {
			var target = event.target;

			if ( ! target || ! target.classList ) {
				return;
			}

			if ( target.classList.contains( 'cscs-repeat__add' ) ) {
				event.preventDefault();

				var added = blankRow( list.lastElementChild );

				if ( added ) {
					list.appendChild( added );

					var field = added.querySelector( 'input' );

					if ( field ) {
						field.focus();
					}
				}

				return;
			}

			if ( target.classList.contains( 'cscs-repeat__remove' ) ) {
				event.preventDefault();

				var row = target.closest( '.cscs-repeat__row' );

				if ( ! row ) {
					return;
				}

				// Never leave the list with nothing in it: a list with no boxes
				// is a list nobody can add to without reloading.
				if ( list.children.length > 1 ) {
					row.parentNode.removeChild( row );

					return;
				}

				var only = row.querySelector( 'input' );

				if ( only ) {
					only.value = '';
					only.focus();
				}
			}
		} );
	}

	function start() {
		var groups = document.querySelectorAll( '[data-cscs-repeat]' );
		var index;

		for ( index = 0; index < groups.length; index += 1 ) {
			wire( groups[ index ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
