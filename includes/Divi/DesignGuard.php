<?php
/**
 * Design stays with whoever owns the design.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Divi;

use CSCS\Admin\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps a site manager out of the module's design settings, on the server.
 *
 * This was the site owner's first requirement and the reason the plugin is
 * shaped the way it is: whoever maintains the courses may say which listing
 * appears, and the look of the site belongs to whoever owns the look. Hiding
 * the design panels would be a courtesy — a builder is a browser application,
 * and anything a browser decides can be undone in the browser. So the rule is
 * applied where it cannot be got around: when the page is saved.
 *
 * What survives a save by someone without `cscs_manage_design` is the design
 * that was there before. Not an empty design, and not a refusal to save: taking
 * away what an administrator set would be as wrong as letting somebody else set
 * it, and refusing the save would lose the content edit that was the point.
 */
final class DesignGuard {

	/**
	 * The attributes a site manager owns.
	 *
	 * Everything else on the module is design, including `css`, which is a
	 * stylesheet by another name.
	 */
	private const CONTENT_KEYS = array( 'set' );

	/**
	 * Bookkeeping Divi writes for itself, which is nobody's decision.
	 */
	private const NEUTRAL_KEYS = array( 'builderVersion' );

	/**
	 * Hooks the guard.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_insert_post_data', array( $this, 'guard' ), 10, 2 );
	}

	/**
	 * Restores the design of every module in a post being saved.
	 *
	 * @param array<string, mixed> $data    Post data about to be written.
	 * @param array<string, mixed> $postarr Post data as submitted.
	 * @return array<string, mixed>
	 */
	public function guard( array $data, array $postarr ): array {
		$content = (string) ( $data['post_content'] ?? '' );

		// Cheap tests first: the overwhelming majority of saves on a site are
		// of something else entirely.
		if ( '' === $content || false === strpos( $content, DisplayModule::NAME ) ) {
			return $data;
		}

		if ( Capabilities::can_manage_design() ) {
			return $data;
		}

		$stored = (int) ( $postarr['ID'] ?? 0 );
		$before = 0 === $stored ? '' : (string) get_post_field( 'post_content', $stored );

		$blocks  = parse_blocks( $content );
		$known   = self::collect( parse_blocks( $before ) );
		$index   = 0;
		$changed = false;

		self::walk(
			$blocks,
			static function ( array $block ) use ( &$index, &$changed, $known ): array {
				$previous = $known[ $index ] ?? array();
				++$index;

				$guarded = self::merge( $previous, is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array(), false );

				if ( $guarded !== $block['attrs'] ) {
					$block['attrs'] = $guarded;
					$changed        = true;
				}

				return $block;
			}
		);

		// Serialising rewrites the whole document, so it is only worth doing
		// when something actually needed putting back.
		if ( $changed ) {
			$data['post_content'] = serialize_blocks( $blocks );
		}

		return $data;
	}

	/**
	 * Returns the attributes a save may keep.
	 *
	 * @param array<string, mixed> $old        Attributes as they were stored.
	 * @param array<string, mixed> $new        Attributes as submitted.
	 * @param bool                 $may_design Whether the person may change design.
	 * @return array<string, mixed>
	 */
	public static function merge( array $old, array $new, bool $may_design ): array {
		if ( $may_design ) {
			return $new;
		}

		$guarded = $old;

		foreach ( self::CONTENT_KEYS as $key ) {
			unset( $guarded[ $key ] );

			if ( array_key_exists( $key, $new ) ) {
				$guarded[ $key ] = $new[ $key ];
			}
		}

		foreach ( self::NEUTRAL_KEYS as $key ) {
			if ( array_key_exists( $key, $new ) ) {
				$guarded[ $key ] = $new[ $key ];
			}
		}

		return $guarded;
	}

	/**
	 * Returns the attributes of every one of our modules, in document order.
	 *
	 * Order is how a module is recognised again, because Divi writes no
	 * identifier of its own into the saved markup. Reordering modules on a page
	 * therefore moves their design with the position rather than with the
	 * module — which is the safe way round: a site manager cannot invent design
	 * that way, only shuffle design an administrator already approved.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<int, array<string, mixed>>
	 */
	public static function collect( array $blocks ): array {
		$found = array();

		self::walk(
			$blocks,
			static function ( array $block ) use ( &$found ): array {
				$found[] = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();

				return $block;
			}
		);

		return $found;
	}

	/**
	 * Visits every one of our modules in a block tree, innermost included.
	 *
	 * @param array<int, array<string, mixed>> $blocks  Parsed blocks, by reference.
	 * @param callable                         $visitor Receives and returns a block.
	 * @return void
	 */
	private static function walk( array &$blocks, callable $visitor ): void {
		foreach ( $blocks as $key => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( DisplayModule::NAME === ( $block['blockName'] ?? '' ) ) {
				$blocks[ $key ] = $visitor( $block );
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$inner = $blocks[ $key ]['innerBlocks'];

				self::walk( $inner, $visitor );

				$blocks[ $key ]['innerBlocks'] = $inner;
			}
		}
	}
}
