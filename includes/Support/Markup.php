<?php
/**
 * Markup that arrives from somewhere else.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Tidies markup written by another system before a page has to live with it.
 *
 * iSport's descriptions are typed into a rich-text box in the booking system,
 * and what comes out carries that box's habits: a `<br>` before anything else,
 * and — in 24 of this gym's 113 courses — a list wrapped in a list, whose only
 * content is another list. On a page that reads as two levels of bullets where
 * one was meant, and no amount of stylesheet fixes it, because the second level
 * is really there in the markup.
 *
 * Nothing here rewrites what the text says. It flattens what the editor nested
 * and removes what it left empty, and that is all.
 */
final class Markup {

	/**
	 * Returns the markup with every list one level deep.
	 *
	 * A list inside a list item is lifted into the list above it, and an item
	 * left with nothing in it afterwards is dropped. Whether the nesting was
	 * meaningful is not something markup can be asked: a sub-list under a
	 * sentence and a sub-list under nothing look the same from here, and the
	 * gym's own website shows every one of these as a flat list.
	 *
	 * @param string $html Markup, possibly nested.
	 * @return string
	 */
	public static function flatten_lists( string $html ): string {
		if ( '' === trim( $html ) || ! class_exists( '\DOMDocument' ) ) {
			return $html;
		}

		if ( ! preg_match( '#<li[^>]*>(?:(?!</li>).)*<(?:ul|ol)#is', $html ) ) {
			return $html;
		}

		$document = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );

		// The encoding declaration is what stops libxml reading UTF-8 as
		// Latin-1; the two flags stop it wrapping the fragment in a document
		// nobody asked for.
		$loaded = $document->loadHTML(
			'<?xml encoding="utf-8" ?><div id="cscs-root">' . $html . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( false === $loaded ) {
			return $html;
		}

		$root = $document->getElementById( 'cscs-root' );

		if ( ! $root instanceof \DOMElement ) {
			return $html;
		}

		self::lift_nested_lists( $document );
		self::drop_empty_items( $document );

		$out = '';

		foreach ( $root->childNodes as $child ) {
			$out .= (string) $document->saveHTML( $child );
		}

		return trim( $out );
	}

	/**
	 * Moves the items of a nested list up into the list that holds it.
	 *
	 * Repeated until nothing moves, because a list three deep is two lifts.
	 *
	 * @param \DOMDocument $document Document.
	 * @return void
	 */
	private static function lift_nested_lists( \DOMDocument $document ): void {
		$moved = true;

		while ( $moved ) {
			$moved = false;

			foreach ( iterator_to_array( $document->getElementsByTagName( 'li' ) ) as $item ) {
				if ( ! $item instanceof \DOMElement || ! $item->parentNode instanceof \DOMElement ) {
					continue;
				}

				$list = $item->parentNode;

				foreach ( iterator_to_array( $item->childNodes ) as $child ) {
					if ( ! $child instanceof \DOMElement ) {
						continue;
					}

					if ( ! in_array( strtolower( $child->tagName ), array( 'ul', 'ol' ), true ) ) {
						continue;
					}

					$after = $item->nextSibling;

					foreach ( iterator_to_array( $child->childNodes ) as $inner ) {
						$list->insertBefore( $inner, $after );
					}

					$item->removeChild( $child );
					$moved = true;
				}
			}
		}
	}

	/**
	 * Removes list items and lists left with nothing to show.
	 *
	 * @param \DOMDocument $document Document.
	 * @return void
	 */
	private static function drop_empty_items( \DOMDocument $document ): void {
		foreach ( array( 'li', 'ul', 'ol' ) as $tag ) {
			foreach ( iterator_to_array( $document->getElementsByTagName( $tag ) ) as $element ) {
				if ( ! $element instanceof \DOMElement || ! $element->parentNode instanceof \DOMNode ) {
					continue;
				}

				// A picture or a line break is content even where there are no
				// words, so emptiness is measured on both.
				$has_element = 0 < $element->getElementsByTagName( '*' )->length;

				if ( '' === trim( $element->textContent ) && ! $has_element ) {
					$element->parentNode->removeChild( $element );
				}
			}
		}
	}
}
