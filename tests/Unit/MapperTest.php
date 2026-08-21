<?php
/**
 * Tests for payload mapping.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Api\Mapper;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the translation of raw payloads into typed records.
 *
 * @covers \CSCS\Api\Mapper
 */
final class MapperTest extends TestCase {

	/**
	 * Mapper under test.
	 *
	 * @var Mapper
	 */
	private Mapper $mapper;

	/**
	 * Sets up the mapper.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->mapper = new Mapper();
	}

	/**
	 * Loads a fixture.
	 *
	 * @param string $name File name without extension.
	 * @return mixed
	 */
	private function fixture( string $name ) {
		return json_decode( (string) file_get_contents( __DIR__ . '/../fixtures/' . $name . '.json' ), true );
	}

	/**
	 * Courses are keyed by their own id.
	 *
	 * @return void
	 */
	public function test_courses_are_keyed_by_id(): void {
		$courses = $this->mapper->map_courses( $this->fixture( 'courses' ) );

		$this->assertCount( 4, $courses );
		$this->assertArrayHasKey( 1070, $courses );
		$this->assertSame( 1070, $courses[1070]->id );
	}

	/**
	 * Every numeric field ends up as an integer regardless of how it arrived.
	 *
	 * @return void
	 */
	public function test_numeric_fields_are_normalised(): void {
		$course = $this->mapper->map_courses( $this->fixture( 'courses' ) )[1070];

		$this->assertSame( 12, $course->capacity );
		$this->assertSame( 3, $course->available );
		$this->assertSame( 18, $course->number_lessons );
		$this->assertSame( '1960.00', $course->price );
	}

	/**
	 * A course with no price is free; that must survive mapping as null.
	 *
	 * @return void
	 */
	public function test_absent_price_is_preserved_as_null(): void {
		$course = $this->mapper->map_courses( $this->fixture( 'courses' ) )[1200];

		$this->assertNull( $course->price );
	}

	/**
	 * A hostile URL or colour in the payload must not reach the renderer.
	 *
	 * @return void
	 */
	public function test_unsafe_values_are_dropped_during_mapping(): void {
		$course = $this->mapper->map_courses( $this->fixture( 'courses' ) )[1200];

		$this->assertNull( $course->url );
		$this->assertNull( $course->colour );
	}

	/**
	 * Terms are sorted chronologically no matter how they arrived.
	 *
	 * @return void
	 */
	public function test_terms_are_sorted_by_timestamp(): void {
		$course = $this->mapper->map_courses( $this->fixture( 'courses' ) )[1070];

		$this->assertCount( 2, $course->terms );
		$this->assertSame( 1789135200, $course->terms[0]->stamp );
		$this->assertSame( 1789740000, $course->terms[1]->stamp );
	}

	/**
	 * A record without an identifier is skipped rather than mapped to garbage.
	 *
	 * @return void
	 */
	public function test_records_without_an_identifier_are_skipped(): void {
		$lessons = $this->mapper->map_lessons( $this->fixture( 'activities' ) );

		$this->assertCount( 4, $lessons );
		$this->assertArrayNotHasKey( 0, $lessons );
	}

	/**
	 * Cancellation and booking flags become real booleans.
	 *
	 * @return void
	 */
	public function test_flags_become_booleans(): void {
		$lessons = $this->mapper->map_lessons( $this->fixture( 'activities' ) );

		$this->assertTrue( $lessons[55920]->canceled );
		$this->assertFalse( $lessons[55918]->canceled );
		$this->assertTrue( $lessons[55918]->booking_allowed );
		$this->assertFalse( $lessons[55919]->booking_allowed );
		$this->assertSame( 'Capacity full', $lessons[55919]->booking_not_allowed_reason );
	}

	/**
	 * A rental has no price at all, which is different from being free.
	 *
	 * @return void
	 */
	public function test_rental_has_no_price(): void {
		$lessons = $this->mapper->map_lessons( $this->fixture( 'activities' ) );

		$this->assertNull( $lessons[55921]->price );
		$this->assertSame( array( 9 => 'Pronájem haly' ), $lessons[55921]->tags );
	}

	/**
	 * The whole matching strategy rests on this: the course and its class agree
	 * on the match key even though their names differ by a space.
	 *
	 * @return void
	 */
	public function test_course_and_its_lesson_share_a_match_key(): void {
		$course = $this->mapper->map_courses( $this->fixture( 'courses' ) )[1070];
		$lesson = $this->mapper->map_lessons( $this->fixture( 'activities' ) )[55918];

		$this->assertNotSame( $course->activity_name, $lesson->activity_name );
		$this->assertSame( $course->match_key, $lesson->match_key );
		$this->assertContains( $lesson->stamp_from, $course->term_stamps() );
	}

	/**
	 * The activity id is not the identity of a course: the same id appears under
	 * two different course names at two different times.
	 *
	 * @return void
	 */
	public function test_activity_id_is_not_a_course_identifier(): void {
		$lessons = $this->mapper->map_lessons( $this->fixture( 'activities' ) );

		$this->assertSame( 368, $lessons[55919]->id_activity );
		$this->assertSame( 368, $lessons[55920]->id_activity );
		$this->assertNotSame( $lessons[55919]->match_key, $lessons[55920]->match_key );
	}

	/**
	 * A payload that is not a list produces an empty result rather than an error.
	 *
	 * @return void
	 */
	public function test_unexpected_payload_shapes_produce_nothing(): void {
		$this->assertSame( array(), $this->mapper->map_courses( 'not a list' ) );
		$this->assertSame( array(), $this->mapper->map_lessons( null ) );
		$this->assertSame( array(), $this->mapper->map_courses( array( 'scalar', 42 ) ) );
	}
}
