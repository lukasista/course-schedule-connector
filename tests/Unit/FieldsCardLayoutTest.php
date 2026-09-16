<?php
/**
 * Tests for the static row/column fallback a trainer card grid draws from.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Render\Fields;
use PHPUnit\Framework\TestCase;

/**
 * `cscs-cards--row` versus `cscs-cards--column`, and nothing subtler.
 *
 * `templates/partials/cards.php` still draws a plain row or column, the same
 * way it always has, styled by the small fallback rules in `cscs.css`. The
 * real, detailed layout is Divi's own native Layout object, drawn by
 * {@see \CSCS\Render\FieldRenderer::card_rules()}'s scoped `<style>`, which
 * outranks this class in the cascade whenever it has something to say. What
 * this class still has to get right is not styling — it is not turning that
 * same object into the literal string "Array" (with a PHP warning alongside
 * it) the moment somebody actually uses the widget the panel now offers,
 * which is exactly what a bare `(string)` cast once did here, silently,
 * because nothing exercised this path with anything but the plain string
 * Gutenberg and the old select both used to write.
 *
 * @covers \CSCS\Render\Fields
 */
final class FieldsCardLayoutTest extends TestCase {

	/**
	 * The plain string every page written before the native widget existed,
	 * and every Gutenberg page written since, still carries.
	 *
	 * @return void
	 */
	public function test_the_string_row_maps_to_row(): void {
		self::assertSame( 'row', $this->fallback( 'row' ) );
	}

	/**
	 * Anything else stored as a string — including the empty string an
	 * untouched setting holds — falls back to column, exactly as the
	 * template's own default always has.
	 *
	 * @return void
	 */
	public function test_any_other_string_maps_to_column(): void {
		self::assertSame( 'column', $this->fallback( 'column' ) );
		self::assertSame( 'column', $this->fallback( 'sideways' ) );
		self::assertSame( 'column', $this->fallback( '' ) );
	}

	/**
	 * The object Divi's native Layout widget saves is read by its own
	 * `flexDirection` key, not cast to a string — a `(string)` cast on an
	 * array is the literal word "Array" in PHP, which is never 'row', so
	 * every card would have silently rendered as a column regardless of
	 * what was actually chosen, with a PHP warning on every request besides.
	 *
	 * @return void
	 */
	public function test_a_native_layout_object_reads_its_flex_direction(): void {
		self::assertSame( 'row', $this->fallback( array( 'flexDirection' => 'row' ) ) );
		self::assertSame( 'row', $this->fallback( array( 'flexDirection' => 'row-reverse' ) ) );
		self::assertSame( 'column', $this->fallback( array( 'flexDirection' => 'column' ) ) );
		self::assertSame( 'column', $this->fallback( array( 'flexDirection' => 'column-reverse' ) ) );
	}

	/**
	 * A grid layout, or any object that never set `flexDirection` at all,
	 * has no row/column of its own to report — the static fallback still
	 * has to draw something, so it draws a column, the same default an
	 * empty string gets.
	 *
	 * @return void
	 */
	public function test_an_object_without_a_flex_direction_falls_back_to_column(): void {
		self::assertSame( 'column', $this->fallback( array( 'display' => 'grid' ) ) );
		self::assertSame( 'column', $this->fallback( array() ) );
	}

	/**
	 * Whatever else a value might be — missing, null, a stray boolean or
	 * number from a malformed request — this never throws and never
	 * produces anything but 'row' or 'column'.
	 *
	 * @return void
	 */
	public function test_anything_else_falls_back_to_column_without_error(): void {
		self::assertSame( 'column', $this->fallback( null ) );
		self::assertSame( 'column', $this->fallback( 0 ) );
		self::assertSame( 'column', $this->fallback( true ) );
	}

	/**
	 * Calls the private fallback directly, the same way
	 * {@see \CSCS\Tests\Unit\FieldRendererTest} reaches into
	 * `FieldRenderer::card_rules()`.
	 *
	 * @param mixed $value Whatever the settings array holds for the key.
	 * @return string
	 */
	private function fallback( $value ): string {
		$callable = new \ReflectionMethod( Fields::class, 'card_layout_fallback' );
		$callable->setAccessible( true );

		return (string) $callable->invoke( null, $value );
	}
}
