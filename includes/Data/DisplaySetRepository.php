<?php
/**
 * Storage for display sets.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the display sets, all of them, in one option.
 *
 * There will be a handful of these — one per page that lists something — and
 * every listing reads one on every request, so they belong in the autoloaded
 * option table rather than in posts: one read, already in memory, no query.
 *
 * The identifier is a slug rather than a number because it is written by hand
 * in a shortcode, `[cscs_courses set="deti-domovska-stranka"]`, and a number
 * there would be unreadable and unmovable between sites. It never changes after
 * the set is created: renaming a set must not break a page that references it.
 */
final class DisplaySetRepository {

	/**
	 * Option holding every set, keyed by id.
	 */
	public const OPTION = 'cscs_display_sets';

	/**
	 * Returns every set, keyed by id.
	 *
	 * @return array<string, DisplaySet>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$sets = array();

		foreach ( $stored as $id => $data ) {
			if ( ! is_array( $data ) ) {
				continue;
			}

			$data['id'] = (string) $id;
			$set        = DisplaySet::from_array( $data );

			if ( '' !== $set->id ) {
				$sets[ $set->id ] = $set;
			}
		}

		return $sets;
	}

	/**
	 * Returns one set, or null when there is no such thing.
	 *
	 * @param string $id Set id.
	 * @return DisplaySet|null
	 */
	public function find( string $id ): ?DisplaySet {
		return $this->all()[ $id ] ?? null;
	}

	/**
	 * Stores a set, creating it when its id is not taken.
	 *
	 * @param DisplaySet $set Set to store.
	 * @return string The id it was stored under, empty when it could not be.
	 */
	public function save( DisplaySet $set ): string {
		if ( '' === $set->name ) {
			return '';
		}

		$sets = $this->all();

		if ( '' === $set->id ) {
			$set->id = $this->unique_id( $set->name, $sets );
		}

		$sets[ $set->id ] = $set;

		$this->write( $sets );

		return $set->id;
	}

	/**
	 * Removes a set.
	 *
	 * @param string $id Set id.
	 * @return bool Whether there was one to remove.
	 */
	public function delete( string $id ): bool {
		$sets = $this->all();

		if ( ! isset( $sets[ $id ] ) ) {
			return false;
		}

		unset( $sets[ $id ] );

		$this->write( $sets );

		return true;
	}

	/**
	 * Returns the sets as id and name, for a select box.
	 *
	 * @return array<string, string>
	 */
	public function names(): array {
		$names = array();

		foreach ( $this->all() as $id => $set ) {
			$names[ $id ] = $set->name;
		}

		return $names;
	}

	/**
	 * Builds an id nothing is using yet.
	 *
	 * @param string                   $name Set name.
	 * @param array<string, DisplaySet> $sets Existing sets.
	 * @return string
	 */
	private function unique_id( string $name, array $sets ): string {
		$base = function_exists( 'sanitize_title' ) ? sanitize_title( $name ) : DisplaySet::slug( $name );
		$base = '' === $base ? 'set' : $base;
		$id   = $base;
		$next = 2;

		while ( isset( $sets[ $id ] ) ) {
			$id = $base . '-' . $next;
			++$next;
		}

		return $id;
	}

	/**
	 * Writes the sets back.
	 *
	 * @param array<string, DisplaySet> $sets Sets to store.
	 * @return void
	 */
	private function write( array $sets ): void {
		$stored = array();

		foreach ( $sets as $id => $set ) {
			$stored[ $id ] = $set->to_array();
		}

		update_option( self::OPTION, $stored, true );
	}
}
