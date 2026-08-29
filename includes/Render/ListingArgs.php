<?php
/**
 * What a visitor asked a listing for.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

defined( 'ABSPATH' ) || exit;

use CSCS\Support\Normalise;

/**
 * The part of a listing a visitor chooses, as opposed to the part a set decides.
 *
 * A display set says what a listing is: which columns, which rooms, how far
 * ahead. This says where in it somebody currently is — which week, which page,
 * which room they narrowed it to. The two are deliberately separate objects: a
 * visitor pressing "next week" must not be able to change what the set means,
 * and every one of these values arrives from a URL, which is to say from
 * anybody at all.
 *
 * Nothing here touches WordPress or the database, so the clamping can be tested
 * as the rule it is.
 */
final class ListingArgs {

	/**
	 * Furthest a visitor may page or step, in either direction.
	 *
	 * A listing is a term's worth of classes; a request for week nine hundred
	 * is not a person browsing.
	 */
	private const LIMIT = 60;

	/**
	 * Page number, from 1.
	 *
	 * @var int
	 */
	public int $page;

	/**
	 * Weeks away from the one the visitor is in.
	 *
	 * @var int
	 */
	public int $week;

	/**
	 * Room the visitor narrowed to, or 0 for all of them.
	 *
	 * @var int
	 */
	public int $room;

	/**
	 * Builds arguments from whatever arrived.
	 *
	 * @param array<string, mixed> $input Query parameters.
	 * @return self
	 */
	public static function from_array( array $input ): self {
		$args = new self();

		$args->page = max( 1, min( self::LIMIT, Normalise::to_int( $input['page'] ?? 1 ) ) );
		$args->week = max( -self::LIMIT, min( self::LIMIT, Normalise::to_int( $input['week'] ?? 0 ) ) );
		$args->room = max( 0, Normalise::to_int( $input['room'] ?? 0 ) );

		return $args;
	}

	/**
	 * Returns the arguments as query parameters, leaving out the defaults.
	 *
	 * A link to the first page of this week in every room is a link to the
	 * listing itself, and should look like one.
	 *
	 * @return array<string, int>
	 */
	public function to_query(): array {
		$query = array();

		if ( 1 !== $this->page ) {
			$query['cscs_page'] = $this->page;
		}

		if ( 0 !== $this->week ) {
			$query['cscs_week'] = $this->week;
		}

		if ( 0 !== $this->room ) {
			$query['cscs_room'] = $this->room;
		}

		return $query;
	}

	/**
	 * Returns the same arguments with one value replaced.
	 *
	 * Changing the week or the room sends a visitor back to the first page:
	 * page four of last week has nothing to do with page four of this one.
	 *
	 * @param string $key   One of `page`, `week` or `room`.
	 * @param int    $value New value.
	 * @return self
	 */
	public function with( string $key, int $value ): self {
		$input = array(
			'page' => 'page' === $key ? $value : $this->page,
			'week' => 'week' === $key ? $value : $this->week,
			'room' => 'room' === $key ? $value : $this->room,
		);

		if ( 'page' !== $key ) {
			$input['page'] = 1;
		}

		return self::from_array( $input );
	}

	/**
	 * Returns what a form choosing a room must carry across.
	 *
	 * A form submitted by a browser replaces the whole query string with its
	 * own fields, so anything the address already said has to be sent again as
	 * a hidden field or it is lost. Two things are dropped on purpose: the room
	 * itself, because the form is what chooses it, and the page, because
	 * choosing a room starts again at the first one. Parameters that are not
	 * this plugin's are kept — a listing has no business dropping whatever else
	 * a site puts in its addresses.
	 *
	 * @param array<string, mixed> $query What the page's address already says.
	 * @return array<string, string>
	 */
	public function carried( array $query ): array {
		$fields = array();

		foreach ( $query as $key => $value ) {
			if ( is_scalar( $value ) && 0 !== strpos( (string) $key, 'cscs_' ) ) {
				$fields[ (string) $key ] = (string) $value;
			}
		}

		foreach ( $this->with( 'room', 0 )->to_query() as $key => $value ) {
			$fields[ (string) $key ] = (string) $value;
		}

		return $fields;
	}

	/**
	 * Reads the arguments a request carries.
	 *
	 * @param array<string, mixed> $request Raw request parameters.
	 * @return self
	 */
	public static function from_request( array $request ): self {
		return self::from_array(
			array(
				'page' => $request['cscs_page'] ?? 1,
				'week' => $request['cscs_week'] ?? 0,
				'room' => $request['cscs_room'] ?? 0,
			)
		);
	}
}
