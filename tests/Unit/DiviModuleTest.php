<?php
/**
 * Tests for the Divi module's wiring.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Divi\ModuleRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Pins the two things about a Divi module that cannot be seen from PHP and
 * were both got wrong once: where the chosen set is stored, and that the field
 * declaring it is a setting rather than the content of an element.
 *
 * @covers \CSCS\Divi\ModuleRenderer
 */
final class DiviModuleTest extends TestCase {

	/**
	 * Reads the module's metadata.
	 *
	 * @return array<string, mixed>
	 */
	private function metadata(): array {
		$decoded = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/divi/cscs-display/module.json' ), true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * The set is stored where Divi keeps a setting: under `advanced`.
	 *
	 * `innerContent` is the content of the element itself — the words in a
	 * heading, the code in a code module — and declaring a setting there gives
	 * the builder nowhere to put the value, so the field silently refuses every
	 * choice made in it.
	 *
	 * @return void
	 */
	public function test_the_set_is_declared_as_a_setting_not_as_content(): void {
		$attribute = $this->metadata()['attributes']['set'] ?? array();

		$this->assertArrayHasKey( 'advanced', $attribute['settings'] ?? array() );
		$this->assertArrayNotHasKey( 'innerContent', $attribute['settings'] ?? array() );
		$this->assertSame( 'set.advanced.id', $attribute['settings']['advanced']['id']['item']['attrName'] ?? '' );
		$this->assertSame( 'divi/select', $attribute['settings']['advanced']['id']['item']['component']['name'] ?? '' );
	}

	/**
	 * The renderer reads the value from the place the field writes it.
	 *
	 * @return void
	 */
	public function test_the_renderer_reads_the_chosen_set(): void {
		$attrs = array(
			'set' => array( 'advanced' => array( 'id' => array( 'desktop' => array( 'value' => 'kurzy-pro-deti' ) ) ) ),
		);

		$this->assertSame( 'kurzy-pro-deti', ModuleRenderer::set_id( $attrs ) );
	}

	/**
	 * A page saved before the mistake was understood keeps working.
	 *
	 * @return void
	 */
	public function test_the_older_place_is_still_read(): void {
		$attrs = array(
			'set' => array( 'innerContent' => array( 'desktop' => array( 'value' => 'tydenni-rozvrh' ) ) ),
		);

		$this->assertSame( 'tydenni-rozvrh', ModuleRenderer::set_id( $attrs ) );
	}

	/**
	 * A module nobody has configured names no set, rather than something that
	 * would be looked up and found missing.
	 *
	 * @return void
	 */
	public function test_a_module_with_no_choice_names_nothing(): void {
		$this->assertSame( '', ModuleRenderer::set_id( array() ) );
		$this->assertSame( '', ModuleRenderer::set_id( array( 'set' => array() ) ) );
		$this->assertSame( '', ModuleRenderer::set_id( array( 'set' => array( 'advanced' => array( 'id' => array( 'desktop' => array( 'value' => '' ) ) ) ) ) ) );
	}

	/**
	 * Whatever a page's markup happens to hold, what reaches a lookup is a key:
	 * lower case, and nothing in it but letters, digits, dashes and
	 * underscores. Block attributes are text in a post, and a post can be
	 * edited by hand.
	 *
	 * @return void
	 */
	public function test_the_stored_value_is_reduced_to_a_key(): void {
		$attrs = array(
			'set' => array( 'advanced' => array( 'id' => array( 'desktop' => array( 'value' => '../../Kurzy Pro Deti' ) ) ) ),
		);

		$this->assertSame( 'kurzyprodeti', ModuleRenderer::set_id( $attrs ) );

		$attrs['set']['advanced']['id']['desktop']['value'] = 'kurzy-pro-deti';

		$this->assertSame( 'kurzy-pro-deti', ModuleRenderer::set_id( $attrs ) );
	}

	/**
	 * The module registers under its own name. Sharing one with the editor
	 * block would mean one of the two never registering at all.
	 *
	 * @return void
	 */
	public function test_the_module_and_the_block_are_two_names(): void {
		$this->assertSame( 'cscs/divi-display', $this->metadata()['name'] ?? '' );
	}
}
