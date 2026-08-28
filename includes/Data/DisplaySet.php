<?php
/**
 * A named configuration of what to show.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Data;

use CSCS\Support\Normalise;

defined( 'ABSPATH' ) || exit;

/**
 * Everything a listing needs to know, named once and reused everywhere.
 *
 * A display set is the answer to a question the site owner asked in the first
 * conversation: the person who maintains the courses must be able to change
 * what a page shows without opening a page builder, and the person who owns the
 * design must keep the design. So a set holds content decisions — which
 * columns, what order, which rooms, whether rentals belong here, what the empty
 * state says — and holds no design at all. The builder module offers a site
 * manager exactly one field: which set.
 *
 * That split only works if a set is a real object rather than a bag of stray
 * options, which is what this class is for. It normalises whatever it is handed
 * — a form post, a WP-CLI argument, an old option written by a previous version
 * — into the same shape, drops what it does not recognise, and never throws:
 * a set that half-loads is worse than a set that loads with a default in the
 * one field somebody mistyped.
 *
 * It needs neither WordPress nor the database — it uses two WordPress helpers
 * where they exist and falls back where they do not — so it can be tested on
 * its own, and it is where the rules live rather than in the screen that edits
 * it.
 */
final class DisplaySet {

	/**
	 * A listing of courses.
	 */
	public const TYPE_COURSES = 'courses';

	/**
	 * A timetable of individual classes.
	 */
	public const TYPE_SCHEDULE = 'schedule';

	/**
	 * The set's identifier, unique within the site.
	 *
	 * @var string
	 */
	public string $id;

	/**
	 * What a person calls it.
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * Which of the two listings this configures.
	 *
	 * @var string
	 */
	public string $type;

	/**
	 * Columns, in the order they appear.
	 *
	 * @var array<int, string>
	 */
	public array $columns;

	/**
	 * Column labels a person preferred to the built-in ones.
	 *
	 * @var array<string, string>
	 */
	public array $labels;

	/**
	 * Room ids to keep, as iSport numbers them, or an empty list for all.
	 *
	 * The remote id rather than a term, because that is what a class carries
	 * and what a course records, and because it survives a rename on either
	 * side.
	 *
	 * @var array<int, int>
	 */
	public array $rooms;

	/**
	 * Trainer term ids to keep, or an empty list for all of them.
	 *
	 * @var array<int, int>
	 */
	public array $trainers;

	/**
	 * Activity term ids to keep, or an empty list for all of them.
	 *
	 * @var array<int, int>
	 */
	public array $activities;

	/**
	 * Which classes to cover: `term`, `week`, `days` or `custom`.
	 *
	 * @var string
	 */
	public string $range;

	/**
	 * How many days ahead, when the range is `days`.
	 *
	 * @var int
	 */
	public int $range_days;

	/**
	 * First day, when the range is `custom`.
	 *
	 * @var string
	 */
	public string $date_from;

	/**
	 * Last day, when the range is `custom`.
	 *
	 * @var string
	 */
	public string $date_to;

	/**
	 * Whether to drop courses with no places left.
	 *
	 * @var bool
	 */
	public bool $only_available;

	/**
	 * Whether hall rentals and outside clubs belong in this listing.
	 *
	 * @var bool
	 */
	public bool $include_rentals;

	/**
	 * Cancelled classes: `inherit`, `show` or `hide`.
	 *
	 * @var string
	 */
	public string $cancelled;

	/**
	 * Sort key.
	 *
	 * @var string
	 */
	public string $sort;

	/**
	 * Sort direction, `asc` or `desc`.
	 *
	 * @var string
	 */
	public string $order;

	/**
	 * Items per page, 0 for all of them.
	 *
	 * @var int
	 */
	public int $per_page;

	/**
	 * Heading above the listing.
	 *
	 * @var string
	 */
	public string $heading;

	/**
	 * Text on the booking button.
	 *
	 * @var string
	 */
	public string $cta;

	/**
	 * What to say when the listing is empty.
	 *
	 * @var string
	 */
	public string $empty_text;

	/**
	 * What to say instead of the button when a course is full.
	 *
	 * @var string
	 */
	public string $full_text;

	/**
	 * Places at or below which the listing warns that a course is filling up.
	 *
	 * @var int
	 */
	public int $few_places;

	/**
	 * Builds a set from anything shaped roughly like one.
	 *
	 * @param array<string, mixed> $data Stored or submitted values.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$set = new self();

		$set->type = self::TYPE_SCHEDULE === Normalise::to_string( $data['type'] ?? '' )
			? self::TYPE_SCHEDULE
			: self::TYPE_COURSES;

		$set->id   = self::slug( Normalise::to_string( $data['id'] ?? '' ) );
		$set->name = self::text( $data['name'] ?? '' );

		$set->columns = self::columns_from( $data['columns'] ?? array(), $set->type );
		$set->labels  = self::labels_from( $data['labels'] ?? array(), $set->columns );

		$set->rooms      = self::ids( $data['rooms'] ?? array() );
		$set->trainers   = self::ids( $data['trainers'] ?? array() );
		$set->activities = self::ids( $data['activities'] ?? array() );

		$set->range      = self::one_of( Normalise::to_string( $data['range'] ?? '' ), array( 'term', 'week', 'days', 'custom' ), 'term' );
		$set->range_days = min( 366, max( 1, Normalise::to_int( $data['range_days'] ?? 14 ) ) );
		$set->date_from  = (string) Normalise::to_date( $data['date_from'] ?? '' );
		$set->date_to    = (string) Normalise::to_date( $data['date_to'] ?? '' );

		$set->only_available  = Normalise::to_bool( $data['only_available'] ?? false );
		$set->include_rentals = Normalise::to_bool( $data['include_rentals'] ?? false );
		$set->cancelled       = self::one_of( Normalise::to_string( $data['cancelled'] ?? '' ), array( 'inherit', 'show', 'hide' ), 'inherit' );

		$set->sort     = self::one_of( Normalise::to_string( $data['sort'] ?? '' ), array_keys( self::sorts( $set->type ) ), self::default_sort( $set->type ) );
		$set->order    = 'desc' === Normalise::to_string( $data['order'] ?? '' ) ? 'desc' : 'asc';
		$set->per_page = min( 500, max( 0, Normalise::to_int( $data['per_page'] ?? 0 ) ) );

		$set->heading    = self::text( $data['heading'] ?? '' );
		$set->cta        = self::text( $data['cta'] ?? '' );
		$set->empty_text = self::text( $data['empty_text'] ?? '' );
		$set->full_text  = self::text( $data['full_text'] ?? '' );
		$set->few_places = min( 99, max( 0, Normalise::to_int( $data['few_places'] ?? 3 ) ) );

		return $set;
	}

	/**
	 * Returns the set as an array, ready to store.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'              => $this->id,
			'name'            => $this->name,
			'type'            => $this->type,
			'columns'         => $this->columns,
			'labels'          => $this->labels,
			'rooms'           => $this->rooms,
			'trainers'        => $this->trainers,
			'activities'      => $this->activities,
			'range'           => $this->range,
			'range_days'      => $this->range_days,
			'date_from'       => $this->date_from,
			'date_to'         => $this->date_to,
			'only_available'  => $this->only_available,
			'include_rentals' => $this->include_rentals,
			'cancelled'       => $this->cancelled,
			'sort'            => $this->sort,
			'order'           => $this->order,
			'per_page'        => $this->per_page,
			'heading'         => $this->heading,
			'cta'             => $this->cta,
			'empty_text'      => $this->empty_text,
			'full_text'       => $this->full_text,
			'few_places'      => $this->few_places,
		);
	}

	/**
	 * Returns the columns a listing of this kind may show.
	 *
	 * The order here is the order a new set starts with, and the keys are what
	 * the renderer asks for. A key nobody recognises is dropped rather than
	 * rendered as an empty column, which is the sort of thing that reaches a
	 * live page and nobody notices for a week.
	 *
	 * @param string $type Listing type.
	 * @return array<int, string>
	 */
	public static function catalogue( string $type ): array {
		if ( self::TYPE_SCHEDULE === $type ) {
			return array( 'date', 'time', 'activity', 'course', 'room', 'trainer', 'price', 'places', 'state', 'button' );
		}

		return array( 'name', 'activity', 'days', 'day', 'hours', 'gender', 'level', 'room', 'trainer', 'period', 'lessons', 'price', 'places', 'button' );
	}

	/**
	 * Returns the sort keys a listing of this kind may use.
	 *
	 * @param string $type Listing type.
	 * @return array<string, string> Key to the field it sorts on.
	 */
	public static function sorts( string $type ): array {
		if ( self::TYPE_SCHEDULE === $type ) {
			return array(
				'start' => 'stamp_from',
				'room'  => 'tab_name',
				'name'  => 'activity_name',
			);
		}

		return array(
			'name'   => 'post_title',
			'start'  => 'stamp_from',
			'price'  => 'price',
			'places' => 'available',
		);
	}

	/**
	 * Returns the sort a new set of this kind starts with.
	 *
	 * @param string $type Listing type.
	 * @return string
	 */
	public static function default_sort( string $type ): string {
		return self::TYPE_SCHEDULE === $type ? 'start' : 'name';
	}

	/**
	 * Turns a name into an identifier that can live in a URL and a shortcode.
	 *
	 * @param string $value Proposed id or name.
	 * @return string
	 */
	public static function slug( string $value ): string {
		$value = strtolower( trim( $value ) );

		if ( function_exists( 'remove_accents' ) ) {
			$value = remove_accents( $value );
		}

		$value = (string) preg_replace( '/[^a-z0-9]+/', '-', $value );

		return trim( $value, '-' );
	}

	/**
	 * Keeps the columns this listing knows, in the order they were given.
	 *
	 * @param mixed  $value Submitted columns.
	 * @param string $type  Listing type.
	 * @return array<int, string>
	 */
	private static function columns_from( $value, string $type ): array {
		$catalogue = self::catalogue( $type );

		if ( ! is_array( $value ) ) {
			return $catalogue;
		}

		$columns = array();

		foreach ( $value as $column ) {
			$column = Normalise::to_string( $column );

			if ( in_array( $column, $catalogue, true ) && ! in_array( $column, $columns, true ) ) {
				$columns[] = $column;
			}
		}

		// A set with no columns at all would render an empty table and look
		// like a broken plugin rather than an empty choice.
		return array() === $columns ? $catalogue : $columns;
	}

	/**
	 * Keeps the labels that belong to a column this set actually shows.
	 *
	 * @param mixed              $value   Submitted labels.
	 * @param array<int, string> $columns Columns kept.
	 * @return array<string, string>
	 */
	private static function labels_from( $value, array $columns ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$labels = array();

		foreach ( $columns as $column ) {
			$label = self::text( $value[ $column ] ?? '' );

			if ( '' !== $label ) {
				$labels[ $column ] = $label;
			}
		}

		return $labels;
	}

	/**
	 * Reduces a list to unique positive integers.
	 *
	 * @param mixed $value Submitted ids.
	 * @return array<int, int>
	 */
	private static function ids( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$ids = array();

		foreach ( $value as $id ) {
			$id = Normalise::to_int( $id );

			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Returns the value when it is allowed, and the fallback when it is not.
	 *
	 * @param string             $value    Submitted value.
	 * @param array<int, string> $allowed  Allowed values.
	 * @param string             $fallback Value to use otherwise.
	 * @return string
	 */
	private static function one_of( string $value, array $allowed, string $fallback ): string {
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Reduces a value to a single line of plain text.
	 *
	 * @param mixed $value Submitted text.
	 * @return string
	 */
	private static function text( $value ): string {
		$value = Normalise::to_string( $value );

		if ( function_exists( 'wp_strip_all_tags' ) ) {
			$value = wp_strip_all_tags( $value );
		} else {
			// What wp_strip_all_tags does, kept in step deliberately: a script's
			// contents go with the script, so the fallback cannot be the more
			// permissive of the two.
			$value = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\1>@si', '', $value );
			$value = strip_tags( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- The WordPress helper is used above whenever it exists; this branch is what lets the class be tested without WordPress.
		}

		return trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
	}
}
