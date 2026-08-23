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
		foreach ( array_keys( Fields::all() ) as $name ) {
			$attributes = $this->read( 'divi/fields/' . $name . '/module.json' )['attributes'] ?? array();

			$this->assertSame(
				'{{selector}} .cscs-field__label',
				$attributes['title']['selector'] ?? '',
				$name
			);
			$this->assertSame(
				'{{selector}} .cscs-field__value',
				$attributes['value']['selector'] ?? '',
				$name
			);
			$this->assertArrayHasKey( 'font', $attributes['title']['settings']['decoration'] ?? array(), $name );
			$this->assertArrayHasKey( 'font', $attributes['value']['settings']['decoration'] ?? array(), $name );
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
}
