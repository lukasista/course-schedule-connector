<?php
/**
 * The catalogue of fields a page can be built out of.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

defined( 'ABSPATH' ) || exit;

use CSCS\Data\KindType;
use CSCS\Data\PostType;
use CSCS\Data\TrainerType;
use CSCS\Plugin;

/**
 * One list of what a course and a trainer are made of.
 *
 * Every field on a course page is the same shape — a heading and a value — and
 * the temptation is to ship one block called "field" with a dropdown on it. The
 * trouble with that is the first thing anybody does: they open the inserter,
 * type "price", and find nothing. A person building a page thinks in the thing
 * they want, not in the abstraction it happens to be an instance of.
 *
 * So the blocks are named — Price, Day, Time, Trainer — and this is the single
 * place that knows what each of them means. The Gutenberg blocks, the Divi
 * modules and the plugin's own templates are all generated from it, which is
 * why a field added here appears in three editors at once and cannot say three
 * different things.
 */
final class Fields {

	/**
	 * Fields that describe a course.
	 */
	public const COURSE = 'course';

	/**
	 * Fields that describe a trainer.
	 */
	public const TRAINER = 'trainer';

	/**
	 * Fields of a kind of course.
	 */
	public const KIND = 'kind';

	/**
	 * A single line, or several lines that belong together.
	 */
	public const TEXT = 'text';

	/**
	 * An ordered list of lines.
	 */
	public const LIST = 'list';

	/**
	 * Markup the plugin itself produced: a button, a table, an image.
	 */
	public const HTML = 'html';

	/**
	 * Returns every field, keyed by the name its block and module carry.
	 *
	 * @return array<string, array{context: string, kind: string, title: string, label: ?string, icon: string, moduleIcon: string, columns: array<int, string>, description: string, heading: bool}>
	 */
	public static function all(): array {
		$course = array(
			'name'    => array(
				'kind'        => self::TEXT,
				'title'       => __( 'Course name', 'course-schedule-connector' ),
				'label'       => null,
				'icon'        => 'editor-textcolor',
				'moduleIcon'  => 'divi/module-post-title',
				'description' => __( 'The name of the course, as a heading you can style.', 'course-schedule-connector' ),
				'heading'     => false,
			),
			'price'   => array(
				'kind'        => self::TEXT,
				'title'       => __( 'Price', 'course-schedule-connector' ),
				'label'       => __( 'Price', 'course-schedule-connector' ),
				'icon'        => 'tag',
				'moduleIcon'  => 'divi/module-pricing-table',
				'description' => __( 'What the course costs. A course with no price shows as free.', 'course-schedule-connector' ),
				'heading'     => true,
			),
			'day'     => array(
				'kind'        => self::TEXT,
				'title'       => __( 'Day', 'course-schedule-connector' ),
				'label'       => __( 'Day', 'course-schedule-connector' ),
				'icon'        => 'calendar',
				'moduleIcon'  => 'divi/module-timeline',
				'description' => __( 'The weekday or weekdays the course meets on, one to a line.', 'course-schedule-connector' ),
				'heading'     => true,
			),
			'time'    => array(
				'kind'        => self::TEXT,
				'title'       => __( 'Time', 'course-schedule-connector' ),
				'label'       => __( 'Time', 'course-schedule-connector' ),
				'icon'        => 'clock',
				'moduleIcon'  => 'divi/module-countdown-timer',
				'description' => __( 'The hours the course meets at, in step with the days.', 'course-schedule-connector' ),
				'heading'     => true,
			),
			'period'  => array(
				'kind'        => self::TEXT,
				'title'       => __( 'Runs', 'course-schedule-connector' ),
				'label'       => __( 'Runs', 'course-schedule-connector' ),
				'icon'        => 'calendar-alt',
				'moduleIcon'  => 'divi/module-timeline-item',
				'description' => __( 'The first and last day of the course.', 'course-schedule-connector' ),
				'heading'     => true,
			),
			'lessons' => array(
				'kind'        => self::TEXT,
				'title'       => __( 'Classes', 'course-schedule-connector' ),
				'label'       => __( 'Classes', 'course-schedule-connector' ),
				'icon'        => 'list-view',
				'moduleIcon'  => 'divi/module-number-counter',
				'description' => __( 'How many classes the course has.', 'course-schedule-connector' ),
				'heading'     => true,
			),
			'age'     => array(
				'kind'        => self::TEXT,
				'title'       => __( 'Age', 'course-schedule-connector' ),
				'label'       => __( 'Age', 'course-schedule-connector' ),
				'icon'        => 'admin-users',
				'moduleIcon'  => 'divi/module-slider',
				'description' => __( 'The ages the course is for. Read from the course name, and can be set by hand on the course.', 'course-schedule-connector' ),
				'heading'     => true,
			),
			'gender'  => array(
				'kind'        => self::TEXT,
				'title'       => __( 'Gender', 'course-schedule-connector' ),
				'label'       => __( 'Gender', 'course-schedule-connector' ),
				'icon'        => 'groups',
				'moduleIcon'  => 'divi/module-toggle',
				'description' => __( 'Who the course is for — girls, boys, mixed, women, men. Read from the course name, and can be set by hand on the course.', 'course-schedule-connector' ),
				'heading'     => true,
			),
			'level'   => array(
				'kind'        => self::TEXT,
				'title'       => __( 'Level', 'course-schedule-connector' ),
				'label'       => __( 'Level', 'course-schedule-connector' ),
				'icon'        => 'chart-bar',
				'moduleIcon'  => 'divi/module-bar-counters',
				'description' => __( 'Beginners, improvers, advanced, competitive training. Worded for the group the course is for, so a course for girls reads differently from a mixed one.', 'course-schedule-connector' ),
				'heading'     => true,
			),
			'room'    => array(
				'kind'        => self::TEXT,
				'title'       => __( 'Room', 'course-schedule-connector' ),
				'label'       => __( 'Room', 'course-schedule-connector' ),
				'icon'        => 'location',
				'moduleIcon'  => 'divi/module-map-pin',
				'description' => __( 'The hall or halls the course runs in.', 'course-schedule-connector' ),
				'heading'     => true,
			),
			'trainer' => array(
				'kind'        => self::HTML,
				'title'       => __( 'Trainer', 'course-schedule-connector' ),
				'label'       => __( 'Trainer', 'course-schedule-connector' ),
				'icon'        => 'groups',
				'moduleIcon'  => 'divi/module-link',
				'description' => __( 'Who runs the course, linked to their own page where they have one.', 'course-schedule-connector' ),
				'heading'     => true,
			),
			'places'  => array(
				'kind'        => self::TEXT,
				'title'       => __( 'Places left', 'course-schedule-connector' ),
				'label'       => __( 'Places left', 'course-schedule-connector' ),
				'icon'        => 'admin-users',
				'moduleIcon'  => 'divi/module-circle-counter',
				'description' => __( 'How many places are still free.', 'course-schedule-connector' ),
				'heading'     => true,
			),
			'button'  => array(
				'kind'        => self::HTML,
				'title'       => __( 'Sign-up button', 'course-schedule-connector' ),
				'label'       => null,
				'icon'        => 'external',
				'moduleIcon'  => 'divi/module-button',
				'description' => __( 'The way into iSport as a button, in your own words. It hides itself when the course is full or takes no bookings.', 'course-schedule-connector' ),
				'heading'     => false,
				'signup'      => 'button',
			),
			'link'    => array(
				'kind'        => self::HTML,
				'title'       => __( 'Sign-up link', 'course-schedule-connector' ),
				'label'       => null,
				'icon'        => 'admin-links',
				'moduleIcon'  => 'divi/module-link',
				'description' => __( 'The same way into iSport, as a link in the text rather than a button.', 'course-schedule-connector' ),
				'heading'     => false,
				'signup'      => 'link',
			),
			'image'   => array(
				'kind'        => self::HTML,
				'title'       => __( 'Course picture', 'course-schedule-connector' ),
				'label'       => null,
				'icon'        => 'format-image',
				'moduleIcon'  => 'divi/module-image',
				'description' => __( 'The featured image of the course.', 'course-schedule-connector' ),
				'heading'     => false,
				'image'       => true,
			),
			'text'    => array(
				'kind'        => self::HTML,
				'title'       => __( 'Course description', 'course-schedule-connector' ),
				'label'       => '',
				'icon'        => 'editor-paragraph',
				'moduleIcon'  => 'divi/module-post-content',
				'description' => __( 'The words written on the course in WordPress.', 'course-schedule-connector' ),
				'heading'     => false,
				'bullets'     => true,
			),
			'api'     => array(
				'kind'        => self::HTML,
				'title'       => __( 'Description from iSport', 'course-schedule-connector' ),
				'label'       => '',
				'icon'        => 'media-document',
				'moduleIcon'  => 'divi/module-sidebar',
				'description' => __( 'The description the booking system holds for the course, which is a different text from the one written in WordPress.', 'course-schedule-connector' ),
				'heading'     => false,
				'bullets'     => true,
			),
			'contact' => array(
				'kind'        => self::HTML,
				'title'       => __( 'Who to ask', 'course-schedule-connector' ),
				'label'       => __( 'Who to ask', 'course-schedule-connector' ),
				'icon'        => 'email',
				'moduleIcon'  => 'divi/module-contact-form',
				'description' => __( 'The person to contact about a course nobody books through iSport.', 'course-schedule-connector' ),
				'heading'     => true,
			),
			'schedule' => array(
				'kind'        => self::HTML,
				'title'       => __( 'Upcoming classes', 'course-schedule-connector' ),
				'label'       => __( 'Upcoming classes', 'course-schedule-connector' ),
				'icon'        => 'calendar-alt',
				'moduleIcon'  => 'divi/module-table-of-contents',
				'columns'     => array( 'date', 'time', 'room', 'trainer', 'state' ),
				'description' => __( 'This course’s own timetable, as a table.', 'course-schedule-connector' ),
				'heading'     => true,
			),
			'makeup'  => array(
				'kind'        => self::HTML,
				'title'       => __( 'Make-up classes', 'course-schedule-connector' ),
				'label'       => __( 'Make-up classes for this course', 'course-schedule-connector' ),
				'icon'        => 'update',
				'moduleIcon'  => 'divi/module-tabs',
				'columns'     => array( 'date', 'time', 'room', 'trainer' ),
				'description' => __( 'Classes that stand in for one somebody missed.', 'course-schedule-connector' ),
				'heading'     => true,
			),
		);

		$trainer = array(
			'name'           => array(
				'kind'        => self::TEXT,
				'title'       => __( 'Trainer name', 'course-schedule-connector' ),
				'label'       => null,
				'icon'        => 'editor-textcolor',
				'moduleIcon'  => 'divi/module-heading',
				'description' => __( 'The trainer’s name, as a heading you can style.', 'course-schedule-connector' ),
				'heading'     => false,
			),
			'photo'          => array(
				'kind'        => self::HTML,
				'title'       => __( 'Photograph', 'course-schedule-connector' ),
				'label'       => null,
				'icon'        => 'format-image',
				'moduleIcon'  => 'divi/module-team-member',
				'description' => __( 'The trainer’s picture: your own where you set one, otherwise the one from iSport.', 'course-schedule-connector' ),
				'heading'     => false,
				'image'       => true,
			),
			'qualifications' => array(
				'kind'        => self::LIST,
				'title'       => __( 'Qualifications', 'course-schedule-connector' ),
				'label'       => __( 'Qualifications', 'course-schedule-connector' ),
				'icon'        => 'awards',
				'moduleIcon'  => 'divi/module-icon-list',
				'description' => __( 'Every qualification, in the order they were entered.', 'course-schedule-connector' ),
				'heading'     => true,
				'bullets'     => true,
			),
			'hobbies'        => array(
				'kind'        => self::LIST,
				'title'       => __( 'Interests', 'course-schedule-connector' ),
				'label'       => __( 'Interests', 'course-schedule-connector' ),
				'icon'        => 'heart',
				'moduleIcon'  => 'divi/module-icon-list-item',
				'description' => __( 'What the trainer does when they are not in the hall.', 'course-schedule-connector' ),
				'heading'     => true,
				'bullets'     => true,
			),
			'fact'           => array(
				'kind'        => self::TEXT,
				'title'       => __( 'Something worth knowing', 'course-schedule-connector' ),
				'label'       => __( 'Something worth knowing', 'course-schedule-connector' ),
				'icon'        => 'lightbulb',
				'moduleIcon'  => 'divi/module-tooltip',
				'description' => __( 'The sentence or two that makes this a person.', 'course-schedule-connector' ),
				'heading'     => true,
			),
			'motto'          => array(
				'kind'        => self::TEXT,
				'title'       => __( 'Motto', 'course-schedule-connector' ),
				'label'       => '',
				'icon'        => 'format-quote',
				'moduleIcon'  => 'divi/module-testimonial',
				'description' => __( 'The trainer’s motto.', 'course-schedule-connector' ),
				'heading'     => false,
			),
			'text'           => array(
				'kind'        => self::HTML,
				'title'       => __( 'Trainer description', 'course-schedule-connector' ),
				'label'       => '',
				'icon'        => 'editor-paragraph',
				'moduleIcon'  => 'divi/module-text',
				'description' => __( 'The words written on the trainer in WordPress.', 'course-schedule-connector' ),
				'heading'     => false,
				'bullets'     => true,
			),
			'courses'        => array(
				'kind'        => self::HTML,
				'title'       => __( 'Courses this trainer runs', 'course-schedule-connector' ),
				'label'       => __( 'Courses this trainer runs', 'course-schedule-connector' ),
				'icon'        => 'calendar-alt',
				'moduleIcon'  => 'divi/module-blog',
				'columns'     => array( 'name', 'day', 'hours', 'period', 'price', 'places' ),
				'description' => __( 'Every course paired with this trainer, as a table.', 'course-schedule-connector' ),
				'heading'     => true,
			),
		);

		// A kind of course holds no facts of its own — no price, no room, no
		// capacity, because it is not a thing anybody signs up for. What it has
		// that a page cannot write by hand is the timetable of what runs under
		// it, and what a term costs, and those are two of these three. The
		// third is the description, which a person does write — but a theme
		// builder template is not a page and has nowhere to write it, so it
		// needs a field like everything else on that template.
		$kind = array(
			'text'    => array(
				'kind'        => self::HTML,
				'title'       => __( 'Kind description', 'course-schedule-connector' ),
				'label'       => '',
				'icon'        => 'editor-paragraph',
				'moduleIcon'  => 'divi/module-text',
				'description' => __( 'The words written on this kind of course in WordPress — the ones fetched from a course, or whatever was written over them.', 'course-schedule-connector' ),
				'heading'     => false,
				'bullets'     => true,
			),
			'prices'  => array(
				'kind'        => self::HTML,
				'title'       => __( 'Prices of this kind', 'course-schedule-connector' ),
				'label'       => __( 'Price', 'course-schedule-connector' ),
				'icon'        => 'tag',
				'moduleIcon'  => 'divi/module-pricing-tables',
				'columns'     => array( 'duration', 'price' ),
				'description' => __( 'What the courses of this kind cost, as a table of two columns: one row per length of lesson and price actually on offer, however many courses share it.', 'course-schedule-connector' ),
				'heading'     => true,
				'filters'     => true,
			),
			'courses' => array(
				'kind'        => self::HTML,
				'title'       => __( 'Courses of this kind', 'course-schedule-connector' ),
				'label'       => __( 'Courses of this kind', 'course-schedule-connector' ),
				'icon'        => 'calendar-alt',
				'moduleIcon'  => 'divi/module-post-slider',
				'columns'     => array( 'day', 'hours', 'age', 'gender', 'level', 'places', 'button' ),
				'description' => __( 'Every course filed under this kind, as a table — or only the ones the settings ask for.', 'course-schedule-connector' ),
				'heading'     => true,
				'filters'     => true,
			),
		);

		$fields = array();

		foreach ( $course as $key => $field ) {
			$fields[ self::COURSE . '-' . $key ] = array_merge(
				self::defaults(),
				$field,
				array( 'context' => self::COURSE )
			);
		}

		foreach ( $trainer as $key => $field ) {
			$fields[ self::TRAINER . '-' . $key ] = array_merge(
				self::defaults(),
				$field,
				array( 'context' => self::TRAINER )
			);
		}

		foreach ( $kind as $key => $field ) {
			$fields[ self::KIND . '-' . $key ] = array_merge(
				self::defaults(),
				$field,
				array( 'context' => self::KIND )
			);
		}

		/**
		 * Filters the fields a page can be built out of.
		 *
		 * Adding one here adds a Gutenberg block and a Divi module at once.
		 *
		 * @since 0.5.0
		 *
		 * @param array<string, array<string, mixed>> $fields Fields, keyed by name.
		 */
		return apply_filters( 'cscs_fields', $fields );
	}

	/**
	 * What a field is when its own entry says nothing.
	 *
	 * A field is text without a picture and without a table until it says
	 * otherwise, and it carries Divi's plain text icon until somebody gives it
	 * a better one. Written once here rather than repeated in twenty-three
	 * entries, so that a key added to the shape reaches every field at once.
	 *
	 * @return array<string, mixed>
	 */
	private static function defaults(): array {
		return array(
			'image'      => false,
			'bullets'    => false,
			'filters'    => false,
			'signup'     => '',
			'columns'    => array(),
			'moduleIcon' => 'divi/module-text',
		);
	}

	/**
	 * Returns one field's definition, or null when there is no such field.
	 *
	 * @param string $name Field name, `course-price` and the like.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $name ): ?array {
		$all = self::all();

		return $all[ $name ] ?? null;
	}

	/**
	 * Works out what a field says about the post it is standing on.
	 *
	 * @param Plugin   $plugin Plugin instance.
	 * @param string   $name   Field name.
	 * @param \WP_Post $post   The course or trainer.
	 * @return array{kind: string, text: string, list: array<int, string>, html: string}
	 */
	public static function value( Plugin $plugin, string $name, \WP_Post $post, array $settings = array() ): array {
		$field = self::get( $name );
		$empty = array(
			'kind' => self::TEXT,
			'text' => '',
			'list' => array(),
			'html' => '',
		);

		if ( null === $field ) {
			return $empty;
		}

		$empty['kind'] = (string) $field['kind'];
		$key           = substr( $name, strlen( (string) $field['context'] ) + 1 );

		if ( self::COURSE === $field['context'] ) {
			return array_merge( $empty, self::course_value( $plugin, $key, $post, $settings ) );
		}

		if ( self::KIND === $field['context'] ) {
			return array_merge( $empty, self::kind_value( $plugin, $key, $post, $settings ) );
		}

		return array_merge( $empty, self::trainer_value( $plugin, $key, $post, $settings ) );
	}

	/**
	 * Works out one course field.
	 *
	 * @param Plugin   $plugin Plugin instance.
	 * @param string   $key    Field key without its context.
	 * @param \WP_Post $post   Course.
	 * @return array<string, mixed>
	 */
	private static function course_value( Plugin $plugin, string $key, \WP_Post $post, array $settings = array() ): array {
		$detail = new CourseDetail( $plugin, $post );

		switch ( $key ) {
			case 'name':
				return array( 'text' => $detail->name() );

			case 'price':
				return array( 'text' => $detail->price() );

			case 'day':
				return array( 'text' => $detail->meeting_days() );

			case 'time':
				return array( 'text' => $detail->meeting_hours() );

			case 'period':
				return array( 'text' => $detail->period() );

			case 'lessons':
				return array( 'text' => $detail->lessons() );

			case 'age':
				return array( 'text' => $detail->age() );

			case 'gender':
				return array( 'text' => $detail->gender() );

			case 'level':
				return array( 'text' => $detail->level() );

			case 'room':
				return array( 'text' => $detail->rooms() );

			case 'places':
				return array( 'text' => $detail->places() );

			case 'trainer':
				$name = $detail->trainer();
				$url  = $detail->trainer_url();

				if ( '' === $name ) {
					return array( 'html' => '' );
				}

				return array(
					'html' => '' === $url
						? esc_html( $name )
						: sprintf(
							'<a class="cscs-field__link" href="%s">%s</a>',
							esc_url( $url ),
							esc_html( $name )
						),
				);

			case 'button':
			case 'link':
				return array(
					'html' => $detail->button(
						array_merge( $settings, array( 'shape' => 'link' === $key ? 'link' : 'button' ) )
					),
				);

			case 'image':
				return array( 'html' => self::picture( (int) get_post_thumbnail_id( $post ), $post, $settings ) );

			case 'text':
				return array( 'html' => self::written_text( $post ) );

			case 'api':
				return array( 'html' => $detail->api_description() );

			case 'contact':
				return array( 'html' => self::contact( $detail ) );

			case 'schedule':
				return array( 'html' => self::table( $detail->schedule() ) );

			case 'makeup':
				return array( 'html' => self::table( $detail->makeup() ) );
		}

		return array();
	}

	/**
	 * Works out one field of a kind of course.
	 *
	 * @param Plugin   $plugin Plugin instance.
	 * @param string   $key    Field key without its context.
	 * @param \WP_Post $post     Kind page.
	 * @param array<string, mixed> $settings Block or module settings.
	 * @return array<string, mixed>
	 */
	private static function kind_value( Plugin $plugin, string $key, \WP_Post $post, array $settings = array() ): array {
		$detail = new KindDetail( $plugin, $post );

		switch ( $key ) {
			case 'text':
				return array( 'html' => self::written_text( $post ) );

			case 'courses':
				return array( 'html' => self::table( $detail->course_listing( $settings ) ) );

			case 'prices':
				return array( 'html' => self::table( $detail->price_listing( $settings ) ) );
		}

		return array();
	}

	/**
	 * Works out one trainer field.
	 *
	 * @param Plugin   $plugin Plugin instance.
	 * @param string   $key    Field key without its context.
	 * @param \WP_Post $post   Trainer.
	 * @return array<string, mixed>
	 */
	private static function trainer_value( Plugin $plugin, string $key, \WP_Post $post, array $settings = array() ): array {
		$detail = new TrainerDetail( $plugin, $post );

		switch ( $key ) {
			case 'name':
				return array( 'text' => $detail->name() );

			case 'fact':
				return array( 'text' => $detail->fact() );

			case 'motto':
				return array( 'text' => $detail->motto() );

			case 'qualifications':
				return array( 'list' => $detail->qualifications() );

			case 'hobbies':
				return array( 'list' => $detail->hobbies() );

			case 'photo':
				return array( 'html' => self::picture( $detail->photograph(), $post, $settings ) );

			case 'text':
				return array( 'html' => self::written_text( $post ) );

			case 'courses':
				return array( 'html' => self::table( $detail->course_listing() ) );
		}

		return array();
	}

	/**
	 * Renders a picture the way a picture wants to be rendered.
	 *
	 * A field showing an image is not the same shape as a field showing a
	 * price, and pretending otherwise is what left this module without the
	 * settings anybody would look for on it: which size to serve, what the alt
	 * text says, and whether the picture is a link. Those are decisions about
	 * an image, so they are made here rather than by whoever is arranging the
	 * page in CSS afterwards.
	 *
	 * @param int                  $attachment Attachment id.
	 * @param \WP_Post             $post       The course or trainer it belongs to.
	 * @param array<string, mixed> $settings   Field settings.
	 * @return string
	 */
	private static function picture( int $attachment, \WP_Post $post, array $settings ): string {
		if ( 0 === $attachment ) {
			return '';
		}

		$size = (string) ( $settings['imageSize'] ?? 'large' );
		$size = in_array( $size, self::sizes(), true ) ? $size : 'large';

		$alt = trim( (string) ( $settings['imageAlt'] ?? '' ) );
		$alt = '' === $alt ? (string) get_the_title( $post ) : $alt;

		$image = (string) wp_get_attachment_image(
			$attachment,
			$size,
			false,
			array(
				'class' => 'cscs-field__image',
				'alt'   => $alt,
			)
		);

		if ( '' === $image ) {
			return '';
		}

		$href = self::picture_link( $attachment, $post, $settings );

		if ( '' === $href ) {
			return $image;
		}

		$blank = ! empty( $settings['imageLinkTarget'] );

		return sprintf(
			'<a class="cscs-field__image-link" href="%s"%s>%s</a>',
			esc_url( $href ),
			// `noopener` on a new window is not optional: without it the page
			// that opens can reach back through `window.opener`.
			$blank ? ' target="_blank" rel="noopener noreferrer"' : '',
			$image
		);
	}

	/**
	 * Returns where a picture links to, if anywhere.
	 *
	 * @param int                  $attachment Attachment id.
	 * @param \WP_Post             $post       The course or trainer.
	 * @param array<string, mixed> $settings   Field settings.
	 * @return string
	 */
	private static function picture_link( int $attachment, \WP_Post $post, array $settings ): string {
		switch ( (string) ( $settings['imageLink'] ?? 'none' ) ) {
			case 'post':
				return (string) get_permalink( $post );

			case 'file':
				return (string) wp_get_attachment_image_url( $attachment, 'full' );

			case 'custom':
				return esc_url_raw( (string) ( $settings['imageLinkUrl'] ?? '' ) );
		}

		return '';
	}

	/**
	 * Returns what each column of a listing is called.
	 *
	 * The one map. A table's columns are named in three places — the display
	 * sets screen, a Divi module's design panel, a block's sidebar — and three
	 * maps would be three chances for a column to be called two things.
	 *
	 * @return array<string, string>
	 */
	public static function column_labels(): array {
		return array(
			'name'     => __( 'Course', 'course-schedule-connector' ),
			'course'   => __( 'Course', 'course-schedule-connector' ),
			'activity' => __( 'Activity', 'course-schedule-connector' ),
			'days'     => __( 'Days and times', 'course-schedule-connector' ),
			'duration' => __( 'Length', 'course-schedule-connector' ),
			'age'      => __( 'Age', 'course-schedule-connector' ),
			'gender'   => __( 'Gender', 'course-schedule-connector' ),
			'level'    => __( 'Level', 'course-schedule-connector' ),
			'day'      => __( 'Day', 'course-schedule-connector' ),
			'hours'    => __( 'Time from and to', 'course-schedule-connector' ),
			'date'     => __( 'Date', 'course-schedule-connector' ),
			'time'     => __( 'Time', 'course-schedule-connector' ),
			'room'     => __( 'Room', 'course-schedule-connector' ),
			'trainer'  => __( 'Trainer', 'course-schedule-connector' ),
			'period'   => __( 'Runs from and to', 'course-schedule-connector' ),
			'lessons'  => __( 'Number of classes', 'course-schedule-connector' ),
			'price'    => __( 'Price', 'course-schedule-connector' ),
			'places'   => __( 'Places left', 'course-schedule-connector' ),
			'state'    => __( 'Cancelled', 'course-schedule-connector' ),
			'button'   => __( 'Booking button', 'course-schedule-connector' ),
		);
	}

	/**
	 * Returns what one column is called.
	 *
	 * @param string $column Column key.
	 * @return string
	 */
	public static function column_label( string $column ): string {
		$labels = self::column_labels();

		return $labels[ $column ] ?? $column;
	}

	/**
	 * Returns the parts of a field a designer can style separately.
	 *
	 * This is the list Divi's modules declare as attributes, the list their
	 * render callbacks emit styles for, and the list the builder writes CSS
	 * from. Keeping it in one place is what stops the third from quietly
	 * falling behind the first — a module that offers a setting and never
	 * writes its CSS is worse than one that does not offer it.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return array<string, string> Attribute name to the selector it styles.
	 */
	/**
	 * Whether a field has a heading at all.
	 *
	 * Three answers live in one key, because the heading a field prints is its
	 * label and nothing else. A label is the name of the field — *Price*,
	 * *Trainer* — and it is printed above or beside the value. An empty label
	 * is a field with no name of its own that will still take one somebody
	 * types: a description is the obvious case, where *Description* over it is
	 * a reasonable thing to want.
	 *
	 * And `null` is a field that is not that kind of thing. The name of a
	 * course is already a heading; a photograph and a sign-up button are not
	 * things a heading sits above. For those there is no heading, no setting
	 * that mentions one, and no typography group for it — rather than a row of
	 * controls that do nothing but take up the panel a designer is reading.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return bool
	 */
	public static function heads( array $field ): bool {
		// Not `?? ''`. Null coalescing cannot tell a key holding null from a
		// key that is not there, and null is the whole answer here — written
		// the short way this returns true for every field, which is exactly how
		// it read the first time.
		if ( ! array_key_exists( 'label', $field ) ) {
			return true;
		}

		return null !== $field['label'];
	}

	public static function style_elements( array $field ): array {
		$elements = self::heads( $field )
			? array(
				'title' => '{{selector}} .cscs-field__label',
				'value' => '{{selector}} .cscs-field__value',
			)
			: array( 'value' => '{{selector}} .cscs-field__value' );

		if ( ! empty( $field['image'] ) ) {
			$elements['image'] = '{{selector}} .cscs-field__image';
		}

		// The button is Divi's own kind of element and carries its own panel;
		// the link is an anchor with a font group on it. Either way the styles
		// have to be emitted, or the panel accepts settings and the page
		// ignores them.
		if ( 'button' === (string) ( $field['signup'] ?? '' ) ) {
			$elements['button'] = '{{selector}} .cscs-button';
		}

		if ( 'link' === (string) ( $field['signup'] ?? '' ) ) {
			$elements['signupLink'] = '{{selector}} .cscs-signup-link';
		}

		// A list is two things a designer means separately: the words of an
		// item, and the mark in front of them.
		if ( ! empty( $field['bullets'] ) ) {
			$elements['bulletItem']   = '{{selector}} .cscs-field__value li';
			$elements['bulletMarker'] = '{{selector}} .cscs-field__value li::marker';
		}

		$columns = (array) ( $field['columns'] ?? array() );

		if ( array() === $columns ) {
			return $elements;
		}

		// A table is not one thing either. Its heading row, its cells, the
		// links inside them and the banding behind them are each what somebody
		// means when they say the table looks wrong.
		$elements['tableHead']   = '{{selector}} .cscs-table thead th';
		$elements['tableCell']   = '{{selector}} .cscs-table tbody td';
		$elements['tableLink']   = '{{selector}} .cscs-table tbody a';
		$elements['tableStripe'] = '{{selector}} .cscs-table tbody tr:nth-child(even)';

		foreach ( $columns as $column ) {
			$elements[ self::column_attribute( (string) $column ) ] =
				'{{selector}} .cscs-table .cscs-col-' . $column;
		}

		return $elements;
	}

	/**
	 * Returns the attribute name a column's settings live under.
	 *
	 * @param string $column Column key.
	 * @return string
	 */
	public static function column_attribute( string $column ): string {
		return 'col' . ucfirst( str_replace( ' ', '', ucwords( str_replace( array( '-', '_' ), ' ', $column ) ) ) );
	}

	/**
	 * Returns the image sizes a field may be asked for.
	 *
	 * Read from the site rather than listed here: a theme adds sizes, and a
	 * hard-coded list would quietly refuse the one somebody registered for
	 * exactly this purpose.
	 *
	 * @return array<int, string>
	 */
	public static function sizes(): array {
		$sizes = function_exists( 'get_intermediate_image_sizes' ) ? get_intermediate_image_sizes() : array();

		return array_values( array_unique( array_merge( array_map( 'strval', $sizes ), array( 'full' ) ) ) );
	}

	/**
	 * Renders the post's own text, without setting the content filter on itself.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	private static function written_text( \WP_Post $post ): string {
		$content = (string) get_post_field( 'post_content', $post->ID );

		if ( '' === trim( $content ) ) {
			return '';
		}

		// `the_content` is where the course and trainer pages hang themselves,
		// and running it from inside a block that is itself part of that page
		// would call it from within itself. The formatting filters are applied
		// by hand instead, which is what everything else on the page has had.
		$content = wp_unslash( $content );
		$content = do_blocks( $content );
		$content = wptexturize( $content );
		$content = wpautop( $content );
		$content = do_shortcode( $content );

		return wp_kses_post( $content );
	}

	/**
	 * Renders a listing as its table, or nothing when there is none.
	 *
	 * @param Listing|null $listing Listing.
	 * @return string
	 */
	private static function table( ?Listing $listing ): string {
		if ( ! $listing instanceof Listing ) {
			return '';
		}

		$file = Renderer::locate( 'partials/table' );

		if ( '' === $file ) {
			return '';
		}

		// The name the partial reads it under. Every template variable carries
		// the plugin's prefix, because a template is included at file scope and
		// a bare `$listing` there is indistinguishable from a global.
		$cscs_listing = $listing;

		ob_start();

		require $file;

		return (string) ob_get_clean();
	}

	/**
	 * Renders the contact details, where somebody entered any.
	 *
	 * @param CourseDetail $detail Course.
	 * @return string
	 */
	private static function contact( CourseDetail $detail ): string {
		$contact = $detail->contact();

		if ( null === $contact ) {
			return '';
		}

		$lines = array();

		if ( '' !== $contact['name'] ) {
			$lines[] = sprintf( '<p class="cscs-field__contact-name">%s</p>', esc_html( $contact['name'] ) );
		}

		$links = '';

		if ( '' !== $contact['email'] ) {
			$links .= sprintf(
				'<li><a href="%s">%s</a></li>',
				esc_url( 'mailto:' . $contact['email'] ),
				esc_html( $contact['email'] )
			);
		}

		if ( '' !== $contact['phone'] ) {
			$links .= sprintf(
				'<li><a href="%s">%s</a></li>',
				esc_url( 'tel:' . (string) preg_replace( '/\s+/', '', $contact['phone'] ) ),
				esc_html( $contact['phone'] )
			);
		}

		if ( '' !== $links ) {
			$lines[] = '<ul class="cscs-field__contact-list">' . $links . '</ul>';
		}

		if ( '' !== $contact['note'] ) {
			$lines[] = sprintf( '<p class="cscs-field__contact-note">%s</p>', esc_html( $contact['note'] ) );
		}

		return implode( '', $lines );
	}

	/**
	 * Returns the post type a field's context lives in.
	 *
	 * @param string $context Context.
	 * @return string
	 */
	public static function post_type( string $context ): string {
		$types = array(
			self::TRAINER => TrainerType::TRAINER,
			self::KIND    => KindType::KIND,
		);

		return $types[ $context ] ?? PostType::COURSE;
	}
}
