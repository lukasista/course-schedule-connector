<?php
/**
 * Tests for the wording of a listing.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Render\Formatter;
use CSCS\Render\Renderer;
use PHPUnit\Framework\TestCase;

/**
 * Guards the small decisions a visitor actually reads.
 *
 * @covers \CSCS\Render\Formatter
 */
final class FormatterTest extends TestCase {

	/**
	 * A price is written the way the site owner asked: a thousands separator,
	 * no rounding, and no trailing zeroes where there is nothing to say.
	 *
	 * @return void
	 */
	public function test_a_price_is_written_the_czech_way(): void {
		$space = Formatter::NBSP;

		$this->assertSame( '1' . $space . '960' . $space . 'Kč', Formatter::price( '1960', 'Zdarma' ) );
		$this->assertSame( '1' . $space . '960' . $space . 'Kč', Formatter::price( '1960.00', 'Zdarma' ) );
		$this->assertSame( '1' . $space . '960,50' . $space . 'Kč', Formatter::price( '1960.5', 'Zdarma' ) );
		$this->assertSame( '190' . $space . 'Kč', Formatter::price( '190', 'Zdarma' ) );
	}

	/**
	 * The separator is the one that never breaks. "1 960 Kč" split across two
	 * lines is a different number at a glance.
	 *
	 * @return void
	 */
	public function test_a_price_never_breaks_across_lines(): void {
		$this->assertStringNotContainsString( ' ', Formatter::price( '1960', 'Zdarma' ) );
	}

	/**
	 * No price at all is not a price of zero, and neither is a zero.
	 *
	 * @return void
	 */
	public function test_a_missing_price_says_what_the_caller_decided(): void {
		$this->assertSame( 'Zdarma', Formatter::price( null, 'Zdarma' ) );
		$this->assertSame( 'Na dotaz', Formatter::price( '', 'Na dotaz' ) );
		$this->assertSame( 'Na dotaz', Formatter::price( '0', 'Na dotaz' ) );
		$this->assertSame( 'Na dotaz', Formatter::price( 'zdarma', 'Na dotaz' ) );
	}

	/**
	 * How full a course is, as a state rather than a sentence, so the wording
	 * stays where a translator can reach it.
	 *
	 * @return void
	 */
	public function test_availability_is_a_state(): void {
		$this->assertSame( 'full', Formatter::availability( 0, 3 ) );
		$this->assertSame( 'full', Formatter::availability( -2, 3 ) );
		$this->assertSame( 'few', Formatter::availability( 3, 3 ) );
		$this->assertSame( 'free', Formatter::availability( 4, 3 ) );
		$this->assertSame( 'free', Formatter::availability( 1, 0 ) );
	}

	/**
	 * The three levels of the booking button, in the order the site owner
	 * described them — and the facts, which overrule all three.
	 *
	 * @return void
	 */
	public function test_the_booking_button_obeys_the_facts_before_the_settings(): void {
		$this->assertTrue( Formatter::shows_button( 'default', true, true, 5 ) );
		$this->assertFalse( Formatter::shows_button( 'default', false, true, 5 ) );
		$this->assertTrue( Formatter::shows_button( 'always', false, true, 5 ) );
		$this->assertFalse( Formatter::shows_button( 'never', true, true, 5 ) );

		// A button leading to a booking that cannot happen is worse than none.
		$this->assertFalse( Formatter::shows_button( 'always', true, false, 5 ) );
		$this->assertFalse( Formatter::shows_button( 'always', true, true, 0 ) );
	}

	/**
	 * A range with nothing on one side is not a range.
	 *
	 * @return void
	 */
	public function test_a_range_with_one_end_is_just_that_end(): void {
		$this->assertSame( '15:30', Formatter::time_range( '15:30:00', '' ) );
		$this->assertSame( '', Formatter::time_range( '', '16:30' ) );
		$this->assertSame( '11. 9. 2026', Formatter::date_range( '11. 9. 2026', '11. 9. 2026' ) );
		$this->assertSame( '11. 9. 2026', Formatter::date_range( '', '11. 9. 2026' ) );
	}

	/**
	 * The width at which a table folds is a setting, so the rule that folds it
	 * has to be built rather than shipped. It is worth asserting that the
	 * number reaches the media query and that the headings survive the fold —
	 * a table with no headings on a telephone is a grid of unlabelled values.
	 *
	 * @return void
	 */
	public function test_the_fold_rule_carries_the_configured_width(): void {
		$css = Renderer::responsive_css( 640 );

		$this->assertStringContainsString( '@media (max-width: 640px)', $css );
		$this->assertStringContainsString( 'content: attr(data-label)', $css );
	}
}
