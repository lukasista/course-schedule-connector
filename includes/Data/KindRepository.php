<?php
/**
 * Reading and writing the page a kind of course has.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Data;

use CSCS\Support\Markup;
use CSCS\Support\Normalise;

defined( 'ABSPATH' ) || exit;

/**
 * Pairs a kind of course with the page written about it.
 *
 * The same arrangement the trainers have, for the same reason: the taxonomy
 * carries the grouping and the post carries the words. Pairing is on the
 * normalised name, because that is the only identifier both sides have — and
 * because it survives a page being retitled, which somebody will do the first
 * time "Gymnastika" wants to read "Gymnastika pro děti" on its own page.
 */
final class KindRepository {

	/**
	 * The settings a page may carry, named as the blocks name them.
	 *
	 * @var array<int, string>
	 */
	public const FILTER_KEYS = array(
		'filterGenders',
		'filterLevels',
		'filterAgeMin',
		'filterAgeMax',
		'filterSort',
		'filterOrder',
		'filterLimit',
	);

	/**
	 * Returns the key a name is paired by.
	 *
	 * @param string $name Kind name.
	 * @return string
	 */
	public static function key( string $name ): string {
		return Normalise::match_key_loose( $name );
	}

	/**
	 * Returns the page written about a kind, or zero where there is none.
	 *
	 * @param string $name Kind name.
	 * @return int Post id.
	 */
	public function find( string $name ): int {
		$key = self::key( $name );

		if ( '' === $key ) {
			return 0;
		}

		$found = get_posts(
			array(
				'post_type'        => KindType::KIND,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'meta_key'         => KindType::META_KEY_NAME, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- The key is the pairing; there is nothing else to find the page by.
				'meta_value'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- See above.
			)
		);

		return array() === $found ? 0 : (int) $found[0];
	}

	/**
	 * Makes sure a kind has a page, and returns it.
	 *
	 * Only the name is ever written, and only when the page is made. A page
	 * that already exists keeps its title and everything else on it: a
	 * synchronisation that corrected a heading somebody wrote, every night,
	 * would be a bug wearing the clothes of a feature.
	 *
	 * @param string $name Kind name.
	 * @return int Post id, or zero when there is nothing to make a page for.
	 */
	public function ensure( string $name ): int {
		$name = trim( $name );

		if ( '' === self::key( $name ) ) {
			return 0;
		}

		$post_id = $this->find( $name );

		if ( 0 !== $post_id ) {
			return $post_id;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => KindType::KIND,
				'post_status'  => 'publish',
				'post_title'   => $name,
				'post_content' => '',
			),
			true
		);

		if ( $post_id instanceof \WP_Error || 0 === (int) $post_id ) {
			return 0;
		}

		update_post_meta( (int) $post_id, KindType::META_KEY_NAME, self::key( $name ) );

		return (int) $post_id;
	}

	/**
	 * Returns the description most of a kind's courses share.
	 *
	 * Every course of a kind carries the same paragraph often enough that the
	 * kind's page can start from it — but not always: eleven of this gym's
	 * twenty-five kinds hold more than one wording, and Gymnastika holds five
	 * across twenty-two courses. So the answer is the one the most courses
	 * agree on, which for Gymnastika is the text eighteen of them share, and a
	 * person can replace it with any single course's afterwards.
	 *
	 * @param int $post_id Kind page id.
	 * @return string Markup, or an empty string where no course says anything.
	 */
	public function prevailing_description( int $post_id ): string {
		$counts = array();
		$texts  = array();

		foreach ( $this->courses( $post_id ) as $course_id ) {
			$text = trim( (string) get_post_meta( $course_id, '_cscs_api_description', true ) );

			if ( '' === $text ) {
				continue;
			}

			$key = md5( $text );

			$counts[ $key ] = ( $counts[ $key ] ?? 0 ) + 1;
			$texts[ $key ]  = $text;
		}

		if ( array() === $counts ) {
			return '';
		}

		arsort( $counts );

		return (string) $texts[ (string) array_key_first( $counts ) ];
	}

	/**
	 * Writes a description onto a kind's page where it has none.
	 *
	 * Only where it has none. A page somebody has written on is a page nobody
	 * asked this to touch, and the whole point of a page beside the term is
	 * that what is written on it stays written.
	 *
	 * @param int $post_id Kind page id.
	 * @return bool Whether anything was written.
	 */
	public function fill_description( int $post_id ): bool {
		if ( '' !== trim( (string) get_post_field( 'post_content', $post_id ) ) ) {
			return false;
		}

		$description = $this->prevailing_description( $post_id );

		if ( '' === $description ) {
			return false;
		}

		return $this->write_description( $post_id, $description );
	}

	/**
	 * Writes a description onto a kind's page, whatever is there.
	 *
	 * For the button on the editing screen: somebody asked for this text, in so
	 * many words, and was told what it would replace.
	 *
	 * @param int    $post_id     Kind page id.
	 * @param string $description Markup.
	 * @return bool Whether anything was written.
	 */
	public function write_description( int $post_id, string $description ): bool {
		$description = trim( (string) preg_replace( '#^(?:\s|<br\s*/?>)+#i', '', $description ) );

		if ( '' === $description ) {
			return false;
		}

		// The remote editor nests lists inside lists; a page that keeps that
		// would show two levels of bullets in whatever renders it next.
		$description = Markup::flatten_lists( $description );

		$written = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => wp_kses_post( $description ),
			),
			true
		);

		return ! $written instanceof \WP_Error;
	}

	/**
	 * Returns the term a page is about, by its name.
	 *
	 * @param int $post_id Kind page id.
	 * @return \WP_Term|null
	 */
	public function term_for( int $post_id ): ?\WP_Term {
		// A page pointed at a term by hand says so, and that answer is not
		// second-guessed by the name. It is how "Gymnastika dívky" can be a
		// page of its own: its title pairs with no term at all, and it is not
		// meant to.
		$chosen = (int) get_post_meta( $post_id, KindType::META_TERM, true );

		if ( 0 !== $chosen ) {
			$term = get_term( $chosen, PostType::KIND );

			if ( $term instanceof \WP_Term ) {
				return $term;
			}
		}

		$key = (string) get_post_meta( $post_id, KindType::META_KEY_NAME, true );

		if ( '' === $key ) {
			$key = self::key( (string) get_post_field( 'post_title', $post_id ) );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => PostType::KIND,
				'hide_empty' => false,
			)
		);

		if ( ! is_array( $terms ) ) {
			return null;
		}

		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term && self::key( $term->name ) === $key ) {
				return $term;
			}
		}

		return null;
	}

	/**
	 * Returns which of its kind's courses a page shows.
	 *
	 * The answer a theme builder template cannot give. A template is one design
	 * for every page of a type, so a filter set on the module in it would be
	 * set for all of them — "girls" on the page for boys. So the page carries
	 * the question and the template only prints the answer, which is the whole
	 * arrangement that makes one template serve twenty-six pages saying
	 * twenty-six different things.
	 *
	 * @param int $post_id Kind page id.
	 * @return array<string, mixed> Settings, in the shape the blocks use.
	 */
	public function filter( int $post_id ): array {
		$stored = get_post_meta( $post_id, KindType::META_FILTER, true );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$filter = array();

		foreach ( self::FILTER_KEYS as $key ) {
			$value = $stored[ $key ] ?? '';

			if ( '' !== $value && null !== $value ) {
				$filter[ $key ] = $value;
			}
		}

		return $filter;
	}

	/**
	 * Returns the courses of the kind a page is about, ready to render.
	 *
	 * Archived courses are left out the way they are everywhere else: iSport no
	 * longer offers them, and a page listing what somebody can sign up for
	 * should not be the one place they still appear.
	 *
	 * @param int $post_id Kind page id.
	 * @return array<int, int> Course post ids.
	 */
	public function courses( int $post_id ): array {
		$term = $this->term_for( $post_id );

		if ( ! $term instanceof \WP_Term ) {
			return array();
		}

		$found = get_posts(
			array(
				'post_type'        => PostType::COURSE,
				'post_status'      => 'publish',
				'posts_per_page'   => 500,
				'fields'           => 'ids',
				'orderby'          => 'title',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- The point of the taxonomy is to be asked this.
					array(
						'taxonomy' => PostType::KIND,
						'field'    => 'term_id',
						'terms'    => array( $term->term_id ),
					),
				),
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- A closed course is not on offer, and this page is a list of what is.
					array(
						'key'     => CourseRepository::META_STATUS,
						'value'   => CourseRepository::STATUS_ARCHIVED,
						'compare' => '!=',
					),
				),
			)
		);

		return array_map( 'intval', $found );
	}
}
