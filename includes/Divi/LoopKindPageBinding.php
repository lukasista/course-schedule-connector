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
 * The template therefore never stores a real page id at all: it stores
 * {@see self::SENTINEL}, and this class is the only place that ever turns it
 * into something a database query can actually run. It has to do that twice,
 * because Divi builds this same query along two entirely separate paths —
 * and it has to turn it into two different things, because only one of the
 * two things a kind page's loop can mean ever has a single right answer.
 *
 * A real visit to a kind page renders the loop straight to HTML in PHP. That
 * path fires `divi_loop_data_before_execution` on the loop's data after it is
 * built from the module's saved attributes but before it becomes a database
 * query, specifically so a plugin can still adjust it — {@see self::resolve()}.
 * The filter fires with two different shapes depending on where Divi is in
 * that conversion: an unconverted condition list under `meta_query_attrs`, or
 * — confirmed by tracing an actual front-end request, not assumed from source
 * alone — a ready `WP_Query` `meta_query` array nested under `query_args`.
 * Both are narrowed the same way, whichever shape shows up.
 *
 * Editing the shared template in the Visual Builder never takes that path at
 * all — confirmed by tracing an actual Visual Builder session and finding
 * `resolve()` never called. Its canvas instead asks a dedicated REST endpoint,
 * `/divi/v1/loop/query-results`, to run the query and hand back results, and
 * that endpoint fires its own filter, `divi_module_options_loop_post_type_results_query_args`,
 * on the WP_Query arguments it is about to run — {@see self::resolve_preview_query_args()}.
 *
 * Only a real, singular kind page — on the site, or in the Visual Builder
 * editing that exact page — has one right answer for what the sentinel
 * means: that page's own id. Everywhere else this condition ever runs — a
 * carousel of trainers on the home page, the shared template itself open in
 * the Visual Builder, any other template or archive the loop happens to
 * render on — there is no one page to bind it to. So it asks a different
 * question instead: not "is this trainer's page the one I'm looking at",
 * which has no answer there, but "does this trainer have a kind page at
 * all" — a plain `EXISTS` comparison with no value. WordPress has to be
 * given that with the value genuinely absent: a leftover value survives
 * even under `EXISTS` and is quietly compared against as if `compare` were
 * `=`, which with the literal sentinel text still sitting in it is exactly
 * how this used to show no trainers at all outside a kind page. Both hooks
 * resolve the sentinel this same way, so the Visual Builder's preview of
 * anything that is not itself a kind page now shows what the site actually
 * shows there, rather than standing in a borrowed page's trainers for it.
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
	 * Registers the filters.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'divi_loop_data_before_execution', array( $this, 'resolve' ) );
		add_filter( 'divi_module_options_loop_post_type_results_query_args', array( $this, 'resolve_preview_query_args' ), 10, 2 );
	}

	/**
	 * Replaces the sentinel for a real front-end render of the loop.
	 *
	 * On a kind page, that page's own id. Everywhere else — the loop
	 * rendering on any other template, such as a trainer carousel on the
	 * home page — there is no one page to bind it to, so the condition
	 * widens to "has a kind page at all" instead of matching nothing.
	 *
	 * @param array<string, mixed> $loop_data Loop data built from the module's saved attrs.
	 * @return array<string, mixed>
	 */
	public function resolve( array $loop_data ): array {
		$kind_page_id = is_singular( KindType::KIND ) ? get_queried_object_id() : 0;

		if ( ! empty( $loop_data['meta_query_attrs'] ) && is_array( $loop_data['meta_query_attrs'] ) ) {
			$loop_data['meta_query_attrs'] = self::replace_sentinel( $loop_data['meta_query_attrs'], $kind_page_id );
		}

		if ( ! empty( $loop_data['query_args']['meta_query'] ) && is_array( $loop_data['query_args']['meta_query'] ) ) {
			$loop_data['query_args']['meta_query'] = self::replace_sentinel( $loop_data['query_args']['meta_query'], $kind_page_id );
		}

		return $loop_data;
	}

	/**
	 * Replaces the sentinel for the Visual Builder's own preview query.
	 *
	 * Editing a kind page's own session resolves the same way a real visit
	 * to it would. Editing anything else — the shared template itself, or
	 * any other page — has no kind page being viewed, so the preview widens
	 * the same way a real render outside a kind page now does, instead of
	 * borrowing some other page's trainers to show something non-empty.
	 *
	 * @param array<string, mixed> $query_args WP_Query arguments the preview is about to run.
	 * @param array<string, mixed> $params     The REST request's own parameters, including which post is being edited.
	 * @return array<string, mixed>
	 */
	public function resolve_preview_query_args( array $query_args, array $params ): array {
		if ( empty( $query_args['meta_query'] ) || ! is_array( $query_args['meta_query'] ) ) {
			return $query_args;
		}

		$edited_post_id = (int) ( $params['current_post_id'] ?? 0 );

		// Editing a real kind page's own Visual Builder session — same answer resolve() would give.
		// Otherwise there is no page being viewed at all (the shared template isn't one), so this
		// widens exactly the way a real render outside a kind page now does.
		$kind_page_id = ( $edited_post_id > 0 && KindType::KIND === get_post_type( $edited_post_id ) )
			? $edited_post_id
			: 0;

		$query_args['meta_query'] = self::replace_sentinel( $query_args['meta_query'], $kind_page_id );

		return $query_args;
	}

	/**
	 * Narrows one meta-query condition list, whichever shape it was given in.
	 *
	 * A `$kind_page_id` of zero means there is no one real page to bind the
	 * sentinel to, so a condition that holds it widens to "has a kind page
	 * at all" — `EXISTS`, with the value removed outright rather than left
	 * for WordPress to misread as an `=` comparison against it.
	 *
	 * @param array<int, mixed> $conditions    Meta query conditions, raw or WP_Query-ready.
	 * @param int                $kind_page_id The kind page to bind the sentinel to, or zero for none.
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

			$has_sentinel = ( isset( $condition['value'] ) && self::SENTINEL === $condition['value'] )
				|| ( isset( $condition['metaValue'] ) && self::SENTINEL === $condition['metaValue'] );

			if ( ! $has_sentinel ) {
				continue;
			}

			if ( $kind_page_id > 0 ) {
				if ( isset( $condition['value'] ) ) {
					$condition['value'] = (string) $kind_page_id;
				}

				if ( isset( $condition['metaValue'] ) ) {
					$condition['metaValue'] = (string) $kind_page_id;
				}

				continue;
			}

			// No one page to bind to: "has a kind page at all" instead. WordPress reads a
			// value left behind even under EXISTS and quietly compares against it as if
			// compare were "=" — the sentinel text itself, which matches no trainer — so
			// the value has to go, not just the compare type.
			unset( $condition['value'], $condition['metaValue'] );

			$condition['compare']     = 'EXISTS';
			$condition['metaCompare'] = 'EXISTS';
		}
		unset( $condition );

		return $conditions;
	}
}
