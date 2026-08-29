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
	 * Every block of this plugin stands on the plugin's own shelf.
	 *
	 * Thirty-one blocks scattered through "Widgets" are not a set anybody can
	 * find, and one of them is called "Price". The category is registered in
	 * one place and named in two — the metadata file and the field registration
	 * — which is exactly the sort of pair that comes apart quietly.
	 *
	 * @return void
	 */
	public function test_every_block_stands_in_the_plugins_own_category(): void {
		$this->assertSame( BlockCategory::SLUG, (string) ( $this->metadata()['category'] ?? '' ) );
		$this->assertStringContainsString(
			"'category'              => BlockCategory::SLUG,",
			$this->source( 'includes/Render/FieldBlocks.php' )
		);
	}

	/**
	 * The shelf is put where somebody can find it, and only once.
	 *
	 * @return void
	 */
	public function test_the_category_lands_after_the_ones_wordpress_ships(): void {
		$core = array(
			array( 'slug' => 'text' ),
			array( 'slug' => 'media' ),
			array( 'slug' => 'widgets' ),
			array( 'slug' => 'embed' ),
			array( 'slug' => 'woocommerce' ),
		);

		$added = ( new BlockCategory() )->add( $core );
		$slugs = array_column( $added, 'slug' );

		$this->assertSame( array( 'text', 'media', 'widgets', 'embed', BlockCategory::SLUG, 'woocommerce' ), $slugs );

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
		$this->assertSame( array( BlockCategory::SLUG ), array_column( ( new BlockCategory() )->add( null ), 'slug' ) );
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
