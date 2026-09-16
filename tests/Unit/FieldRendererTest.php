<?php
/**
 * Tests for turning trainer-card settings into scoped CSS.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Render\FieldRenderer;
use PHPUnit\Framework\TestCase;

/**
 * A grid of trainer cards, and the handful of settings it accepts.
 *
 * Bullets already prove the shape: a small set of settings that mean the same
 * thing in Divi and in Gutenberg, read by one method, turned into rules scoped
 * to one field's instance. Cards reuse that shape rather than Divi's own
 * richer per-element groups, precisely so nothing here can drift between the
 * two editors. These tests hold the pure, WordPress-free half of that promise
 * — the parsing — so a stray character in a stored setting fails a test
 * instead of silently producing no rule, or an unsafe one.
 *
 * @covers \CSCS\Render\FieldRenderer
 */
final class FieldRendererTest extends TestCase {

	/**
	 * Nothing asked for is nothing rendered.
	 *
	 * A field that has never had its card settings touched must not emit an
	 * empty `<style>` tag, the same way an untouched bullet list does not.
	 *
	 * @return void
	 */
	public function test_no_settings_means_no_rules(): void {
		self::assertSame( array(), $this->card_rules( array() ) );
	}

	/**
	 * Layout and gap become one rule on the grid itself.
	 *
	 * @return void
	 */
	public function test_layout_and_gap_become_one_rule_on_the_grid(): void {
		$rules = $this->card_rules(
			array(
				'cardsLayout' => 'row',
				'cardsGap'    => '1.5rem',
			)
		);

		self::assertSame(
			'flex-direction:row;gap:1.5rem;',
			$rules['{{scope}} .cscs-cards']
		);
	}

	/**
	 * "column" is the other half of the same choice as "row".
	 *
	 * @return void
	 */
	public function test_column_layout_is_accepted_too(): void {
		$rules = $this->card_rules( array( 'cardsLayout' => 'column' ) );

		self::assertSame( 'flex-direction:column;', $rules['{{scope}} .cscs-cards'] );
	}

	/**
	 * A layout that is neither "row" nor "column" is not a layout Divi or
	 * Gutenberg ever offer, so it is not trusted, the same as any other
	 * setting that did not come from the list a select control actually
	 * shows.
	 *
	 * @return void
	 */
	public function test_an_unrecognised_layout_is_dropped(): void {
		self::assertSame( array(), $this->card_rules( array( 'cardsLayout' => 'diagonal' ) ) );
	}

	/**
	 * The photograph and the name inside one card get their own rule, kept
	 * apart from the grid's — the same independence Divi's panel offers
	 * through two separate groups, "Cards" and "Photo & name".
	 *
	 * @return void
	 */
	public function test_the_card_gets_its_own_layout_rule(): void {
		$rules = $this->card_rules( array( 'cardLayout' => 'row' ) );

		self::assertSame( 'flex-direction:row;', $rules['{{scope}} .cscs-card'] );
		self::assertArrayNotHasKey( '{{scope}} .cscs-cards', $rules );
	}

	/**
	 * "column" is the other half of the same choice as "row", for the card
	 * exactly as it is for the grid.
	 *
	 * @return void
	 */
	public function test_the_card_accepts_column_too(): void {
		$rules = $this->card_rules( array( 'cardLayout' => 'column' ) );

		self::assertSame( 'flex-direction:column;', $rules['{{scope}} .cscs-card'] );
	}

	/**
	 * An unrecognised value is dropped for the card the same way it is for
	 * the grid.
	 *
	 * @return void
	 */
	public function test_an_unrecognised_card_layout_is_dropped(): void {
		self::assertSame( array(), $this->card_rules( array( 'cardLayout' => 'diagonal' ) ) );
	}

	/**
	 * The grid and the card answer independently: setting one is not
	 * setting the other, and both rules can exist at once.
	 *
	 * @return void
	 */
	public function test_the_grid_and_the_card_can_differ(): void {
		$rules = $this->card_rules(
			array(
				'cardsLayout' => 'row',
				'cardLayout'  => 'column',
			)
		);

		self::assertSame( 'flex-direction:row;', $rules['{{scope}} .cscs-cards'] );
		self::assertSame( 'flex-direction:column;', $rules['{{scope}} .cscs-card'] );
	}

	/**
	 * The ratio and the margin become one rule on the photograph, kept apart
	 * from the rule on the grid because they style different elements.
	 *
	 * @return void
	 */
	public function test_ratio_and_margin_become_one_rule_on_the_image(): void {
		$rules = $this->card_rules(
			array(
				'cardImageRatio'  => '4/3',
				'cardImageMargin' => '0 auto',
			)
		);

		self::assertSame(
			'aspect-ratio:4/3;object-fit:cover;width:100%;margin:0 auto;',
			$rules['{{scope}} .cscs-card__image']
		);
		self::assertArrayNotHasKey( '{{scope}} .cscs-cards', $rules );
	}

	/**
	 * A ratio that is not two numbers separated by a slash is not a CSS
	 * aspect ratio, so nothing is written rather than something unsafe.
	 *
	 * @return void
	 */
	public function test_a_malformed_ratio_is_dropped(): void {
		self::assertSame( array(), $this->card_rules( array( 'cardImageRatio' => 'banana' ) ) );
	}

	/**
	 * Spaces around the slash are how a person is likely to type a ratio,
	 * so "4 / 3" must work exactly like "4/3".
	 *
	 * @return void
	 */
	public function test_a_ratio_with_spaces_around_the_slash_is_normalised(): void {
		$rules = $this->card_rules( array( 'cardImageRatio' => '16 / 9' ) );

		self::assertStringContainsString( 'aspect-ratio:16/9;', $rules['{{scope}} .cscs-card__image'] );
	}

	/**
	 * Decimal ratios, such as a square-ish 1.5/1, are valid CSS and must not
	 * be rejected for having a dot in them.
	 *
	 * @return void
	 */
	public function test_a_decimal_ratio_is_accepted(): void {
		$rules = $this->card_rules( array( 'cardImageRatio' => '1.5/1' ) );

		self::assertStringContainsString( 'aspect-ratio:1.5/1;', $rules['{{scope}} .cscs-card__image'] );
	}

	/**
	 * Calls the private, WordPress-free half of card styling directly, the
	 * same way {@see \CSCS\Tests\Unit\KindFilterTest} reaches into
	 * `KindDetail`: this logic has no WordPress dependency and stays
	 * decoupled from the rendering path that assembles the final `<style>`
	 * tag.
	 *
	 * @param array<string, mixed> $attributes Settings, as Divi or Gutenberg would store them.
	 * @return array<string, string>
	 */
	private function card_rules( array $attributes ): array {
		$callable = new \ReflectionMethod( FieldRenderer::class, 'card_rules' );
		$callable->setAccessible( true );

		return (array) $callable->invoke( null, $attributes );
	}
}
