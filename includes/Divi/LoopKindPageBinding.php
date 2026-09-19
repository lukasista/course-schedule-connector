<?php
/**
 * Binds a Loop module's trainer filter to whichever kind page is showing it.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Divi;

defined( 'ABSPATH' ) || exit;

use CSCS\Data\KindRepository;
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
 * into one. It has to do that twice, because Divi builds this same query
 * along two entirely separate paths and only one of them is the one this was
 * first written for.
 *
 * A real visit to a kind page renders the loop straight to HTML in PHP. That
 * path fires `divi_loop_data_before_execution` on the loop's data after it is
 * built from the module's saved attributes but before it becomes a database
 * query, specifically so a plugin can still adjust it — {@see self::resolve()}.
 * The filter fires with two different shapes depending on where Divi is in
 * that conversion: an unconverted condition list under `meta_query_attrs`, or
 * — confirmed by tracing an actual front-end request, not assumed from source
 * alone — a ready `WP_Query` `meta_query` array nested under `query_args`.
 * Both are narrowed so neither leaves every trainer showing on every page.
 *
 * Editing the shared template in the Visual Builder never takes that path at
 * all — confirmed by tracing an actual Visual Builder session and finding
 * `resolve()` never called. Its canvas instead asks a dedicated REST endpoint,
 * `/divi/v1/loop/query-results`, to run the query and hand back results, and
 * that endpoint fires its own filter, `divi_module_options_loop_post_type_results_query_args`,
 * on the WP_Query arguments it is about to run — {@see self::resolve_preview_query_args()}.
 * Left unhandled, that endpoint runs the query with the literal sentinel
 * still in it, which matches no trainer, and whoever is editing the template
 * sees an empty loop to design against instead of a trainer card.
 *
 * That second path has no kind page being viewed either — a shared template
 * is not any one page — so there is no real id to bind the sentinel to the
 * way {@see self::resolve()} does. Reaching that hook at all only ever
 * happens from inside that one REST endpoint, which only the Visual Builder
 * ever calls, so it is free to bind the sentinel to a real, published example
 * page instead: whichever one {@see KindRepository::example_page_with_trainers()}
 * finds already has a trainer, so whoever is editing sees an actual photo and
 * name rather than another empty state. A real front-end visit never reaches
 * this hook, so this substitution can never show on the site itself — it only
 * ever narrows or stands in for the Visual Builder's own preview query, never
 * widens a real one.
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
	 * Replaces the sentinel for the Visual Builder's own preview query.
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
		// Otherwise there is no page being viewed at all (the shared template isn't one), so an
		// example page stands in, purely so there is something to design against.
		$kind_page_id = ( $edited_post_id > 0 && KindType::KIND === get_post_type( $edited_post_id ) )
			? $edited_post_id
			: ( new KindRepository() )->example_page_with_trainers();

		if ( 0 === $kind_page_id ) {
			return $query_args;
		}

		$query_args['meta_query'] = self::replace_sentinel( $query_args['meta_query'], $kind_page_id );

		return $query_args;
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
