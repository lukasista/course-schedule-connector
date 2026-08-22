<?php
/**
 * How rooms are named and ordered on the site.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Data;

use CSCS\Support\Normalise;

defined( 'ABSPATH' ) || exit;

/**
 * Lets a site say "Hala 1" where the booking system says something else.
 *
 * The names in iSport are written for the people who run the timetable, not for
 * the people reading the website: "Gymnastická hala 1 a veřejnost" is precise
 * and unreadable in a table column. Nothing can be renamed in iSport without
 * disturbing the gym's own work, so the site keeps its own label alongside the
 * remote one, plus an order, a colour and a way to hide a room entirely.
 *
 * The remote id is the key, not the name. A room renamed in iSport is the same
 * room, and a label that survives the rename is the whole point.
 */
final class RoomMap {

	/**
	 * Option holding the map, keyed by remote room id.
	 */
	public const OPTION = 'cscs_rooms';

	/**
	 * Returns the stored map.
	 *
	 * @return array<int, array{label: string, order: int, colour: string, hidden: bool}>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$map = array();

		foreach ( $stored as $id => $row ) {
			$id = (int) $id;

			if ( 0 !== $id && is_array( $row ) ) {
				$map[ $id ] = self::row( $row );
			}
		}

		return $map;
	}

	/**
	 * Stores the map, dropping the rows that say nothing.
	 *
	 * A room with no label, no colour, the default order and shown as normal is
	 * a room nobody has configured. Storing it would grow the option every time
	 * a new room appeared in the timetable and mean nothing.
	 *
	 * @param array<int, array<string, mixed>> $rows Submitted rows.
	 * @return int Number of rooms actually configured.
	 */
	public function save( array $rows ): int {
		$map = array();

		foreach ( $rows as $id => $row ) {
			$id = (int) $id;

			if ( 0 === $id || ! is_array( $row ) ) {
				continue;
			}

			$normalised = self::row( $row );

			if ( ! self::is_empty( $normalised ) ) {
				$map[ $id ] = $normalised;
			}
		}

		update_option( self::OPTION, $map, true );

		return count( $map );
	}

	/**
	 * Applies the map to the rooms the timetable actually mentions.
	 *
	 * @param array<int, string> $rooms Room id to the remote name.
	 * @return array<int, array{id: int, name: string, remote: string, colour: string, hidden: bool, order: int}>
	 */
	public function decorate( array $rooms ): array {
		return self::apply( $rooms, $this->all() );
	}

	/**
	 * Merges rooms with their configuration and puts them in order.
	 *
	 * Hidden rooms are kept in the list rather than dropped: this is what the
	 * screen edits, and a room that disappeared from it the moment it was
	 * hidden could never be shown again. Filtering belongs to whoever renders.
	 *
	 * @param array<int, string>                                                            $rooms Room id to the remote name.
	 * @param array<int, array{label: string, order: int, colour: string, hidden: bool}> $map   Stored configuration.
	 * @return array<int, array{id: int, name: string, remote: string, colour: string, hidden: bool, order: int}>
	 */
	public static function apply( array $rooms, array $map ): array {
		$decorated = array();

		foreach ( $rooms as $id => $remote ) {
			$id     = (int) $id;
			$remote = Normalise::to_string( $remote );
			$row    = $map[ $id ] ?? self::row( array() );

			$decorated[] = array(
				'id'     => $id,
				'name'   => '' === $row['label'] ? $remote : $row['label'],
				'remote' => $remote,
				'colour' => $row['colour'],
				'hidden' => $row['hidden'],
				'order'  => $row['order'],
			);
		}

		// Same order, then alphabetical by what the site actually shows, so that
		// leaving every order at nought still produces a sensible list rather
		// than whatever order the database felt like.
		usort(
			$decorated,
			static function ( array $first, array $second ): int {
				return array( $first['order'], $first['name'] ) <=> array( $second['order'], $second['name'] );
			}
		);

		return $decorated;
	}

	/**
	 * Normalises one row.
	 *
	 * @param array<string, mixed> $row Submitted or stored row.
	 * @return array{label: string, order: int, colour: string, hidden: bool}
	 */
	public static function row( array $row ): array {
		return array(
			'label'  => trim( Normalise::to_string( $row['label'] ?? '' ) ),
			'order'  => min( 999, max( 0, Normalise::to_int( $row['order'] ?? 0 ) ) ),
			'colour' => (string) Normalise::to_hex_colour( $row['colour'] ?? '' ),
			'hidden' => Normalise::to_bool( $row['hidden'] ?? false ),
		);
	}

	/**
	 * Whether a row says nothing worth storing.
	 *
	 * @param array{label: string, order: int, colour: string, hidden: bool} $row Normalised row.
	 * @return bool
	 */
	private static function is_empty( array $row ): bool {
		return '' === $row['label'] && '' === $row['colour'] && 0 === $row['order'] && false === $row['hidden'];
	}
}
