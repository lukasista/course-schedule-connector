<?php
/**
 * Tests for occurrence serialisation.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Api\Mapper;
use CSCS\Data\LessonPayload;
use PHPUnit\Framework\TestCase;

/**
 * Guards the round trip that lets matching be re-run without a network request.
 *
 * @covers \CSCS\Data\LessonPayload
 */
final class LessonPayloadTest extends TestCase {

	/**
	 * Every occurrence in the fixture survives a store and reload unchanged.
	 *
	 * A renamed property would otherwise break re-matching silently, months
	 * after the change, with no error anywhere.
	 *
	 * @return void
	 */
	public function test_occurrences_survive_the_round_trip_unchanged(): void {
		$mapper   = new Mapper();
		$original = $mapper->map_lessons(
			json_decode( (string) file_get_contents( __DIR__ . '/../fixtures/activities.json' ), true )
		);

		$stored = array();

		foreach ( $original as $lesson ) {
			$decoded  = json_decode( LessonPayload::encode( $lesson ), true );
			$stored[] = LessonPayload::to_api_shape( is_array( $decoded ) ? $decoded : array() );
		}

		$restored = $mapper->map_lessons( $stored );

		$this->assertSame( array_keys( $original ), array_keys( $restored ) );

		foreach ( $original as $id => $lesson ) {
			$this->assertSame(
				get_object_vars( $lesson ),
				get_object_vars( $restored[ $id ] ),
				'Occurrence ' . $id . ' did not survive the round trip.'
			);
		}
	}

	/**
	 * A record missing every optional field still decodes into something usable.
	 *
	 * @return void
	 */
	public function test_a_sparse_record_still_decodes(): void {
		$shape = LessonPayload::to_api_shape( array( 'id_term' => 42 ) );

		$this->assertSame( 42, $shape['id_activity_term'] );
		$this->assertNull( $shape['price'] );
		$this->assertSame( 0, $shape['canceled'] );
	}
}
