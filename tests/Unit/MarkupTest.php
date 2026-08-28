<?php
/**
 * Tests for tidying markup that arrives from somewhere else.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Support\Markup;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the flattening against what iSport's editor actually produces.
 *
 * @covers \CSCS\Support\Markup
 */
final class MarkupTest extends TestCase {

	/**
	 * The shape 24 of this gym's 113 descriptions arrive in: a list whose only
	 * item is another list, which reads on the page as two levels of bullets.
	 *
	 * @return void
	 */
	public function test_a_list_inside_a_list_becomes_one_list(): void {
		$flat = Markup::flatten_lists( "<ul>\n\t<li>\n\t<ul>\n\t\t<li>První</li>\n\t\t<li>Druhá</li>\n\t</ul>\n\t</li>\n</ul>" );

		$this->assertSame( 1, substr_count( $flat, '<ul>' ) );
		$this->assertSame( 2, substr_count( $flat, '<li>' ) );
		$this->assertStringContainsString( 'První', $flat );
		$this->assertStringContainsString( 'Druhá', $flat );
	}

	/**
	 * A sub-list under a sentence keeps both: the sentence stays an item and
	 * the sub-items join it, rather than one of them being thrown away.
	 *
	 * @return void
	 */
	public function test_items_are_kept_when_a_sub_list_is_lifted(): void {
		$flat = Markup::flatten_lists( '<ul><li>První<ul><li>Vnořená</li></ul></li><li>Druhá</li></ul>' );

		$this->assertSame( 1, substr_count( $flat, '<ul>' ) );
		$this->assertSame( 3, substr_count( $flat, '<li>' ) );
		$this->assertStringContainsString( 'Vnořená', $flat );
	}

	/**
	 * A list that is already flat is handed back exactly as it was: the cheapest
	 * correct answer, and the one 89 of the 113 descriptions get.
	 *
	 * @return void
	 */
	public function test_a_flat_list_is_left_alone(): void {
		$html = '<ul><li>A</li><li>B</li></ul>';

		$this->assertSame( $html, Markup::flatten_lists( $html ) );
	}

	/**
	 * Text around a list is text, not something to tidy away.
	 *
	 * @return void
	 */
	public function test_everything_that_is_not_a_list_survives(): void {
		$flat = Markup::flatten_lists( '<p>Úvod</p><ul><li>První<ul><li>Vnořená</li></ul></li></ul><p>Závěr</p>' );

		$this->assertStringContainsString( '<p>Úvod</p>', $flat );
		$this->assertStringContainsString( '<p>Závěr</p>', $flat );
	}

	/**
	 * Nothing in, nothing out — and nothing thrown either.
	 *
	 * @return void
	 */
	public function test_empty_markup_is_answered_with_empty_markup(): void {
		$this->assertSame( '', Markup::flatten_lists( '' ) );
		$this->assertSame( '   ', Markup::flatten_lists( '   ' ) );
	}
}
