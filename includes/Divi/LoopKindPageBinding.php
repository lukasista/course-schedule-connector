<?php
/**
 * Binds a Loop module's trainer filter to whichever kind page is showing it.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Divi;

defined( 'ABSPATH' ) || exit;

use CSCS\Data\KindType;
use CSCS\Data\TrainerType;

/**
 * Resolves the one piece a native Divi Loop cannot express on its own.
 *
 * The shared Theme Builder template draws a kind page's trainers with a
 * native Divi Group module set to loop over
 * {@see \CSCS\Data\TrainerType::TRAINER}, filtered by a meta query on
 * {@see \CSCS\Data\TrainerType::META_KIND_PAGE}. Divi's own meta-query editor
 * has no dynamic-content option for that condition's value — every field in
 * it is a plain text input with no token picker, confirmed by reading the
 * Visual Builder's own shipped source rather than assumed — so whatever value
 * is saved in the template can only ever be one fixed page's id, which would
 * show the same trainers on every kind page instead of each one's own.
 *
 * Divi fires `divi_loop_data_before_execution` on the loop's data after it is
 * built from the module's saved attributes but before it becomes a database
 * query, specifically so a plugin can still adjust it. The template therefore
 * never stores a real page id at all: it stores {@see self::SENTINEL}, and
 * this is the one place that ever turns it into one, using whichever kind
 * page is actually being viewed. A loop saved with any other value, or one
 * rendered somewhere that is not a single kind page, is left exactly as
 * saved — this only ever narrows a query, never widens one.
 *
 * The filter fires with two different shapes depending on where Divi is in
 * turning the module's attributes into a database query: the Visual
 * Builder's own editor-preview path still carries the raw, unconverted
 * condition list under `meta_query_attrs`, but the real front-end render —
 * confirmed by tracing an actual request, not assumed from source alone —
 * has already turned it into a ready `WP_Query` `meta_query` array nested
 * under `query_args`. Both are narrowed here so neither path is left
 * showing every trainer on every kind page.
 */
final class LoopKindPageBinding {

	/**
	 * Stands in for "the kind page this loop is currently showing on".
	 *
	 * Never a real value a trainer's kind-page meta could hold — that meta is
	 * always a post id — so there is nothing for it to collide with.
	 */
	public const SENTINEL = '%current_kind_page%';

	/**
	 * Registers the filter.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'divi_loop_data_before_execution', array( $this, 'resolve' ) );
	}

	/**
	 * Replaces the sentinel with the kind page actually being viewed.
	 *
	 * @param array<string, mixed> $loop_data Loop data built from the module's saved attrs.
	 * @return array<string, mixed>
	 */
	public function resolve( array $loop_data ): array {
		if ( ! is_singular( KindType::KIND ) ) {
			return $loop_data;
		}

		$kind_page_id = get_queried_object_id();

		if ( 0 === $kind_page_id ) {
			return $loop_data;
		}

		if ( ! empty( $loop_data['meta_query_attrs'] ) && is_array( $loop_data['meta_query_attrs'] ) ) {
			$loop_data['meta_query_attrs'] = self::replace_sentinel( $loop_data['meta_query_attrs'], $kind_page_id );
		}

		if ( ! empty( $loop_data['query_args']['meta_query'] ) && is_array( $loop_data['query_args']['meta_query'] ) ) {
			$loop_data['query_args']['meta_query'] = self::replace_sentinel( $loop_data['query_args']['meta_query'], $kind_page_id );
		}

		return $loop_data;
	}

	/**
	 * Narrows one meta-query condition list, whichever shape it was given in.
	 *
	 * @param array<int, mixed> $conditions    Meta query conditions, raw or WP_Query-ready.
	 * @param int                $kind_page_id The kind page to bind the sentinel to.
	 * @return array<int, mixed>
	 */
	private static function replace_sentinel( array $conditions, int $kind_page_id ): array {
		foreach ( $conditions as &$condition ) {
			if ( ! is_array( $condition ) ) {
				continue;
			}

			$key = $condition['key'] ?? $condition['metaKey'] ?? '';

			if ( TrainerType::META_KIND_PAGE !== $key ) {
				continue;
			}

			if ( isset( $condition['value'] ) && self::SENTINEL === $condition['value'] ) {
				$condition['value'] = (string) $kind_page_id;
			}

			if ( isset( $condition['metaValue'] ) && self::SENTINEL === $condition['metaValue'] ) {
				$condition['metaValue'] = (string) $kind_page_id;
			}
		}
		unset( $condition );

		return $conditions;
	}
}
