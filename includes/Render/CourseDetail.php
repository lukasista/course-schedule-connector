<?php
/**
 * What a course's own page shows.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

use CSCS\Data\CourseRepository;
use CSCS\Data\DisplaySet;
use CSCS\Plugin;

defined( 'ABSPATH' ) || exit;

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

		$facts = array(
			__( 'Price', 'course-schedule-connector' )   => Formatter::price(
				$course['price'] ?? null,
				__( 'Free', 'course-schedule-connector' )
			),
			__( 'When', 'course-schedule-connector' )    => $this->meeting_times(),
			__( 'Runs', 'course-schedule-connector' )    => Formatter::date_range(
				$this->short_date( (string) ( $course['date_from'] ?? '' ) ),
				$this->short_date( (string) ( $course['date_to'] ?? '' ) )
			),
			__( 'Classes', 'course-schedule-connector' ) => 0 === (int) ( $course['lessons'] ?? 0 ) ? '' : (string) (int) $course['lessons'],
			__( 'Room', 'course-schedule-connector' )    => implode( ', ', array_map( 'strval', (array) ( $course['rooms'] ?? array() ) ) ),
			__( 'Trainer', 'course-schedule-connector' ) => (string) ( $course['trainer'] ?? '' ),
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
	 * Returns when the course meets, in words.
	 *
	 * @return string
	 */
	private function meeting_times(): string {
		$query = new Query( $this->plugin );
		$times = $query->course_times( array( (int) ( $this->course['course_id'] ?? 0 ) ) );
		$set   = DisplaySet::from_array( array( 'type' => DisplaySet::TYPE_COURSES ) );

		$listing = new Listing( $set, array( $this->course ), array( 'days' => '' ), $this->plugin->settings(), $times );

		return $listing->cell( $this->course, 'days' )['text'];
	}

	/**
	 * Returns how many places are left, in words.
	 *
	 * @return string
	 */
	private function places(): string {
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
		return '' === $date ? '' : (string) mysql2date( 'j. n. Y', $date );
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
