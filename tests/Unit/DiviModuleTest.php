<?php
/**
 * Tests for the Divi module's wiring.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Divi\ModuleFolder;
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
	 * Every module is filed in a folder somebody registered.
	 *
	 * @return void
	 */
	public function test_every_module_is_filed_in_a_folder_that_exists(): void {
		$folders = array();

		foreach ( ModuleFolder::definitions() as $folder ) {
			$path      = '' === $folder['path'] ? $folder['name'] : $folder['path'] . '/' . $folder['name'];
			$folders[] = $path;
		}

		$root  = dirname( __DIR__, 2 );
		$files = glob( $root . '/divi/*/module.json' ) ?: array();
		$files = array_merge( $files, glob( $root . '/divi/fields/*/module.json' ) ?: array() );

		$this->assertNotEmpty( $files );

		foreach ( $files as $file ) {
			$json = json_decode( (string) file_get_contents( $file ), true );

			// A module filed under a folder nobody registered is a module that
			// disappears from the list entirely, which is worse than one in the
			// wrong drawer.
			$this->assertContains(
				(string) ( $json['folder'] ?? '' ),
				$folders,
				basename( dirname( $file ) ) . ' names a folder that is not registered'
			);
		}
	}

	/**
	 * Each context has a drawer of its own, and an unknown one still lands.
	 *
	 * @return void
	 */
	public function test_every_context_names_a_drawer(): void {
		$this->assertSame( ModuleFolder::NAME . '/courses', ModuleFolder::path( 'course' ) );
		$this->assertSame( ModuleFolder::NAME . '/kinds', ModuleFolder::path( 'kind' ) );
		$this->assertSame( ModuleFolder::NAME . '/trainers', ModuleFolder::path( 'trainer' ) );
		$this->assertSame( ModuleFolder::NAME . '/courses', ModuleFolder::path( 'nobody-has-added-this-yet' ) );
	}

	/**
	 * The shelf is registered before the drawers that sit in it.
	 *
	 * @return void
	 */
	public function test_the_shelf_comes_before_its_drawers(): void {
		$definitions = ModuleFolder::definitions();

		$this->assertSame( '', $definitions[0]['path'] );
		$this->assertSame( ModuleFolder::NAME, $definitions[0]['name'] );

		foreach ( array_slice( $definitions, 1 ) as $drawer ) {
			$this->assertSame( ModuleFolder::NAME, $drawer['path'] );
		}
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
		$attribute = $this->metadata()['attributes']['listing'] ?? array();

		$this->assertArrayHasKey( 'advanced', $attribute['settings'] ?? array() );
		$this->assertArrayNotHasKey( 'innerContent', $attribute['settings'] ?? array() );
		$this->assertSame( 'listing.advanced.id', $attribute['settings']['advanced']['id']['item']['attrName'] ?? '' );
		$this->assertSame( 'divi/select', $attribute['settings']['advanced']['id']['item']['component']['name'] ?? '' );
	}

	/**
	 * The renderer reads the value from the place the field writes it.
	 *
	 * @return void
	 */
	public function test_the_renderer_reads_the_chosen_set(): void {
		$attrs = array(
			'listing' => array( 'advanced' => array( 'id' => array( 'desktop' => array( 'value' => 'kurzy-pro-deti' ) ) ) ),
		);

		$this->assertSame( 'kurzy-pro-deti', ModuleRenderer::set_id( $attrs ) );
	}

	/**
	 * A page saved before the mistake was understood keeps working.
	 *
	 * @return void
	 */
	public function test_the_older_places_are_still_read(): void {
		$this->assertSame(
			'tydenni-rozvrh',
			ModuleRenderer::set_id( array( 'set' => array( 'innerContent' => array( 'desktop' => array( 'value' => 'tydenni-rozvrh' ) ) ) ) )
		);

		$this->assertSame(
			'tydenni-rozvrh',
			ModuleRenderer::set_id( array( 'set' => array( 'advanced' => array( 'id' => array( 'desktop' => array( 'value' => 'tydenni-rozvrh' ) ) ) ) ) )
		);
	}

	/**
	 * A module nobody has configured names no set, rather than something that
	 * would be looked up and found missing.
	 *
	 * @return void
	 */
	public function test_a_module_with_no_choice_names_nothing(): void {
		$this->assertSame( '', ModuleRenderer::set_id( array() ) );
		$this->assertSame( '', ModuleRenderer::set_id( array( 'listing' => array() ) ) );
		$this->assertSame( '', ModuleRenderer::set_id( array( 'listing' => array( 'advanced' => array( 'id' => array( 'desktop' => array( 'value' => '' ) ) ) ) ) ) );
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
			'listing' => array( 'advanced' => array( 'id' => array( 'desktop' => array( 'value' => '../../Kurzy Pro Deti' ) ) ) ),
		);

		$this->assertSame( 'kurzyprodeti', ModuleRenderer::set_id( $attrs ) );

		$attrs['listing']['advanced']['id']['desktop']['value'] = 'kurzy-pro-deti';

		$this->assertSame( 'kurzy-pro-deti', ModuleRenderer::set_id( $attrs ) );
	}

	/**
	 * The group the field sits in declares a groupName. The documentation lists
	 * it among a group's required keys, and Divi's own modules all carry one:
	 * without it the field renders and the panel looks finished, which is the
	 * worst way for a required key to be missing.
	 *
	 * @return void
	 */
	public function test_the_group_is_declared_the_way_divi_declares_its_own(): void {
		$groups = $this->metadata()['settings']['groups'] ?? array();
		$slug   = $this->metadata()['attributes']['listing']['settings']['advanced']['id']['item']['groupSlug'] ?? '';

		$this->assertArrayHasKey( $slug, $groups );
		$this->assertSame( 'content', $groups[ $slug ]['panel'] ?? '' );
		$this->assertNotSame( '', (string) ( $groups[ $slug ]['groupName'] ?? '' ) );
		$this->assertSame( 'divi/composite', $groups[ $slug ]['component']['name'] ?? '' );
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
	/**
	 * The attribute is not called `set`, and that is not a matter of taste.
	 *
	 * Divi keeps a module's attributes in seamless-immutable objects, whose own
	 * API includes `set` and `setIn`. An attribute of that name collides with
	 * it: Divi drops the attribute from the module entirely and every choice
	 * made in the field is refused with "getIn(...).setIn is not a function" —
	 * while the field itself renders perfectly, so nothing looks wrong.
	 *
	 * @return void
	 */
	public function test_the_attribute_avoids_the_names_the_immutable_api_uses(): void {
		$names = array_keys( $this->metadata()['attributes'] ?? array() );

		$this->assertContains( 'listing', $names );

		foreach ( array( 'set', 'setIn', 'get', 'getIn', 'merge', 'without', 'asMutable' ) as $reserved ) {
			$this->assertNotContains( $reserved, $names );
		}
	}

	/**
	 * The module ships default attributes, and they cover the field.
	 *
	 * Divi writes a chosen value into the structure the defaults describe. With
	 * no default for the field there is nothing to write into, which is the
	 * second half of the same silent failure.
	 *
	 * @return void
	 */
	public function test_the_module_ships_defaults_for_its_field(): void {
		$decoded = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/divi/cscs-display/module-default-render-attributes.json' ), true );

		$this->assertIsArray( $decoded );
		$this->assertArrayHasKey( 'listing', $decoded );
		$this->assertSame( '', $decoded['listing']['advanced']['id']['desktop']['value'] ?? null );
	}
}
