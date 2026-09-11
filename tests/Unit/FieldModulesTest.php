<?php
/**
 * Tests for the generated Divi field modules.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Render\Fields;
use PHPUnit\Framework\TestCase;

/**
 * Keeps the generated modules honest.
 *
 * The module metadata is written by `tools/build-divi-modules.php` and
 * committed, which is the only way to give Divi the directory it insists on
 * without twenty-three files edited by hand. The price of a generated file in
 * a repository is that somebody adds a field, forgets to run the generator, and
 * the module is simply missing — with nothing anywhere to say so. These tests
 * are that "anywhere".
 *
 * @covers \CSCS\Render\Fields
 */
final class FieldModulesTest extends TestCase {

	/**
	 * Returns the plugin's root.
	 *
	 * @return string
	 */
	private function root(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Reads one generated file.
	 *
	 * @param string $path Path below the root.
	 * @return array<string, mixed>
	 */
	private function read( string $path ): array {
		$file = $this->root() . '/' . $path;

		if ( ! is_readable( $file ) ) {
			return array();
		}

		$decoded = json_decode( (string) file_get_contents( $file ), true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Every field has a module, and every module has a field.
	 *
	 * @return void
	 */
	public function test_the_modules_and_the_catalogue_agree(): void {
		$expected = array_keys( Fields::all() );
		$found    = array();

		foreach ( (array) glob( $this->root() . '/divi/fields/*', GLOB_ONLYDIR ) as $directory ) {
			$found[] = basename( (string) $directory );
		}

		sort( $expected );
		sort( $found );

		$this->assertSame(
			$expected,
			$found,
			'Run `php tools/build-divi-modules.php` — a field was added or removed without regenerating the Divi modules.'
		);
	}

	/**
	 * Each module carries its defaults, without which every field refuses every
	 * choice made in it, silently.
	 *
	 * @return void
	 */
	public function test_every_module_has_its_defaults(): void {
		foreach ( array_keys( Fields::all() ) as $name ) {
			$defaults = $this->read( 'divi/fields/' . $name . '/module-default-render-attributes.json' );

			$this->assertArrayHasKey( 'field', $defaults, $name );
			$this->assertArrayHasKey( 'advanced', $defaults['field'], $name );
			$this->assertArrayHasKey( 'source', $defaults['field']['advanced'], $name );
		}
	}

	/**
	 * No attribute is called `set`.
	 *
	 * Divi keeps attributes in seamless-immutable objects, where `set` and
	 * `setIn` are methods. An attribute of that name overwrites them, the
	 * builder fails on `getIn(...).setIn is not a function`, and the only
	 * symptom is that nothing saves. It cost an evening once.
	 *
	 * @return void
	 */
	public function test_no_attribute_is_called_set(): void {
		foreach ( array_keys( Fields::all() ) as $name ) {
			$metadata = $this->read( 'divi/fields/' . $name . '/module.json' );

			$this->assertArrayNotHasKey( 'set', $metadata['attributes'] ?? array(), $name );
		}
	}

	/**
	 * The heading and the value are styled apart, which is the point of these.
	 *
	 * @return void
	 */
	public function test_the_heading_and_the_value_have_their_own_typography(): void {
		foreach ( Fields::all() as $name => $field ) {
			$attributes = $this->read( 'divi/fields/' . $name . '/module.json' )['attributes'] ?? array();

			// A button is styled as a button. The box it is printed in is not a
			// second set of text settings, and offering it as one is how a
			// panel comes to hold two of them, only one of which reaches the
			// button.
			if ( 'button' === (string) ( $field['signup'] ?? '' ) ) {
				$this->assertArrayNotHasKey( 'value', $attributes, $name );
				$this->assertArrayNotHasKey( 'designValueText', $this->read( 'divi/fields/' . $name . '/module.json' )['settings']['groups'] ?? array(), $name );

				continue;
			}

			$this->assertSame(
				'{{selector}} .cscs-field__value',
				$attributes['value']['selector'] ?? '',
				$name
			);
			$this->assertArrayHasKey( 'font', $attributes['value']['settings']['decoration'] ?? array(), $name );

			// A field with no heading has no attribute for one, and so no
			// typography group either — the panel says nothing about a thing
			// the page never prints.
			if ( ! Fields::heads( $field ) ) {
				$this->assertArrayNotHasKey( 'title', $attributes, $name );

				continue;
			}

			$this->assertSame(
				'{{selector}} .cscs-field__label',
				$attributes['title']['selector'] ?? '',
				$name
			);
			$this->assertArrayHasKey( 'font', $attributes['title']['settings']['decoration'] ?? array(), $name );
		}
	}

	/**
	 * The arrangement of a field is Divi's Layout group, on the field itself.
	 *
	 * Two halves of one thing. `decoration.layout` is what makes Divi generate
	 * its own Layout group in the Design tab — flex, grid, direction,
	 * alignment, wrapping, gaps — instead of the smaller version the plugin
	 * used to draw in the content panel. And the style prop is what points that
	 * group's CSS at the element with two children in it: a flex container
	 * arranges what is inside it, and inside the module there is only the
	 * field. Without the first the group is not offered; without the second
	 * every control in it works and none of them shows.
	 *
	 * @return void
	 */
	public function test_the_layout_group_is_offered_and_reaches_the_field(): void {
		foreach ( array_keys( Fields::all() ) as $name ) {
			$attributes = $this->read( 'divi/fields/' . $name . '/module.json' )['attributes'] ?? array();

			$this->assertSame(
				'divi/layout',
				$attributes['module']['settings']['decoration']['layout']['item']['component']['name'] ?? '',
				$name
			);
			$this->assertSame(
				'divi/layout',
				$this->read( 'divi/fields/' . $name . '/module.json' )['settings']['groups']['designLayout']['component']['props']['presetGroup'] ?? '',
				$name
			);
			$this->assertSame(
				'{{selector}} .cscs-field',
				$attributes['module']['styleProps']['layout']['selector'] ?? '',
				$name
			);
			$this->assertArrayNotHasKey(
				'layout',
				$attributes['field']['settings']['advanced'] ?? array(),
				$name
			);
		}
	}

	/**
	 * A field with no heading offers nothing that describes one.
	 *
	 * The name of a course is already a heading; a photograph and a sign-up
	 * button are not things a heading sits above. For those the panel says
	 * nothing about one — no switch, no wording, no element, no separator, and
	 * no arrangement or gap, both of which are about where a heading sits.
	 *
	 * @return void
	 */
	public function test_a_field_with_no_heading_says_nothing_about_one(): void {
		$headless = 0;

		foreach ( Fields::all() as $name => $field ) {
			if ( Fields::heads( $field ) ) {
				continue;
			}

			++$headless;

			$metadata = $this->read( 'divi/fields/' . $name . '/module.json' );
			$content  = $metadata['attributes']['field']['settings']['advanced'] ?? array();
			$defaults = $this->read( 'divi/fields/' . $name . '/module-default-render-attributes.json' );

			foreach ( array( 'showLabel', 'label', 'labelTag', 'separator', 'gap' ) as $setting ) {
				$this->assertArrayNotHasKey( $setting, $content, $name . ' ' . $setting );
				$this->assertArrayNotHasKey(
					$setting,
					$defaults['field']['advanced'] ?? array(),
					$name . ' ' . $setting
				);
			}

			// The group survives on a field whose value *is* the heading — it
			// is the value's typography under the name that describes it.
			if ( empty( $field['headline'] ) ) {
				$this->assertArrayNotHasKey( 'designHeadingText', $metadata['settings']['groups'] ?? array(), $name );
			}
		}

		// A guard that guards nothing is worse than none: it passes for ever
		// the day the flag stops being read.
		$this->assertSame( 6, $headless );
	}

	/**
	 * A button has somewhere to write what the Button panel is told.
	 *
	 * The panel appeared, took every setting offered and saved none of them:
	 * the page it was tried on carried no `button` key at all. Divi writes a
	 * chosen value into the structure the defaults describe, and there was no
	 * structure — the same trap the source field fell into, and the same fix.
	 * Every module in Divi's own library that declares `elementType: button`
	 * ships this default, without exception.
	 *
	 * @return void
	 */
	public function test_a_button_has_somewhere_to_write(): void {
		$buttons = 0;

		foreach ( Fields::all() as $name => $field ) {
			if ( 'button' !== (string) ( $field['signup'] ?? '' ) ) {
				continue;
			}

			++$buttons;

			$defaults  = $this->read( 'divi/fields/' . $name . '/module-default-render-attributes.json' );
			$attribute = $this->read( 'divi/fields/' . $name . '/module.json' )['attributes']['button'] ?? array();

			$this->assertIsArray(
				$defaults['button']['decoration']['button']['desktop']['value'] ?? null,
				$name
			);
			// No `body #page-container` in front of it. The prefix was bought to
			// outrank the site's own button styling and cost two contexts: a
			// theme builder template, where Divi splices its wrappers into a
			// selector it did not write and produced one that matched nothing,
			// and the builder canvas, where the prefix does not describe the
			// markup either.
			$this->assertSame(
				'{{selector}} .cscs-button.et_pb_button',
				$attribute['styleProps']['selector'] ?? '',
				$name
			);
			$this->assertArrayNotHasKey( 'customPostTypeSelector', $attribute['styleProps'] ?? array(), $name );
		}

		$this->assertSame( 1, $buttons );
	}

	/**
	 * Every styled element says what kind of element it is.
	 *
	 * Divi's builder decides from `elementType` which style components an
	 * attribute gets, and gives one without it none at all. The page still
	 * renders correctly, because PHP does not ask — so the only symptom is a
	 * builder in which no design setting ever changes anything, which is a long
	 * way to find from a missing word in a generated file.
	 *
	 * @return void
	 */
	public function test_every_styled_element_declares_its_kind(): void {
		foreach ( Fields::all() as $name => $field ) {
			$attributes = $this->read( 'divi/fields/' . $name . '/module.json' )['attributes'] ?? array();

			if ( 'button' !== (string) ( $field['signup'] ?? '' ) ) {
				// A field that is itself a heading says so: the value is
				// declared a heading, not a body of text, and the panel calls
				// its typography what it is.
				$this->assertSame(
					empty( $field['headline'] ) ? 'content' : 'heading',
					$attributes['value']['elementType'] ?? '',
					$name
				);
			}

			if ( Fields::heads( $field ) ) {
				$this->assertSame( 'heading', $attributes['title']['elementType'] ?? '', $name );
			}

			if ( empty( $field['image'] ) ) {
				continue;
			}

			$this->assertSame( 'image', $attributes['image']['elementType'] ?? '', $name );
		}
	}

	/**
	 * A field that draws a table can have the table designed.
	 *
	 * The heading row, the cells, the links, the banding and each column, each
	 * with its own attribute, its own group and its own selector — and no such
	 * attribute at all on a field that draws no table, where it would be a
	 * design panel for something that is not on the page.
	 *
	 * @return void
	 */
	public function test_a_table_can_be_designed_part_by_part(): void {
		foreach ( Fields::all() as $name => $field ) {
			$metadata   = $this->read( 'divi/fields/' . $name . '/module.json' );
			$attributes = $metadata['attributes'] ?? array();
			$groups     = $metadata['settings']['groups'] ?? array();
			$columns    = (array) $field['columns'];

			if ( array() === $columns ) {
				$this->assertArrayNotHasKey( 'tableHead', $attributes, $name );

				continue;
			}

			foreach ( array( 'tableHead', 'tableCell', 'tableLink', 'tableStripe' ) as $element ) {
				$this->assertArrayHasKey( $element, $attributes, $name . ' ' . $element );
			}

			$this->assertSame( '{{selector}} .cscs-table thead th', $attributes['tableHead']['selector'] ?? '', $name );

			foreach ( $columns as $column ) {
				$attribute = Fields::column_attribute( (string) $column );

				$this->assertArrayHasKey( $attribute, $attributes, $name . ' ' . $attribute );
				$this->assertSame(
					'{{selector}} .cscs-table .cscs-col-' . $column,
					$attributes[ $attribute ]['selector'] ?? '',
					$name . ' ' . $attribute
				);
				$this->assertArrayHasKey( 'design' . ucfirst( $attribute ), $groups, $name . ' ' . $attribute );
			}
		}
	}

	/**
	 * Every part a module offers to style, it also writes CSS for.
	 *
	 * The catalogue answers both questions, so they cannot drift: what the
	 * generated file declares as an attribute is what the render callback and
	 * the builder ask for styles for.
	 *
	 * @return void
	 */
	public function test_the_declared_parts_and_the_styled_parts_are_the_same(): void {
		foreach ( Fields::all() as $name => $field ) {
			$attributes = $this->read( 'divi/fields/' . $name . '/module.json' )['attributes'] ?? array();

			$declared = array_keys(
				array_filter(
					$attributes,
					static function ( $attribute ): bool {
						return isset( $attribute['elementType'] );
					}
				)
			);

			$this->assertSame( array_keys( Fields::style_elements( $field ) ), $declared, $name );
		}
	}

	/**
	 * Two modules may not answer to one name.
	 *
	 * @return void
	 */
	public function test_every_module_is_named_once(): void {
		$names      = array();
		$shortcodes = array();

		foreach ( array_keys( Fields::all() ) as $name ) {
			$metadata     = $this->read( 'divi/fields/' . $name . '/module.json' );
			$names[]      = (string) ( $metadata['name'] ?? '' );
			$shortcodes[] = (string) ( $metadata['d4Shortcode'] ?? '' );
		}

		$this->assertSame( $names, array_values( array_unique( $names ) ) );
		$this->assertSame( $shortcodes, array_values( array_unique( $shortcodes ) ) );
		$this->assertNotContains( '', $names );
	}

	/**
	 * Every context a field is in knows which posts it can be pointed at.
	 *
	 * The kinds arrived without one, and nothing said so: the page rendered
	 * perfectly and the builder answered "this content cannot be displayed" the
	 * moment anybody opened the module's settings, because the select's options
	 * were null. Both the module list and the block list are asked of the
	 * catalogue now, and this is what says they still are.
	 *
	 * @return void
	 */
	public function test_every_context_has_a_post_type_to_be_pointed_at(): void {
		$seen = array();

		foreach ( Fields::all() as $name => $field ) {
			$context = (string) $field['context'];

			$this->assertNotSame( '', $context, $name . ' is in no context at all.' );

			$seen[ $context ] = Fields::post_type( $context );
		}

		foreach ( $seen as $context => $post_type ) {
			$this->assertNotSame( '', $post_type, 'Nothing says which posts a ' . $context . ' field may be pointed at.' );
		}

		// Not one answer for all of them either, which is what a fallback
		// standing in for a missing case looks like from here.
		$this->assertSame( count( $seen ), count( array_unique( $seen ) ) );
	}
}
