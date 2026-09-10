<?php
/**
 * Design stays with whoever owns the design.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Divi;

defined( 'ABSPATH' ) || exit;

use CSCS\Admin\Capabilities;
use CSCS\Render\Block;

/**
 * Keeps a site manager out of the design settings, on the server.
 *
 * Every block and module the plugin registers, not one of them: the listing
 * module was guarded from the first day and the thirty field blocks and modules
 * that arrived later were not, which meant the rule held on the one place a
 * design used to live and nowhere it had since moved to.
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
	 * The attributes a site manager owns on a listing.
	 *
	 * Everything else on the module is design, including `css`, which is a
	 * stylesheet by another name. `set` is the name the attribute had before it
	 * turned out to collide with Divi's own, and is kept so that a page saved
	 * under it is still a page whose content its manager owns.
	 */
	private const LISTING_KEYS = array( 'listing', 'set' );

	/**
	 * The attributes a site manager owns on a field block.
	 *
	 * Exactly what the block's **Settings** tab offers, which is the point:
	 * the server refuses what the panel does not show and allows everything it
	 * does, so a person cannot be told one thing by the builder and another by
	 * the save. Which course this shows, what to say when there is nothing,
	 * which of a kind's courses to list, and the picture — the picture because
	 * choosing a size and writing what it says to somebody who cannot see it is
	 * describing the content, not designing it. The sign-up link belongs here
	 * for the same reason: what it says is words, and whether it says them as
	 * a button or as a plain link decides which element a reader meets, not
	 * what colour it is.
	 */
	private const FIELD_KEYS = array(
		'postId',
		'emptyText',
		'filterGenders',
		'filterLevels',
		'filterAgeMin',
		'filterAgeMax',
		'filterSort',
		'filterOrder',
		'filterLimit',
		'linkStyle',
		'linkText',
		'imageSize',
		'imageAlt',
		'imageLink',
		'imageLinkUrl',
		'imageLinkTarget',
	);

	/**
	 * The attribute a site manager owns on a Divi field module.
	 *
	 * One key, because Divi keeps every content setting of these modules under
	 * it — `field.advanced.*` is precisely what the builder shows in the
	 * Content panel, and everything else on the module (`module`, `title`,
	 * `value`, the table parts, the columns, `css`) is a design group. The
	 * split is Divi's own, so the server and the panel cannot disagree.
	 */
	private const MODULE_KEYS = array( 'field' );

	/**
	 * Returns which attributes a site manager owns on a given block.
	 *
	 * @param string $name Block name.
	 * @return array<int, string>
	 */
	private static function content_keys( string $name ): array {
		if ( DisplayModule::NAME === $name || Block::NAME === $name ) {
			return self::LISTING_KEYS;
		}

		return str_starts_with( $name, 'cscs/divi-' ) ? self::MODULE_KEYS : self::FIELD_KEYS;
	}

	/**
	 * Returns whether a block is one of the plugin's.
	 *
	 * @param string $name Block name.
	 * @return bool
	 */
	private static function ours( string $name ): bool {
		return str_starts_with( $name, 'cscs/' );
	}

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
		// Post data reaches this filter slashed, and a block's attributes are
		// JSON: `{\"set\":\"x\"}` is not JSON any more, so `parse_blocks()`
		// hands back a block with no attributes at all. The guard then read
		// "nothing was submitted", kept everything that was stored, and threw
		// away the one thing a site manager is allowed to change — which looked
		// exactly like the lock working, and was the lock eating the content.
		$content = wp_unslash( (string) ( $data['post_content'] ?? '' ) );

		// Cheap tests first: the overwhelming majority of saves on a site are
		// of something else entirely.
		if ( '' === $content || false === strpos( $content, 'wp:cscs/' ) ) {
			return $data;
		}

		if ( Capabilities::can_manage_design() ) {
			return $data;
		}

		$stored = (int) ( $postarr['ID'] ?? 0 );

		// Straight from the database, so unslashed already.
		$before = 0 === $stored ? '' : (string) get_post_field( 'post_content', $stored );

		$blocks  = parse_blocks( $content );
		$known   = self::collect( parse_blocks( $before ) );
		$seen    = array();
		$changed = false;

		self::walk(
			$blocks,
			static function ( array $block ) use ( &$seen, &$changed, $known ): array {
				$name = (string) ( $block['blockName'] ?? '' );

				// Counted per block name rather than across all of them, so
				// that inserting one kind of block does not shift every other
				// kind's design by one.
				$at            = $seen[ $name ] ?? 0;
				$seen[ $name ] = $at + 1;

				$previous = $known[ $name ][ $at ] ?? array();
				$guarded  = self::merge( $previous, is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array(), false, $name );

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
			$data['post_content'] = wp_slash( serialize_blocks( $blocks ) );
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
	public static function merge( array $old, array $new, bool $may_design, string $name = DisplayModule::NAME ): array {
		if ( $may_design ) {
			return $new;
		}

		$guarded = $old;

		foreach ( self::content_keys( $name ) as $key ) {
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
				$name             = (string) ( $block['blockName'] ?? '' );
				$found[ $name ][] = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();

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

			if ( self::ours( (string) ( $block['blockName'] ?? '' ) ) ) {
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
