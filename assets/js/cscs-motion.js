/**
 * Bringing a field into view.
 *
 * An animation that runs on page load is decoration; one that runs when the
 * thing it belongs to arrives on screen is a way of noticing something. So the
 * work is done by an IntersectionObserver rather than a timer, and each element
 * animates once and is then left alone.
 *
 * Two things this deliberately does not do. It never animates for a visitor who
 * has asked their system to reduce motion — that request is not a preference to
 * be weighed against a design, it is often a medical one. And it never hides an
 * element it cannot animate: without JavaScript, without the observer, the CSS
 * leaves everything visible, so a failure here costs an effect and never a
 * paragraph.
 */
( function () {
	'use strict';

	var SELECTOR = '[data-cscs-animation]';

	function still() {
		return (
			window.matchMedia &&
			window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
		);
	}

	function show( node ) {
		node.classList.add( 'cscs-animate--in' );
	}

	function start() {
		var nodes = document.querySelectorAll( SELECTOR );
		var index;

		if ( ! nodes.length ) {
			return;
		}

		if ( still() || ! window.IntersectionObserver ) {
			for ( index = 0; index < nodes.length; index += 1 ) {
				show( nodes[ index ] );
			}

			return;
		}

		// The class that arms the animation is added here rather than in the
		// markup, so an element is only ever hidden once something is certainly
		// going to show it again.
		var observer = new window.IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( ! entry.isIntersecting ) {
						return;
					}

					show( entry.target );
					observer.unobserve( entry.target );
				} );
			},
			{ rootMargin: '0px 0px -10% 0px', threshold: 0.05 }
		);

		for ( index = 0; index < nodes.length; index += 1 ) {
			nodes[ index ].classList.add( 'cscs-animate--armed' );
			observer.observe( nodes[ index ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
