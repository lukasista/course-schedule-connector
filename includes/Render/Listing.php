<?php
/**
 * What a template is handed.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

use CSCS\Data\Audience;
use CSCS\Data\DisplaySet;
use CSCS\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * One listing, worked out, ready to be printed.
 *
 * Templates are meant to be replaced — a theme drops its own copy in and gets
 * the markup it wants — and that only stays true if a template has no decisions
 * left in it. Everything that could be decided has been decided by the time one
 * of them runs: what the columns are called, what each cell says, whether a
 * button appears, what to print when there is nothing. A template that only
 * arranges things cannot break the rules by being rewritten.
 */
final class Listing {

	/**
	 * The set this listing was built from.
	 *
	 * @var DisplaySet
	 */
	public DisplaySet $set;

	/**
	 * Rows, already read.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $rows;

	/**
	 * Column key to heading.
	 *
	 * @var array<string, string>
	 */
	public array $columns;

	/**
	 * Weekday and time each course meets, keyed by course id.
	 *
	 * @var array<int, array<int, array{day: int, time: string, from?: string, to?: string}>>
	 */
	public array $times;

	/**
	 * Where in the listing the visitor currently is.
	 *
	 * @var ListingArgs
	 */
	public ListingArgs $args;

	/**
	 * How many rows there are in total, before the page size.
	 *
	 * @var int
	 */
	public int $total = 0;

	/**
	 * Rooms this listing could be narrowed to, id to name.
	 *
	 * @var array<int, string>
	 */
	public array $rooms = array();

	/**
	 * The page this listing sits on.
	 *
	 * @var string
	 */
	public string $base_url = '';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param DisplaySet                       $set      Display set.
	 * @param array<int, array<string, mixed>> $rows     Rows.
	 * @param array<string, string>            $columns  Column key to heading.
	 * @param Settings                         $settings Settings.
	 * @param array<int, array<int, array{day: int, time: string, from?: string, to?: string}>> $times Course meeting times.
	 */
	public function __construct( DisplaySet $set, array $rows, array $columns, Settings $settings, array $times = array() ) {
		$this->set      = $set;
		$this->rows     = $rows;
		$this->columns  = $columns;
		$this->settings = $settings;
		$this->times    = $times;
		$this->args     = ListingArgs::from_array( array() );

		$this->columns = self::used_columns(
			$columns,
			$rows,
			fn( array $row, string $column ): string => $this->cell( $row, $column )['html']
		);
	}

	/**
	 * Drops the columns that would be empty from top to bottom.
	 *
	 * A column can be asked for and still have nothing to say: the booking
	 * button when the site has them switched off, the cancelled marker in a
	 * week when nothing was cancelled. Rendered anyway it is a heading over
	 * nothing, which reads as something broken rather than as an answer of
	 * "none". The set is not changed — tick the column back into use and it
	 * returns the moment there is anything to put in it.
	 *
	 * A listing with no rows keeps every column: there is nothing to conclude
	 * from an empty table, and its headings are the only thing describing what
	 * it would have shown.
	 *
	 * @param array<string, string>            $columns Column key to heading.
	 * @param array<int, array<string, mixed>> $rows    Rows.
	 * @param callable                         $value   Reads one cell: (row, column) => string.
	 * @return array<string, string>
	 */
	public static function used_columns( array $columns, array $rows, callable $value ): array {
		if ( array() === $rows ) {
			return $columns;
		}

		$used = array();

		foreach ( $columns as $column => $label ) {
			foreach ( $rows as $row ) {
				if ( '' !== trim( (string) $value( $row, (string) $column ) ) ) {
					$used[ $column ] = $label;

					break;
				}
			}
		}

		// Everything empty is not an answer either: with no headings at all a
		// table of rows is a grid of unlabelled values. Better to show what was
		// asked for and let it be visibly empty.
		return array() === $used ? $columns : $used;
	}

	/**
	 * Records where in the listing the visitor is.
	 *
	 * @param ListingArgs        $args     Chosen page, week and room.
	 * @param int                $total    Rows in total, before the page size.
	 * @param array<int, string> $rooms    Rooms the listing may be narrowed to.
	 * @param string             $base_url The page the listing sits on.
	 * @return void
	 */
	public function place( ListingArgs $args, int $total, array $rooms, string $base_url ): void {
		$this->args     = $args;
		$this->total    = $total;
		$this->rooms    = $rooms;
		$this->base_url = $base_url;
	}

	/**
	 * Returns how many pages the listing has.
	 *
	 * @return int
	 */
	public function pages(): int {
		if ( 0 === $this->set->per_page ) {
			return 1;
		}

		return max( 1, (int) ceil( $this->total / $this->set->per_page ) );
	}

	/**
	 * Whether a week can be stepped through.
	 *
	 * Only a timetable set to show a week has weeks to step through. On a
	 * listing of courses, or one covering a whole term, the control would be a
	 * button that changes nothing.
	 *
	 * @return bool
	 */
	public function has_weeks(): bool {
		return DisplaySet::TYPE_SCHEDULE === $this->set->type && 'week' === $this->set->range;
	}

	/**
	 * Builds the address of this listing with one argument changed.
	 *
	 * @param string $key   One of `page`, `week` or `room`.
	 * @param int    $value New value.
	 * @return string
	 */
	public function url( string $key, int $value ): string {
		$base = '' === $this->base_url ? '' : $this->base_url;
		$args = $this->args->with( $key, $value )->to_query();

		return add_query_arg( $args, $base ) . ( '' === $this->set->id ? '' : '#cscs-' . $this->set->id );
	}

	/**
	 * Returns the address the room filter submits to.
	 *
	 * A form submitted by a browser replaces the whole query string with its
	 * own fields, so the address it points at must carry none: whatever the
	 * page already had is put back as hidden fields instead.
	 *
	 * @return string
	 */
	public function form_action(): string {
		$base = explode( '#', $this->base_url )[0];

		return explode( '?', $base )[0];
	}

	/**
	 * Returns what the room filter must carry across as hidden fields.
	 *
	 * Everything the page was already asked for, minus the room being chosen
	 * and minus the page, because choosing a room starts again at the first
	 * one. Query parameters that are not this plugin's are kept: a listing has
	 * no business dropping whatever else a site puts in its addresses.
	 *
	 * @return array<string, string>
	 */
	public function hidden_fields(): array {
		$query  = (string) wp_parse_url( $this->base_url, PHP_URL_QUERY );
		$parsed = array();

		if ( '' !== $query ) {
			wp_parse_str( $query, $parsed );
		}

		return $this->args->carried( $parsed );
	}

	/**
	 * Returns what the listing shows, as a sentence to be read out.
	 *
	 * For the live region: after a control replaces the table, somebody looking
	 * at the screen sees what changed and somebody listening is told nothing,
	 * because focus has not moved and nothing was navigated. This is what gets
	 * said — the week where there is one, the page where there is more than
	 * one, and how many rows arrived, which is the answer to "did that do
	 * anything".
	 *
	 * @return string
	 */
	public function spoken(): string {
		$said = array();

		if ( $this->has_weeks() ) {
			/* translators: %s: a range of dates, "7. 9. – 13. 9. 2026". */
			$said[] = sprintf( __( 'Week of %s', 'course-schedule-connector' ), $this->week_label() );
		}

		if ( 1 < $this->pages() ) {
			$said[] = sprintf(
				/* translators: 1: the page being shown, 2: how many pages there are. */
				__( 'Page %1$d of %2$d', 'course-schedule-connector' ),
				$this->args->page,
				$this->pages()
			);
		}

		$rows = count( $this->rows );

		$said[] = sprintf(
			/* translators: %d: how many rows the table has. */
			_n( '%d row', '%d rows', $rows, 'course-schedule-connector' ),
			$rows
		);

		return implode( ', ', $said );
	}

	/**
	 * Returns the week being shown, as a person would say it.
	 *
	 * @return string
	 */
	public function week_label(): string {
		$stamp = time() + ( $this->args->week * WEEK_IN_SECONDS );
		$start = (int) strtotime( 'monday this week', $stamp );
		$end   = (int) strtotime( 'sunday this week', $stamp );

		return Formatter::date_range(
			(string) wp_date( 'j. n.', $start ),
			(string) wp_date( 'j. n. Y', $end )
		);
	}

	/**
	 * Whether there is anything to show.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return array() === $this->rows;
	}

	/**
	 * Returns the heading, or an empty string when the set has none.
	 *
	 * @return string
	 */
	public function heading(): string {
		return $this->set->heading;
	}

	/**
	 * Returns what to say when there is nothing to show.
	 *
	 * @return string
	 */
	public function empty_text(): string {
		return '' !== $this->set->empty_text
			? $this->set->empty_text
			: __( 'Nothing is listed here at the moment.', 'course-schedule-connector' );
	}

	/**
	 * Returns the text on the booking button.
	 *
	 * @return string
	 */
	public function cta(): string {
		return '' !== $this->set->cta
			? $this->set->cta
			: __( 'Sign up in iSport', 'course-schedule-connector' );
	}

	/**
	 * Returns what to say instead of the button when a course is full.
	 *
	 * @return string
	 */
	public function full_text(): string {
		return '' !== $this->set->full_text
			? $this->set->full_text
			: __( 'Full', 'course-schedule-connector' );
	}

	/**
	 * Returns the classes one row carries.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return string
	 */
	public function row_class( array $row ): string {
		$classes = array( 'cscs-row' );

		if ( ! empty( $row['cancelled'] ) ) {
			$classes[] = 'cscs-row--cancelled';
		}

		if ( isset( $row['available'] ) ) {
			$classes[] = 'cscs-row--' . Formatter::availability( (int) $row['available'], $this->set->few_places );
		}

		return implode( ' ', $classes );
	}

	/**
	 * Returns one cell, as text and as the markup that may replace it.
	 *
	 * @param array<string, mixed> $row    Row.
	 * @param string               $column Column key.
	 * @return array{text: string, html: string}
	 */
	public function cell( array $row, string $column ): array {
		$text = $this->value( $row, $column );
		$html = esc_html( $text );

		if ( 'name' === $column || 'course' === $column ) {
			$link = (string) ( $row['permalink'] ?? '' );

			if ( '' !== $link && '' !== $text ) {
				$html = sprintf( '<a href="%s">%s</a>', esc_url( $link ), esc_html( $text ) );
			}
		}

		if ( 'button' === $column ) {
			$html = $this->button( $row );
			$text = wp_strip_all_tags( $html );
		}

		// A course that meets twice a week has two days and two times, and they
		// are read in step. Joined into a sentence they stop being readable,
		// which is the whole reason these are two columns rather than one.
		if ( 'day' === $column || 'hours' === $column ) {
			$html = nl2br( esc_html( $text ) );
		}

		if ( 'state' === $column && ! empty( $row['cancelled'] ) ) {
			$html = '<span class="cscs-cancelled">' . esc_html( $text ) . '</span>';
		}

		return array(
			'text' => $text,
			'html' => $html,
		);
	}

	/**
	 * Returns the plain value of one cell.
	 *
	 * @param array<string, mixed> $row    Row.
	 * @param string               $column Column key.
	 * @return string
	 */
	private function value( array $row, string $column ) {
		switch ( $column ) {
			case 'name':
				return (string) ( $row['name'] ?? '' );

			case 'course':
				return '' !== (string) ( $row['course'] ?? '' ) ? (string) $row['course'] : (string) ( $row['name'] ?? '' );

			case 'activity':
				return (string) ( $row['activity'] ?? $row['name'] ?? '' );

			case 'days':
				return $this->days( (int) ( $row['course_id'] ?? 0 ) );

			case 'day':
				return $this->weekdays( (int) ( $row['course_id'] ?? 0 ) );

			case 'hours':
				return $this->hours( (int) ( $row['course_id'] ?? 0 ) );

			case 'date':
				return '' === (string) ( $row['date'] ?? '' )
					? ''
					: (string) mysql2date( (string) get_option( 'date_format' ), (string) $row['date'] );

			case 'time':
				return Formatter::time_range( (string) ( $row['time_from'] ?? '' ), (string) ( $row['time_to'] ?? '' ) );

			case 'duration':
				return $this->duration( (int) ( $row['course_id'] ?? 0 ) );

			case 'age':
				return Formatter::age_range(
					(string) ( $row['age_from'] ?? '' ),
					(string) ( $row['age_to'] ?? '' )
				);

			case 'gender':
				return Audience::gender_label( (string) ( $row['gender'] ?? '' ) );

			case 'level':
				return Audience::level_label(
					(string) ( $row['level'] ?? '' ),
					(string) ( $row['gender'] ?? '' )
				);

			case 'room':
				return $this->room( $row );

			case 'trainer':
				return (string) ( $row['trainer'] ?? '' );

			case 'period':
				return Formatter::date_range(
					$this->short_date( (string) ( $row['date_from'] ?? '' ) ),
					$this->short_date( (string) ( $row['date_to'] ?? '' ) )
				);

			case 'lessons':
				return 0 === (int) ( $row['lessons'] ?? 0 ) ? '' : (string) (int) $row['lessons'];

			case 'price':
				return Formatter::price( $row['price'] ?? null, $this->empty_price( $row ) );

			case 'places':
				return $this->places( $row );

			case 'state':
				return empty( $row['cancelled'] ) ? '' : __( 'Cancelled', 'course-schedule-connector' );

			case 'button':
				return '';
		}

		return '';
	}

	/**
	 * Returns the weekday and time a course meets.
	 *
	 * @param int $course_id Course id.
	 * @return string
	 */
	private function days( int $course_id ): string {
		global $wp_locale;

		$slots = $this->times[ $course_id ] ?? array();
		$parts = array();

		foreach ( $slots as $slot ) {
			// WEEKDAY() counts from Monday; WordPress's own list starts on
			// Sunday, which is a difference of one place and a whole day if it
			// goes unnoticed.
			$day = ( $slot['day'] + 1 ) % 7;
			$name = $wp_locale instanceof \WP_Locale ? $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $day ) ) : '';

			$parts[] = trim( $name . ' ' . $slot['time'] );
		}

		return implode( ', ', $parts );
	}

	/**
	 * Returns the weekdays a course meets on, one to a line.
	 *
	 * The abbreviated name, because this is a table column and "Pondělí" in a
	 * column beside four others is a column that pushes everything else off a
	 * telephone.
	 *
	 * @param int $course_id Course id.
	 * @return string One weekday per line.
	 */
	private function weekdays( int $course_id ): string {
		global $wp_locale;

		$days = array();

		foreach ( $this->times[ $course_id ] ?? array() as $slot ) {
			// WEEKDAY() counts from Monday; WordPress's own list starts on
			// Sunday, which is a difference of one place and a whole day if it
			// goes unnoticed.
			$index = ( (int) $slot['day'] + 1 ) % 7;

			$days[] = $wp_locale instanceof \WP_Locale ? $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $index ) ) : '';
		}

		return implode( "\n", $days );
	}

	/**
	 * Returns the hours a course meets at, one to a line.
	 *
	 * The lines match {@see self::weekdays()} one for one: the first day
	 * belongs to the first time, and a reader never has to guess which.
	 *
	 * @param int $course_id Course id.
	 * @return string One time range per line.
	 */
	private function hours( int $course_id ): string {
		$hours = array();

		foreach ( $this->times[ $course_id ] ?? array() as $slot ) {
			$hours[] = Formatter::time_range(
				(string) ( $slot['from'] ?? $slot['time'] ?? '' ),
				(string) ( $slot['to'] ?? '' )
			);
		}

		return implode( "\n", $hours );
	}

	/**
	 * Returns how long one class of this course lasts.
	 *
	 * The first slot answers for the course: a course that meets twice a week
	 * meets for the same length both times, and where it does not, the first
	 * one is the one at the top of the timetable beside it.
	 *
	 * @param int $course_id Course id.
	 * @return string
	 */
	private function duration( int $course_id ): string {
		foreach ( $this->times[ $course_id ] ?? array() as $slot ) {
			$minutes = Formatter::minutes_between(
				(string) ( $slot['from'] ?? '' ),
				(string) ( $slot['to'] ?? '' )
			);

			if ( 0 !== $minutes ) {
				return Formatter::duration( $minutes );
			}
		}

		return '';
	}

	/**
	 * Returns the room, under the name the site chose for it.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return string
	 */
	private function room( array $row ): string {
		if ( isset( $row['rooms'] ) && is_array( $row['rooms'] ) ) {
			return implode( ', ', array_map( 'strval', $row['rooms'] ) );
		}

		return (string) ( $row['room'] ?? '' );
	}

	/**
	 * Returns what to print where a price is missing.
	 *
	 * A course with no price is free — that is what it means for a course. A
	 * class with no price is usually a hall rental or a public session, where
	 * "free" would be an invitation the gym never made, so the site decides.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return string
	 */
	private function empty_price( array $row ): string {
		if ( DisplaySet::TYPE_COURSES === $this->set->type || isset( $row['post_id'] ) && 0 !== (int) ( $row['post_id'] ?? 0 ) ) {
			return __( 'Free', 'course-schedule-connector' );
		}

		return 'free' === (string) $this->settings->get( 'lesson_price_when_empty' )
			? __( 'Free', 'course-schedule-connector' )
			: __( 'On request', 'course-schedule-connector' );
	}

	/**
	 * Returns how many places are left, in words.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return string
	 */
	private function places( array $row ): string {
		$available = (int) ( $row['available'] ?? 0 );
		$state     = Formatter::availability( $available, $this->set->few_places );

		if ( 'full' === $state ) {
			return $this->full_text();
		}

		if ( 'few' === $state ) {
			return sprintf(
				/* translators: %d: number of places */
				_n( 'last %d place', 'last %d places', $available, 'course-schedule-connector' ),
				$available
			);
		}

		return (string) $available;
	}

	/**
	 * Returns the booking button, or what stands in for it.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return string
	 */
	private function button( array $row ): string {
		$url = (string) ( $row['url'] ?? '' );

		$shows = Formatter::shows_button(
			(string) ( $row['button'] ?? 'default' ),
			(bool) $this->settings->get( 'show_isport_button' ),
			(bool) ( $row['booking'] ?? false ),
			(int) ( $row['available'] ?? 0 )
		);

		if ( ! $shows || '' === $url ) {
			return '';
		}

		return sprintf(
			'<a class="cscs-button" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( $url ),
			esc_html( $this->cta() )
		);
	}

	/**
	 * Formats a date the short way, for a range.
	 *
	 * @param string $date Date, `Y-m-d`.
	 * @return string
	 */
	private function short_date( string $date ): string {
		return '' === $date ? '' : (string) mysql2date( 'j. n. Y', $date );
	}
}
