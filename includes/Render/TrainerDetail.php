<?php
/**
 * What a trainer's own page shows.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

use CSCS\Data\TrainerRepository;
use CSCS\Data\TrainerType;
use CSCS\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * A trainer, worked out, ready for their page.
 *
 * The same arrangement as a course: everything is decided here so the template
 * only arranges it, and so the blocks and the Divi modules ask one object for a
 * field rather than each working the answer out again slightly differently.
 */
final class TrainerDetail {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * The trainer post.
	 *
	 * @var \WP_Post
	 */
	private \WP_Post $post;

	/**
	 * Trainers.
	 *
	 * @var TrainerRepository
	 */
	private TrainerRepository $trainers;

	/**
	 * Constructor.
	 *
	 * @param Plugin   $plugin Plugin instance.
	 * @param \WP_Post $post   Trainer post.
	 */
	public function __construct( Plugin $plugin, \WP_Post $post ) {
		$this->plugin   = $plugin;
		$this->post     = $post;
		$this->trainers = new TrainerRepository();
	}

	/**
	 * Returns the trainer post.
	 *
	 * @return \WP_Post
	 */
	public function post(): \WP_Post {
		return $this->post;
	}

	/**
	 * Returns the trainer's name.
	 *
	 * @return string
	 */
	public function name(): string {
		return (string) get_the_title( $this->post );
	}

	/**
	 * Returns the qualifications, in the order somebody put them in.
	 *
	 * @return array<int, string>
	 */
	public function qualifications(): array {
		return TrainerType::sanitise_list( get_post_meta( $this->post->ID, TrainerType::META_QUALIFICATIONS, true ) );
	}

	/**
	 * Returns the interests.
	 *
	 * @return array<int, string>
	 */
	public function hobbies(): array {
		return TrainerType::sanitise_list( get_post_meta( $this->post->ID, TrainerType::META_HOBBIES, true ) );
	}

	/**
	 * Returns the short fact about this trainer.
	 *
	 * @return string
	 */
	public function fact(): string {
		return (string) get_post_meta( $this->post->ID, TrainerType::META_FACT, true );
	}

	/**
	 * Returns the trainer's motto.
	 *
	 * @return string
	 */
	public function motto(): string {
		return (string) get_post_meta( $this->post->ID, TrainerType::META_MOTTO, true );
	}

	/**
	 * Returns the attachment id of the photograph to show.
	 *
	 * @return int
	 */
	public function photograph(): int {
		return $this->trainers->photograph( $this->post->ID );
	}

	/**
	 * Returns the photograph, ready to print.
	 *
	 * @param string $size Image size.
	 * @return string HTML, or an empty string when there is no photograph.
	 */
	public function photograph_html( string $size = 'medium_large' ): string {
		$id = $this->photograph();

		if ( 0 === $id ) {
			return '';
		}

		return (string) wp_get_attachment_image(
			$id,
			$size,
			false,
			array(
				'class' => 'cscs-trainer__photo-image',
				'alt'   => $this->name(),
			)
		);
	}

	/**
	 * Returns the courses this trainer runs, as name and address.
	 *
	 * @param bool $include_finished Whether courses that have ended count.
	 * @return array<int, array{id: int, name: string, url: string}>
	 */
	public function courses( bool $include_finished = true ): array {
		$courses = array();

		foreach ( $this->trainers->courses( $this->post->ID, $include_finished ) as $course ) {
			$courses[] = array(
				'id'   => (int) $course->ID,
				'name' => (string) get_the_title( $course ),
				'url'  => (string) get_permalink( $course ),
			);
		}

		return $courses;
	}

	/**
	 * Returns the courses this trainer runs, as a listing.
	 *
	 * The same listing code a page of courses uses, so a trainer's courses fold
	 * on a telephone exactly as every other list of courses does.
	 *
	 * @return Listing|null
	 */
	public function course_listing(): ?Listing {
		$ids = array_map(
			static function ( array $course ): int {
				return $course['id'];
			},
			$this->courses()
		);

		if ( array() === $ids ) {
			return null;
		}

		$query = new Query( $this->plugin );
		$rows  = array();

		foreach ( $ids as $id ) {
			$post = get_post( $id );

			if ( $post instanceof \WP_Post ) {
				$rows[] = $query->course_row( $post );
			}
		}

		if ( array() === $rows ) {
			return null;
		}

		$set = \CSCS\Data\DisplaySet::from_array(
			array(
				'type'    => \CSCS\Data\DisplaySet::TYPE_COURSES,
				// Day and time apart, the way the course's own page says them:
				// "Po 15:30" is two facts in the one shape nobody can read a
				// timetable out of.
				'columns' => array( 'name', 'day', 'hours', 'period', 'price', 'places' ),
			)
		);

		$times = $query->course_times(
			array_map(
				static function ( array $row ): int {
					return (int) ( $row['course_id'] ?? 0 );
				},
				$rows
			)
		);

		return new Listing( $set, $rows, Renderer::labels_for( $set ), $this->plugin->settings(), $times );
	}

	/**
	 * Returns the machine-readable description of this trainer.
	 *
	 * @return string JSON-LD, or an empty string when there is too little to say.
	 */
	public function structured_data(): string {
		$name = $this->name();

		if ( '' === $name ) {
			return '';
		}

		$data = array_filter(
			array(
				'@context'    => 'https://schema.org',
				'@type'       => 'Person',
				'name'        => $name,
				'url'         => (string) get_permalink( $this->post ),
				'jobTitle'    => __( 'Trainer', 'course-schedule-connector' ),
				'description' => wp_strip_all_tags( $this->fact() ),
				'image'       => (string) wp_get_attachment_image_url( $this->photograph(), 'large' ),
				'worksFor'    => array(
					'@type' => 'Organization',
					'name'  => get_bloginfo( 'name' ),
					'url'   => home_url( '/' ),
				),
			)
		);

		return (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}
}
