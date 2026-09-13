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
			// Begins at `{{selector}}` and nothing in front of it. Divi splices
			// its own wrapper chain onto that and the result outranks the
			// site's button styling on its own; a prefix of ours breaks the
			// splice, and naming `customPostTypeSelector` to repair it only
			// moves the problem to `{{baseSelector}}`, which the builder's
			// canvas resolves to a class no element carries.
			$this->assertSame(
				'{{selector}} .cscs-button.et_pb_button',
				$attribute['styleProps']['selector'] ?? '',
				$name
			);
			$this->assertArrayNotHasKey( 'customPostTypeSelector', $attribute['styleProps'] ?? array(), $name );

			// Every group the site's own button styling also writes is marked
			// important. In the builder's canvas Divi splices no wrapper chain
			// onto our selector, so it stands at three classes and no id against
			// that rule's id — and loses the background, the radius, the weight
			// and the size. A gradient showed there and a colour did not,
			// because nothing competes for `background-image`.
			foreach ( array( 'background', 'border', 'font', 'spacing' ) as $group ) {
				$this->assertTrue(
					$attribute['styleProps'][ $group ]['important'] ?? false,
					$name . ' ' . $group
				);
			}
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

	/**
	 * One field pilots the column-children mechanism, and names its child.
	 *
	 * Every other field names none — a table nobody has touched offers no
	 * child to drag in at all, rather than one that would do nothing if used.
	 *
	 * @return void
	 */
	public function test_a_table_piloting_column_children_names_its_child(): void {
		$piloting = 0;

		foreach ( Fields::all() as $name => $field ) {
			$children = $this->read( 'divi/fields/' . $name . '/module.json' )['childrenName'] ?? null;

			if ( empty( $field['columns_as_children'] ) ) {
				$this->assertSame( array(), $children, $name );

				continue;
			}

			++$piloting;

			$this->assertSame( array( 'cscs/divi-' . $name . '-column' ), $children, $name );
		}

		// A guard that guards nothing is worse than none: it passes for ever
		// the day the flag stops being read.
		$this->assertSame( 1, $piloting );
	}

	/**
	 * The column child itself exists, agrees with what its parent names, and
	 * has no columns — or children — of its own.
	 *
	 * @return void
	 */
	public function test_the_column_child_module_exists_and_agrees_with_its_parent(): void {
		$parent = $this->read( 'divi/fields/course-schedule/module.json' );
		$child  = $this->read( 'divi/course-schedule-column/module.json' );

		$this->assertNotSame( array(), $child, 'divi/course-schedule-column/module.json is missing.' );
		$this->assertSame( $parent['childrenName'][0] ?? '', $child['name'] ?? '' );
		$this->assertSame( 'child-module', $child['category'] ?? '' );
		$this->assertSame( array(), $child['childrenName'] ?? null );
	}

	/**
	 * Reads the columns a set of children choose, the way either editor hands
	 * them over.
	 *
	 * Divi keeps a child's attribute by breakpoint and state even where there
	 * is neither; Gutenberg keeps it flat. The same helper serves both, so
	 * `FieldModuleRenderer` and `FieldBlocks` need not each know the other
	 * editor's shape.
	 *
	 * @return void
	 */
	public function test_columns_from_children_reads_both_editors_shapes(): void {
		$divi_child = static function ( string $field ): array {
			return array(
				'blockName' => 'cscs/divi-course-schedule-column',
				'attrs'     => array(
					'column' => array(
						'advanced' => array(
							'field' => array( 'desktop' => array( 'value' => $field ) ),
						),
					),
				),
			);
		};

		$gutenberg_child = static function ( string $field ): array {
			return array(
				'blockName' => 'cscs/course-schedule-column',
				'attrs'     => array( 'field' => $field ),
			);
		};

		$this->assertSame(
			array( 'trainer', 'date' ),
			Fields::columns_from_children(
				'course-schedule',
				array( $divi_child( 'trainer' ), $divi_child( 'date' ) ),
				'cscs/divi-course-schedule-column'
			)
		);

		$this->assertSame(
			array( 'trainer', 'date' ),
			Fields::columns_from_children(
				'course-schedule',
				array( $gutenberg_child( 'trainer' ), $gutenberg_child( 'date' ) ),
				'cscs/course-schedule-column'
			)
		);

		// A child naming a block this table has no such column for, a child
		// belonging to a different block entirely, and a second child naming a
		// column already chosen: none of them earn the column a second place,
		// or any place at all.
		$this->assertSame(
			array( 'date' ),
			Fields::columns_from_children(
				'course-schedule',
				array(
					$divi_child( 'not-a-real-column' ),
					array( 'blockName' => 'cscs/divi-some-other-module', 'attrs' => array() ),
					$divi_child( 'date' ),
					$divi_child( 'date' ),
				),
				'cscs/divi-course-schedule-column'
			)
		);
	}

	/**
	 * No children at all is the signal the caller falls back on.
	 *
	 * A table saved before this mechanism existed has none, and a table with
	 * every child since deleted has none either — both have to answer exactly
	 * as `apply_children_columns()` expects: nothing to override with.
	 *
	 * @return void
	 */
	public function test_columns_from_children_is_the_fallback_signal_when_there_are_none(): void {
		$this->assertSame(
			array(),
			Fields::columns_from_children( 'course-schedule', array(), 'cscs/divi-course-schedule-column' )
		);

		// A field that draws no table at all — asking it for columns is not a
		// mistake worth a warning, just an empty answer.
		$this->assertSame(
			array(),
			Fields::columns_from_children( 'course-price', array(), 'cscs/divi-course-price-column' )
		);
	}

	/**
	 * An empty choice leaves the settings exactly as they were.
	 *
	 * This is the whole of what keeps a page with no column children behaving
	 * as it always has: the numbered settings it already carries reach
	 * `ordered_columns()` untouched.
	 *
	 * @return void
	 */
	public function test_apply_children_columns_leaves_settings_alone_when_nothing_was_chosen(): void {
		$settings = array( 'orderDate' => '3', 'label' => 'Kept as it was' );

		$this->assertSame( $settings, Fields::apply_children_columns( 'course-schedule', $settings, array() ) );
	}

	/**
	 * A choice writes every candidate column's place, in the numbered shape
	 * `ordered_columns()` already reads — the chosen ones counting from one,
	 * and the rest sent to nought, which is that function's own way of saying
	 * "leave this out".
	 *
	 * @return void
	 */
	public function test_apply_children_columns_writes_the_numbered_settings_children_imply(): void {
		$settings = Fields::apply_children_columns(
			'course-schedule',
			array( 'label' => 'Untouched by any of this' ),
			array( 'trainer', 'date' )
		);

		$this->assertSame( 'Untouched by any of this', $settings['label'] );
		$this->assertSame( '2', $settings['orderDate'] );
		$this->assertSame( '1', $settings['orderTrainer'] );

		foreach ( array( 'orderTime', 'orderRoom', 'orderState' ) as $hidden ) {
			$this->assertSame( '0', $settings[ $hidden ], $hidden );
		}
	}

	/**
	 * The two new helpers together reproduce, from a set of children, exactly
	 * the table `ordered_columns()` already knew how to build from numbers —
	 * which is the entire point: nothing downstream had to change to read a
	 * table built the new way.
	 *
	 * @return void
	 */
	public function test_children_reorder_and_narrow_the_table_ordered_columns_returns(): void {
		$children = array(
			array( 'blockName' => 'cscs/course-schedule-column', 'attrs' => array( 'field' => 'trainer' ) ),
			array( 'blockName' => 'cscs/course-schedule-column', 'attrs' => array( 'field' => 'date' ) ),
			array( 'blockName' => 'cscs/course-schedule-column', 'attrs' => array( 'field' => 'room' ) ),
		);

		$settings = Fields::apply_children_columns(
			'course-schedule',
			array(),
			Fields::columns_from_children( 'course-schedule', $children, 'cscs/course-schedule-column' )
		);

		$this->assertSame(
			array( 'trainer', 'date', 'room' ),
			Fields::ordered_columns( 'course-schedule', $settings )
		);
	}
}
