<?php
/**
 * Tests for the block's metadata.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Data\DisplaySet;
use CSCS\Render\BlockCategory;
use CSCS\Render\Fields;
use PHPUnit\Framework\TestCase;

/**
 * Guards the wiring between the block, its script and its renderer.
 *
 * None of this can be exercised without WordPress, but all of it can be got
 * wrong by renaming a string in one file and not the other — and the result is
 * an editor that loads a block with no controls and no error.
 *
 * @covers \CSCS\Render\Block
 */
final class BlockMetadataTest extends TestCase {

	/**
	 * Reads the block's metadata.
	 *
	 * @return array<string, mixed>
	 */
	private function metadata(): array {
		$decoded = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/blocks/display/block.json' ), true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Reads a source file.
	 *
	 * @param string $path Path relative to the plugin root.
	 * @return string
	 */
	private function source( string $path ): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $path );
	}

	/**
	 * Every block of this plugin stands in the section its context belongs to.
	 *
	 * Thirty-two blocks scattered through "Widgets" are not a set anybody can
	 * find, and one of them is called "Price"; thirty-two under one heading is
	 * a shorter list and still not the question somebody is asking, which is
	 * "the fields of a trainer". The section is decided in one place and read
	 * in two — the metadata file and the field registration — which is exactly
	 * the sort of pair that comes apart quietly.
	 *
	 * @return void
	 */
	public function test_every_block_stands_in_the_section_of_its_context(): void {
		$this->assertSame( BlockCategory::COURSES, (string) ( $this->metadata()['category'] ?? '' ) );
		$this->assertStringContainsString(
			"'category'              => BlockCategory::of( (string) \$field['context'] ),",
			$this->source( 'includes/Render/FieldBlocks.php' )
		);
	}

	/**
	 * Every context has a section, and an unknown one still has somewhere to go.
	 *
	 * @return void
	 */
	public function test_every_context_names_a_section(): void {
		$this->assertSame( BlockCategory::COURSES, BlockCategory::of( 'course' ) );
		$this->assertSame( BlockCategory::KINDS, BlockCategory::of( 'kind' ) );
		$this->assertSame( BlockCategory::TRAINERS, BlockCategory::of( 'trainer' ) );
		$this->assertSame( BlockCategory::COURSES, BlockCategory::of( 'something-nobody-has-added-yet' ) );

		$sections = array_column( ( new BlockCategory() )->add( array() ), 'slug' );

		foreach ( Fields::all() as $field ) {
			$this->assertContains( BlockCategory::of( (string) $field['context'] ), $sections );
		}
	}

	/**
	 * The sections are put where somebody can find them, and only once.
	 *
	 * @return void
	 */
	public function test_the_sections_land_after_the_ones_wordpress_ships(): void {
		$core = array(
			array( 'slug' => 'text' ),
			array( 'slug' => 'media' ),
			array( 'slug' => 'widgets' ),
			array( 'slug' => 'embed' ),
			array( 'slug' => 'woocommerce' ),
		);

		$added = ( new BlockCategory() )->add( $core );
		$slugs = array_column( $added, 'slug' );

		$this->assertSame(
			array( 'text', 'media', 'widgets', 'embed', BlockCategory::COURSES, BlockCategory::KINDS, BlockCategory::TRAINERS, 'woocommerce' ),
			$slugs
		);

		// Called twice — which WordPress does when a filter is added twice, and
		// two identical categories is a duplicated heading in the inserter.
		$this->assertSame( $slugs, array_column( ( new BlockCategory() )->add( $added ), 'slug' ) );
	}

	/**
	 * Anything the filter is handed that is not a list is still a list after.
	 *
	 * @return void
	 */
	public function test_the_filter_survives_being_handed_nonsense(): void {
		$this->assertSame(
			array( BlockCategory::COURSES, BlockCategory::KINDS, BlockCategory::TRAINERS ),
			array_column( ( new BlockCategory() )->add( null ), 'slug' )
		);
	}

	/**
	 * The block is what the renderer, the editor script and the metadata all
	 * think it is.
	 *
	 * @return void
	 */
	public function test_the_block_name_agrees_everywhere(): void {
		$metadata = $this->metadata();

		$this->assertSame( 'cscs/display', $metadata['name'] ?? '' );
		$this->assertStringContainsString( "const NAME = 'cscs/display'", $this->source( 'includes/Render/Block.php' ) );
		$this->assertStringContainsString( "block: 'cscs/display'", $this->source( 'blocks/display/editor.js' ) );
	}

	/**
	 * The script handle in the metadata is the one PHP registers. Renaming one
	 * and not the other gives an editor with a block and no controls, and no
	 * error to say so.
	 *
	 * @return void
	 */
	public function test_the_editor_script_handle_is_the_registered_one(): void {
		$metadata = $this->metadata();

		$this->assertSame( 'cscs-block-editor', $metadata['editorScript'] ?? '' );
		$this->assertStringContainsString( "const SCRIPT = 'cscs-block-editor'", $this->source( 'includes/Render/Block.php' ) );
	}

	/**
	 * The block carries exactly the one attribute the renderer reads, and the
	 * block-renderer endpoint the editor previews through will only pass
	 * attributes that are declared here.
	 *
	 * @return void
	 */
	public function test_the_only_attribute_is_the_set(): void {
		$metadata = $this->metadata();

		$this->assertSame( array( 'set' ), array_keys( $metadata['attributes'] ?? array() ) );
		$this->assertSame( 'string', $metadata['attributes']['set']['type'] ?? '' );
		$this->assertSame( '', $metadata['attributes']['set']['default'] ?? null );
	}

	/**
	 * Nothing is saved into the post content. A listing stored as markup would
	 * be a snapshot of a Tuesday, wrong by Wednesday and invisible to everyone
	 * until somebody reopened the page.
	 *
	 * @return void
	 */
	public function test_the_block_saves_nothing(): void {
		$this->assertStringContainsString( 'return null;', $this->source( 'blocks/display/editor.js' ) );
		$this->assertFalse( $this->metadata()['supports']['html'] ?? true );
	}

	/**
	 * The stylesheet the block asks for is the one the renderer enqueues, so a
	 * listing in the editor looks like a listing on the page.
	 *
	 * @return void
	 */
	public function test_the_block_uses_the_same_stylesheet_as_the_page(): void {
		$metadata = $this->metadata();

		$this->assertSame( 'cscs', $metadata['style'] ?? '' );
		$this->assertSame( 'cscs', $metadata['editorStyle'] ?? '' );
		$this->assertStringContainsString( "const HANDLE = 'cscs'", $this->source( 'includes/Render/Assets.php' ) );
	}

	/**
	 * Both listing types can be chosen through the block, or one of the two
	 * would need a shortcode and the arrangement would only half work.
	 *
	 * @return void
	 */
	public function test_the_editor_offers_both_kinds_of_set(): void {
		$source = $this->source( 'includes/Render/Block.php' );

		$this->assertStringContainsString( DisplaySet::TYPE_SCHEDULE, $source );
		$this->assertStringContainsString( 'sets()->all()', $source );
	}
}
