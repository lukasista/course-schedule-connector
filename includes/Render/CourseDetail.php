<?php
/**
 * What a course's own page shows.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

defined( 'ABSPATH' ) || exit;

use CSCS\Data\Audience;
use CSCS\Data\CourseRepository;
use CSCS\Data\DisplaySet;
use CSCS\Data\TrainerRepository;
use CSCS\Plugin;
use CSCS\Support\Markup;

/**
 * A course, worked out, ready for its page.
 *
 * The page a visitor lands on from a listing has to answer the questions the
 * listing could not: when exactly does it meet, what does it cost, who runs it,
 * how do I get in — and, for the courses nobody books through iSport, whom to
 * ask instead. All of that is decided here, so the template only arranges it,
 * and a theme replacing the template cannot change what a course is.
 *
 * The two tables on the page are ordinary listings, built from sets made on the
 * spot. That is deliberate: the timetable of this course and the timetable of
 * every course fold the same way on a telephone, because they are the same
 * code.
 */
final class CourseDetail {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * The course post.
	 *
	 * @var \WP_Post
	 */
	private \WP_Post $post;

	/**
	 * The course, read once.
	 *
	 * @var array<string, mixed>
	 */
	private array $course;

	/**
	 * The slots this course meets in, read once.
	 *
	 * @var array<int, array{day: int, time: string, from?: string, to?: string}>|null
	 */
	private ?array $slots = null;

	/**
	 * Constructor.
	 *
	 * @param Plugin   $plugin Plugin instance.
	 * @param \WP_Post $post   Course post.
	 */
	public function __construct( Plugin $plugin, \WP_Post $post ) {
		$this->plugin = $plugin;
		$this->post   = $post;
		$this->course = ( new Query( $plugin ) )->course_row( $post );
	}

	/**
	 * Returns the course as the renderer sees it.
	 *
	 * @return array<string, mixed>
	 */
	public function course(): array {
		return $this->course;
	}

	/**
	 * Returns the facts worth printing beside the text, already worded.
	 *
	 * A fact with nothing to say is left out rather than printed empty, for the
	 * same reason an empty column is dropped from a listing.
	 *
	 * @return array<string, string>
	 */
	public function facts(): array {
		$course = $this->course;

		unset( $course );

		$facts = array(
			__( 'Price', 'course-schedule-connector' )   => $this->price(),
			__( 'Day', 'course-schedule-connector' )     => $this->meeting_days(),
			__( 'Time', 'course-schedule-connector' )    => $this->meeting_hours(),
			__( 'Runs', 'course-schedule-connector' )    => $this->period(),
			__( 'Classes', 'course-schedule-connector' ) => $this->lessons(),
			__( 'Age', 'course-schedule-connector' )     => $this->age(),
			__( 'Gender', 'course-schedule-connector' )  => $this->gender(),
			__( 'Level', 'course-schedule-connector' )   => $this->level(),
			__( 'Room', 'course-schedule-connector' )    => $this->rooms(),
			__( 'Trainer', 'course-schedule-connector' ) => $this->trainer(),
			__( 'Places left', 'course-schedule-connector' ) => $this->places(),
		);

		return array_filter(
			$facts,
			static function ( string $value ): bool {
				return '' !== trim( $value );
			}
		);
	}

	/**
	 * Returns the description iSport holds for the course, as markup.
	 *
	 * Not the same thing as the words written in WordPress, and deliberately a
	 * second field rather than a fallback inside the first: one is what the gym
	 * wrote for its own website and the other is what the booking system says,
	 * and a page should be able to show either, both or neither.
	 *
	 * The remote system sends HTML — lists, line breaks — usually opening with
	 * a stray `<br>` that is nothing but a gap at the top of a page, so that is
	 * trimmed. What survives goes through the same filter as any post content.
	 *
	 * @return string
	 */
	public function api_description(): string {
		$description = (string) ( $this->course['api_description'] ?? '' );
		$description = (string) preg_replace( '#^(?:\s|<br\s*/?>)+#i', '', $description );

		if ( '' === trim( $description ) ) {
			return '';
		}

		// The booking system's editor wraps a list in a list often enough — 24
		// of this gym's 113 courses — that a page would show two levels of
		// bullets where one was meant. No stylesheet can undo that: the second
		// level is really in the markup.
		return wp_kses_post( Markup::flatten_lists( $description ) );
	}

	/**
	 * Returns the ages the course is for.
	 *
	 * @return string
	 */
	public function age(): string {
		return Formatter::age_range(
			(string) ( $this->course['age_from'] ?? '' ),
			(string) ( $this->course['age_to'] ?? '' )
		);
	}

	/**
	 * Returns who the course is for, in words.
	 *
	 * @return string
	 */
	public function gender(): string {
		return Audience::gender_label( (string) ( $this->course['gender'] ?? '' ) );
	}

	/**
	 * Returns the level the course is at, worded for the group it is for.
	 *
	 * @return string
	 */
	public function level(): string {
		return Audience::level_label(
			(string) ( $this->course['level'] ?? '' ),
			(string) ( $this->course['gender'] ?? '' )
		);
	}

	/**
	 * Returns the same facts with the one link a course page has in them.
	 *
	 * `facts()` stays plain text, because that is what a block, a module or a
	 * feed wants and because a value that might be markup is a value every
	 * consumer has to remember to be careful with. This is the one place that
	 * knows a trainer's name is also a way to reach their page.
	 *
	 * @return array<string, string> Label to HTML.
	 */
	public function facts_html(): array {
		$trainer = __( 'Trainer', 'course-schedule-connector' );
		$url     = $this->trainer_url();
		$html    = array();

		foreach ( $this->facts() as $label => $value ) {
			if ( $label === $trainer && '' !== $url ) {
				$html[ $label ] = sprintf(
					'<a class="cscs-course__trainer-link" href="%s">%s</a>',
					esc_url( $url ),
					esc_html( $value )
				);

				continue;
			}

			$html[ $label ] = nl2br( esc_html( $value ) );
		}

		return $html;
	}

	/**
	 * Returns the address of this course's trainer page, where there is one.
	 *
	 * @return string
	 */
	public function trainer_url(): string {
		$trainer_id = ( new TrainerRepository() )->for_course( (int) ( $this->course['post_id'] ?? 0 ) );

		if ( 0 === $trainer_id ) {
			return '';
		}

		return (string) get_permalink( $trainer_id );
	}

	/**
	 * Returns the lecturer's contact details, where somebody entered any.
	 *
	 * @return array{name: string, email: string, phone: string, note: string}|null
	 */
	public function contact(): ?array {
		$contact = (array) ( $this->course['contact'] ?? array() );

		$contact = array(
			'name'  => (string) ( $contact['name'] ?? '' ),
			'email' => (string) ( $contact['email'] ?? '' ),
			'phone' => (string) ( $contact['phone'] ?? '' ),
			'note'  => (string) ( $contact['note'] ?? '' ),
		);

		foreach ( $contact as $value ) {
			if ( '' !== $value ) {
				return $contact;
			}
		}

		return null;
	}

	/**
	 * Returns the booking button, or an empty string when there is none to show.
	 *
	 * @return string
	 */
	public function button(): string {
		$settings = $this->plugin->settings();

		$shows = Formatter::shows_button(
			(string) ( $this->course['button'] ?? 'default' ),
			(bool) $settings->get( 'show_isport_button' ),
			(bool) ( $this->course['booking'] ?? false ),
			(int) ( $this->course['available'] ?? 0 )
		);

		$url = (string) ( $this->course['url'] ?? '' );

		if ( ! $shows || '' === $url ) {
			return '';
		}

		return sprintf(
			'<a class="cscs-button" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( $url ),
			esc_html__( 'Sign up in iSport', 'course-schedule-connector' )
		);
	}

	/**
	 * Returns the course's own timetable, as a listing.
	 *
	 * @return Listing|null
	 */
	public function schedule(): ?Listing {
		$rows = ( new Query( $this->plugin ) )->course_schedule( (int) ( $this->course['course_id'] ?? 0 ) );

		return $this->listing( $rows, array( 'date', 'time', 'room', 'trainer', 'state' ) );
	}

	/**
	 * Returns the make-up lessons tied to this course, as a listing.
	 *
	 * These belong on the course's page and nowhere else: a make-up lesson is a
	 * replacement for a class in this course, and a visitor looking at any other
	 * page has no use for it.
	 *
	 * @return Listing|null
	 */
	public function makeup(): ?Listing {
		$rows = ( new Query( $this->plugin ) )->course_makeup( (int) ( $this->course['course_id'] ?? 0 ) );

		return $this->listing( $rows, array( 'date', 'time', 'room', 'trainer' ) );
	}

	/**
	 * Builds a listing from rows, or nothing when there are none.
	 *
	 * @param array<int, array<string, mixed>> $rows    Rows.
	 * @param array<int, string>               $columns Columns to show.
	 * @return Listing|null
	 */
	private function listing( array $rows, array $columns ): ?Listing {
		if ( array() === $rows ) {
			return null;
		}

		$set = DisplaySet::from_array(
			array(
				'type'      => DisplaySet::TYPE_SCHEDULE,
				'columns'   => $columns,
				'cancelled' => 'show',
			)
		);

		return new Listing( $set, $rows, Renderer::labels_for( $set ), $this->plugin->settings() );
	}

	/**
	 * Returns the machine-readable description of this course.
	 *
	 * Search engines are the second visitor every course page has, and a course
	 * with a price, a date and a place is exactly what they have a vocabulary
	 * for. Nothing is claimed here that the page does not also say in words.
	 *
	 * @return string JSON-LD, or an empty string when there is too little to say.
	 */
	public function structured_data(): string {
		$course = $this->course;
		$name   = (string) ( $course['name'] ?? '' );

		if ( '' === $name ) {
			return '';
		}

		$data = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Course',
			'name'        => $name,
			'url'         => (string) ( $course['permalink'] ?? '' ),
			'description' => wp_strip_all_tags( (string) ( $course['excerpt'] ?? '' ) ),
			'provider'    => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			),
		);

		$instance = array_filter(
			array(
				'@type'        => 'CourseInstance',
				'courseMode'   => 'onsite',
				'startDate'    => (string) ( $course['date_from'] ?? '' ),
				'endDate'      => (string) ( $course['date_to'] ?? '' ),
				'location'     => $this->location(),
				'instructor'   => '' === (string) ( $course['trainer'] ?? '' ) ? null : array(
					'@type' => 'Person',
					'name'  => (string) $course['trainer'],
				),
			)
		);

		if ( 1 < count( $instance ) ) {
			$data['hasCourseInstance'] = array( $instance );
		}

		$price = $course['price'] ?? null;

		if ( null !== $price && is_numeric( $price ) && 0.0 < (float) $price ) {
			$data['offers'] = array(
				'@type'         => 'Offer',
				'price'         => (string) ( 0.0 === round( (float) $price - (int) (float) $price, 2 ) ? (string) (int) (float) $price : $price ),
				'priceCurrency' => 'CZK',
				'url'           => (string) ( $course['url'] ?? $course['permalink'] ?? '' ),
			);
		}

		return (string) wp_json_encode( array_filter( $data ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Returns the place a course runs in, for the structured data.
	 *
	 * @return array<string, mixed>|null
	 */
	private function location(): ?array {
		$rooms = array_map( 'strval', (array) ( $this->course['rooms'] ?? array() ) );

		if ( array() === $rooms ) {
			return null;
		}

		return array(
			'@type'   => 'Place',
			'name'    => implode( ', ', $rooms ),
			'address' => array(
				'@type'           => 'PostalAddress',
				'addressLocality' => get_bloginfo( 'name' ),
			),
		);
	}

	/**
	 * Returns the days the course meets on, one to a line.
	 *
	 * Day and time are two facts, not one: "Monday, Wednesday 16:00, 17:00" is
	 * a sentence nobody can read a timetable out of. They are printed as two
	 * fields whose lines run in step, so that the first day belongs to the
	 * first time and the reader never has to guess which goes with which.
	 *
	 * @return string One weekday per line.
	 */
	public function meeting_days(): string {
		global $wp_locale;

		$days = array();

		foreach ( $this->slots() as $slot ) {
			// WEEKDAY() counts from Monday; WordPress's own list starts on
			// Sunday, which is a difference of one place and a whole day if it
			// goes unnoticed.
			$index = ( (int) $slot['day'] + 1 ) % 7;

			$days[] = $wp_locale instanceof \WP_Locale ? $wp_locale->get_weekday( $index ) : '';
		}

		return implode( "\n", $days );
	}

	/**
	 * Returns the hours the course meets at, one to a line.
	 *
	 * The lines match {@see self::meeting_days()} one for one.
	 *
	 * @return string One time range per line.
	 */
	public function meeting_hours(): string {
		$hours = array();

		foreach ( $this->slots() as $slot ) {
			$hours[] = Formatter::time_range(
				(string) ( $slot['from'] ?? $slot['time'] ?? '' ),
				(string) ( $slot['to'] ?? '' )
			);
		}

		return implode( "\n", $hours );
	}

	/**
	 * Returns the slots this course meets in, read once.
	 *
	 * @return array<int, array{day: int, time: string, from?: string, to?: string}>
	 */
	private function slots(): array {
		if ( null !== $this->slots ) {
			return $this->slots;
		}

		$id    = (int) ( $this->course['course_id'] ?? 0 );
		$times = ( new Query( $this->plugin ) )->course_times( array( $id ) );

		$this->slots = array_values(
			array_filter(
				$times[ $id ] ?? array(),
				static function ( array $slot ): bool {
					return '' !== (string) ( $slot['from'] ?? $slot['time'] ?? '' );
				}
			)
		);

		return $this->slots;
	}

	/**
	 * Returns the price, in words.
	 *
	 * @return string
	 */
	public function price(): string {
		return Formatter::price(
			$this->course['price'] ?? null,
			__( 'Free', 'course-schedule-connector' )
		);
	}

	/**
	 * Returns the span of dates the course runs over.
	 *
	 * @return string
	 */
	public function period(): string {
		return Formatter::date_range(
			$this->short_date( (string) ( $this->course['date_from'] ?? '' ) ),
			$this->short_date( (string) ( $this->course['date_to'] ?? '' ) )
		);
	}

	/**
	 * Returns how many classes the course has, or nothing when it says none.
	 *
	 * @return string
	 */
	public function lessons(): string {
		$lessons = (int) ( $this->course['lessons'] ?? 0 );

		return 0 === $lessons ? '' : (string) $lessons;
	}

	/**
	 * Returns the rooms the course runs in.
	 *
	 * @return string
	 */
	public function rooms(): string {
		return implode( ', ', array_map( 'strval', (array) ( $this->course['rooms'] ?? array() ) ) );
	}

	/**
	 * Returns the trainer's name as iSport spells it.
	 *
	 * @return string
	 */
	public function trainer(): string {
		return (string) ( $this->course['trainer'] ?? '' );
	}

	/**
	 * Returns the course's name.
	 *
	 * @return string
	 */
	public function name(): string {
		return (string) ( $this->course['name'] ?? '' );
	}

	/**
	 * Returns how many places are left, in words.
	 *
	 * @return string
	 */
	public function places(): string {
		$available = (int) ( $this->course['available'] ?? 0 );
		$capacity  = (int) ( $this->course['capacity'] ?? 0 );

		if ( 0 === $capacity ) {
			return '';
		}

		return $available <= 0
			? __( 'Full', 'course-schedule-connector' )
			: sprintf( '%d / %d', $available, $capacity );
	}

	/**
	 * Formats a date the short way.
	 *
	 * @param string $date Date, `Y-m-d`.
	 * @return string
	 */
	private function short_date( string $date ): string {
		// The site's own date format, as the listings use. A course page that
		// wrote "1. 9. 2026" under a site set to write "September 1, 2026" is
		// one page disagreeing with every other page around it.
		return '' === $date ? '' : (string) mysql2date( (string) get_option( 'date_format' ), $date );
	}

	/**
	 * Whether this course was made here rather than in iSport.
	 *
	 * @return bool
	 */
	public function is_manual(): bool {
		return CourseRepository::is_manual( (int) ( $this->course['course_id'] ?? 0 ) );
	}
}
