<?php
/**
 * Reading and writing the page a kind of course has.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Data;

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
	 * Returns the term a page is about, by its name.
	 *
	 * @param int $post_id Kind page id.
	 * @return \WP_Term|null
	 */
	public function term_for( int $post_id ): ?\WP_Term {
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
