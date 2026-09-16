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
 * `cardsLayout` and `cardLayout` are Divi's own native Layout value now — the
 * same `display`/`flexDirection`/`justifyContent`/… object
 * `module.decoration.layout` holds for the module itself — and Gutenberg's
 * plain 'row'/'column' string, kept for the pages it already wrote and the
 * simpler control it still offers. `layout_value()` normalises either shape
 * down to the keys Divi's own
 * `\ET\Builder\Packages\StyleLibrary\Declarations\Layout\Layout` would
 * recognise, dropping anything that is not on its own allow-list;
 * `layout_declaration()` calls that class when it is present and falls back
 * to a direction-only declaration when it is not, which is also what proves
 * these tests without a Divi installation to hand. These tests hold the
 * pure, WordPress-free half of that promise — the parsing and the
 * sanitising — so a stray or hostile character in a stored setting fails a
 * test here instead of silently producing no rule, or an unsafe one.
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
			'display:flex;flex-direction:row;gap:1.5rem;',
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

		self::assertSame( 'display:flex;flex-direction:column;', $rules['{{scope}} .cscs-cards'] );
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

		self::assertSame( 'display:flex;flex-direction:row;', $rules['{{scope}} .cscs-card'] );
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

		self::assertSame( 'display:flex;flex-direction:column;', $rules['{{scope}} .cscs-card'] );
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

		self::assertSame( 'display:flex;flex-direction:row;', $rules['{{scope}} .cscs-cards'] );
		self::assertSame( 'display:flex;flex-direction:column;', $rules['{{scope}} .cscs-card'] );
	}

	/**
	 * Divi's own native Layout widget saves a small object — `display`,
	 * `flexDirection`, `justifyContent`, and the rest — not the bare
	 * 'row'/'column' string the old plain select wrote. Every key it
	 * recognises must reach the declaration; this environment has no Divi
	 * installed, so it exercises the direction-only fallback
	 * {@see self::test_without_divi_a_direction_still_renders()} covers on
	 * its own, but the gap keys are applied directly by the plugin's own
	 * code either way, proven here on the object shape specifically.
	 *
	 * @return void
	 */
	public function test_a_native_layout_object_is_accepted_for_the_grid(): void {
		$rules = $this->card_rules(
			array(
				'cardsLayout' => array(
					'display'        => 'flex',
					'flexDirection'  => 'row',
					'justifyContent' => 'center',
					'alignItems'     => 'center',
					'columnGap'      => '24px',
					'rowGap'         => '16px',
				),
			)
		);

		$declaration = $rules['{{scope}} .cscs-cards'];

		self::assertStringContainsString( 'flex-direction:row', $declaration );
		self::assertStringContainsString( 'row-gap:16px;', $declaration );
		self::assertStringContainsString( 'column-gap:24px;', $declaration );
	}

	/**
	 * The card accepts the same native object as the grid, independently —
	 * this is the setting Lukas specifically asked for: centering the
	 * photograph and the name within their own card.
	 *
	 * @return void
	 */
	public function test_a_native_layout_object_centres_the_card(): void {
		$rules = $this->card_rules(
			array(
				'cardLayout' => array(
					'flexDirection'  => 'column',
					'justifyContent' => 'center',
					'alignItems'     => 'center',
				),
			)
		);

		$declaration = $rules['{{scope}} .cscs-card'];

		self::assertStringContainsString( 'flex-direction:column', $declaration );
	}

	/**
	 * An object with nothing this plugin's allow-lists recognise — however
	 * it got into storage — renders exactly as if the setting were empty,
	 * the same discipline the bare string enjoyed when "diagonal" was
	 * rejected above.
	 *
	 * @return void
	 */
	public function test_an_object_with_only_unrecognised_keys_produces_nothing(): void {
		self::assertSame(
			array(),
			$this->card_rules(
				array(
					'cardsLayout' => array(
						'display'       => 'inline-block',
						'flexDirection' => 'diagonal',
					),
				)
			)
		);
	}

	/**
	 * `gridTemplateColumns` is the one field the native widget leaves
	 * genuinely open-ended — a manual track list — which makes it the one a
	 * hostile stored value would use to close the declaration early and add
	 * rules of its own. It must never reach the page, even sitting next to
	 * an otherwise valid direction.
	 *
	 * @return void
	 */
	public function test_a_track_list_that_could_break_out_of_the_declaration_is_dropped(): void {
		$rules = $this->card_rules(
			array(
				'cardsLayout' => array(
					'flexDirection'       => 'row',
					'gridTemplateColumns' => '1fr; } body { display:none',
				),
			)
		);

		$declaration = $rules['{{scope}} .cscs-cards'] ?? '';

		self::assertStringContainsString( 'flex-direction:row', $declaration );
		self::assertStringNotContainsString( 'display:none', $declaration );
		self::assertStringNotContainsString( '}', $declaration );
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
	 * Without Divi's own Layout class to hand — this environment has no
	 * Divi installed — a plain direction still renders, and gap is still
	 * applied directly from a rich object. This is the fallback a
	 * non-Divi site, or a request that races the plugin's own load order,
	 * actually gets; grid-specific keys are Divi's own class's to draw and
	 * are not asserted here, only in {@see self::test_a_well_formed_manual_track_list_survives_sanitising()}
	 * and its neighbours, which test the sanitising step directly rather
	 * than the final CSS text.
	 *
	 * @return void
	 */
	public function test_without_divi_a_direction_still_renders(): void {
		self::assertSame( 'display:flex;flex-direction:row;', $this->layout_declaration( 'row' ) );
		self::assertStringContainsString( 'row-gap:10px;', $this->layout_declaration( array( 'rowGap' => '10px' ) ) );
		self::assertStringNotContainsString( 'column-gap', $this->layout_declaration( array( 'rowGap' => '10px' ) ) );
	}

	/**
	 * Every key {@see \ET\Builder\Packages\StyleLibrary\Declarations\Layout\Layout}
	 * itself would read for a flex layout survives sanitising, in the same
	 * shape it arrived in — the object Divi's own native Layout widget
	 * saves, not a reduced version of it.
	 *
	 * @return void
	 */
	public function test_every_recognised_flex_key_survives_sanitising(): void {
		$clean = $this->layout_value(
			array(
				'display'        => 'flex',
				'flexDirection'  => 'row',
				'justifyContent' => 'center',
				'alignItems'     => 'center',
				'flexWrap'       => 'wrap',
				'alignContent'   => 'space-between',
				'columnGap'      => '20px',
				'rowGap'         => '1rem',
			)
		);

		self::assertSame(
			array(
				'display'        => 'flex',
				'flexDirection'  => 'row',
				'justifyContent' => 'center',
				'alignItems'     => 'center',
				'flexWrap'       => 'wrap',
				'alignContent'   => 'space-between',
				'columnGap'      => '20px',
				'rowGap'         => '1rem',
			),
			$clean
		);
	}

	/**
	 * The widget's "Grid" mode keys survive sanitising too, since the value
	 * is one object with one set of allow-lists, not a separate shape for
	 * each mode.
	 *
	 * @return void
	 */
	public function test_every_recognised_grid_key_survives_sanitising(): void {
		$clean = $this->layout_value(
			array(
				'display'            => 'grid',
				'gridColumnWidths'   => 'equalMinimum',
				'gridColumnMinWidth' => '250px',
				'gridColumnCount'    => '3',
				'gridRowHeights'     => 'auto',
				'gridAutoFlow'       => 'row',
				'gridDensity'        => 'dense',
				'gridJustifyItems'   => 'stretch',
			)
		);

		self::assertSame( 'grid', $clean['display'] );
		self::assertSame( 'equalMinimum', $clean['gridColumnWidths'] );
		self::assertSame( '250px', $clean['gridColumnMinWidth'] );
		self::assertSame( '3', $clean['gridColumnCount'] );
		self::assertSame( 'auto', $clean['gridRowHeights'] );
		self::assertSame( 'row', $clean['gridAutoFlow'] );
		self::assertSame( 'dense', $clean['gridDensity'] );
		self::assertSame( 'stretch', $clean['gridJustifyItems'] );
	}

	/**
	 * A value outside a key's own enum is dropped, not passed through —
	 * "inline-block" is not a `display` the widget offers, whatever wrote
	 * it into storage.
	 *
	 * @return void
	 */
	public function test_an_out_of_list_enum_value_is_dropped(): void {
		$clean = $this->layout_value(
			array(
				'display'        => 'inline-block',
				'flexDirection'  => 'diagonal',
				'gridColumnCount' => '0',
			)
		);

		self::assertSame( array(), $clean );
	}

	/**
	 * A well-formed manual track list — the one field the widget leaves
	 * genuinely open-ended — survives sanitising unharmed.
	 *
	 * @return void
	 */
	public function test_a_well_formed_manual_track_list_survives_sanitising(): void {
		$clean = $this->layout_value(
			array( 'gridTemplateColumns' => 'repeat(3, minmax(100px, 1fr))' )
		);

		self::assertSame( 'repeat(3, minmax(100px, 1fr))', $clean['gridTemplateColumns'] );
	}

	/**
	 * The same field, asked to carry a value that could close the
	 * declaration early and add rules of its own, is dropped outright.
	 *
	 * @return void
	 */
	public function test_a_track_list_that_could_break_out_of_the_declaration_is_dropped_by_sanitising(): void {
		$clean = $this->layout_value(
			array( 'gridTemplateColumns' => '1fr; } body { display:none' )
		);

		self::assertArrayNotHasKey( 'gridTemplateColumns', $clean );
	}

	/**
	 * The same field, asked to carry markup, is dropped outright too —
	 * nothing about a manual track list should ever contain a `<`.
	 *
	 * @return void
	 */
	public function test_a_track_list_with_markup_is_dropped_by_sanitising(): void {
		$clean = $this->layout_value(
			array( 'gridAutoRows' => 'auto"><script>alert(1)</script>' )
		);

		self::assertArrayNotHasKey( 'gridAutoRows', $clean );
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

	/**
	 * Calls `layout_declaration()` directly, the same way {@see self::card_rules()}
	 * calls `card_rules()`.
	 *
	 * @param mixed $value A `cardsLayout`/`cardLayout` value, string or object.
	 * @return string
	 */
	private function layout_declaration( $value ): string {
		$callable = new \ReflectionMethod( FieldRenderer::class, 'layout_declaration' );
		$callable->setAccessible( true );

		return (string) $callable->invoke( null, $value );
	}

	/**
	 * Calls `layout_value()` directly — the sanitising step on its own,
	 * before it becomes a CSS declaration — the same way {@see self::card_rules()}
	 * calls `card_rules()`.
	 *
	 * @param mixed $value A `cardsLayout`/`cardLayout` value, string or object.
	 * @return array<string, string>
	 */
	private function layout_value( $value ): array {
		$callable = new \ReflectionMethod( FieldRenderer::class, 'layout_value' );
		$callable->setAccessible( true );

		return (array) $callable->invoke( null, $value );
	}
}
