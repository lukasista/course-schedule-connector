<?php
/**
 * Tests for the API client's guards.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Api\ApiException;
use CSCS\Api\CircuitBreaker;
use CSCS\Api\Client;
use CSCS\Api\Mapper;
use CSCS\Api\RateLimiter;
use CSCS\Api\Response;
use CSCS\Cache\Store;
use CSCS\Settings;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CSCS\Api\Client
 */
final class ClientTest extends TestCase {

	/**
	 * Resets in-memory WordPress state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		cscs_reset_test_state();

		update_option(
			Settings::OPTION,
			array(
				'api_base_url'        => 'https://jojogym.isportsystem.cz',
				'request_retries'     => 2,
				'cache_fresh_seconds' => 300,
				'cache_stale_seconds' => 3600,
			)
		);
	}

	/**
	 * Builds a client around a scripted transport.
	 *
	 * @param FakeHttp $http      Transport.
	 * @param int      $cap       Hourly request ceiling.
	 * @param int      $threshold Failures required to open the circuit.
	 * @return Client
	 */
	private function client( FakeHttp $http, int $cap = 60, int $threshold = 3 ): Client {
		$store = new Store();

		return new Client(
			$http,
			new Mapper(),
			$store,
			new RateLimiter( $store, $cap ),
			new CircuitBreaker( $store, $threshold, 1800 ),
			new Settings()
		);
	}

	/**
	 * Returns a successful response carrying the courses fixture.
	 *
	 * @return Response
	 */
	private function courses_response(): Response {
		return new Response(
			200,
			(string) file_get_contents( __DIR__ . '/../fixtures/courses.json' ),
			'application/json'
		);
	}

	/**
	 * The happy path returns mapped courses.
	 *
	 * @return void
	 */
	public function test_courses_are_returned_and_mapped(): void {
		$http   = new FakeHttp( array( $this->courses_response() ) );
		$client = $this->client( $http );

		$courses = $client->get_courses();

		$this->assertCount( 3, $courses );
		$this->assertSame( 1, $http->calls );
	}

	/**
	 * A second call inside the freshness window must not hit the network.
	 *
	 * @return void
	 */
	public function test_a_fresh_cache_hit_makes_no_request(): void {
		$http   = new FakeHttp( array( $this->courses_response() ) );
		$client = $this->client( $http );

		$client->get_courses();
		$client->get_courses();

		$this->assertSame( 1, $http->calls );
	}

	/**
	 * A transport failure is retried, and the retry can succeed.
	 *
	 * @return void
	 */
	public function test_a_transient_failure_is_retried(): void {
		$http = new FakeHttp(
			array(
				new ApiException( 'Connection timed out', 'timeout' ),
				$this->courses_response(),
			)
		);

		$courses = $this->client( $http )->get_courses();

		$this->assertCount( 3, $courses );
		$this->assertSame( 2, $http->calls );
	}

	/**
	 * A malformed body will be malformed again, so it is not retried.
	 *
	 * @return void
	 */
	public function test_malformed_json_is_not_retried(): void {
		$http = new FakeHttp(
			array(
				new Response( 200, '{ this is not json', 'application/json' ),
				$this->courses_response(),
			)
		);

		$this->expectException( ApiException::class );

		try {
			$this->client( $http )->get_courses();
		} finally {
			$this->assertSame( 1, $http->calls );
		}
	}

	/**
	 * Repeated failures open the circuit, after which nothing is attempted.
	 *
	 * @return void
	 */
	public function test_repeated_failures_open_the_circuit(): void {
		$failures = array_fill( 0, 12, new ApiException( 'Connection timed out', 'timeout' ) );
		$http     = new FakeHttp( $failures );
		$client   = $this->client( $http, 60, 2 );

		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			try {
				$client->get_courses( null, true );
			} catch ( ApiException $e ) {
				unset( $e );
			}
		}

		$this->assertFalse( $client->breaker()->is_closed() );

		$calls_before = $http->calls;

		try {
			$client->get_courses( null, true );
		} catch ( ApiException $e ) {
			$this->assertSame( 'circuit_open', $e->get_reason() );
		}

		$this->assertSame( $calls_before, $http->calls, 'No request may be made while the circuit is open.' );
	}

	/**
	 * The hourly ceiling is a hard stop, not a suggestion.
	 *
	 * @return void
	 */
	public function test_the_hourly_ceiling_is_enforced(): void {
		$http   = new FakeHttp( array_fill( 0, 10, $this->courses_response() ) );
		$client = $this->client( $http, 2 );

		$client->get_courses( '20260901', true );
		$client->get_courses( '20260902', true );

		$this->expectException( ApiException::class );
		$this->expectExceptionMessage( 'hourly request ceiling' );

		$client->get_courses( '20260903', true );
	}

	/**
	 * When the remote system fails, stale data is better than an empty page.
	 *
	 * @return void
	 */
	public function test_stale_data_is_served_when_the_api_fails(): void {
		update_option(
			Settings::OPTION,
			array(
				'api_base_url'        => 'https://jojogym.isportsystem.cz',
				'request_retries'     => 0,
				'cache_fresh_seconds' => 30,
				'cache_stale_seconds' => 3600,
			)
		);

		$http   = new FakeHttp( array( $this->courses_response(), new ApiException( 'Connection timed out', 'timeout' ) ) );
		$client = $this->client( $http );

		$client->get_courses();

		// Age the cached entry past its freshness window.
		foreach ( $GLOBALS['cscs_test_transients'] as $key => $entry ) {
			if ( is_array( $entry['value'] ) && isset( $entry['value']['fresh_until'] ) ) {
				$GLOBALS['cscs_test_transients'][ $key ]['value']['fresh_until'] = time() - 1;
			}
		}

		$courses = $client->get_courses();

		$this->assertCount( 3, $courses, 'The last known data must still be served.' );
		$this->assertSame( 2, $http->calls );
	}

	/**
	 * Without a base URL the client refuses to do anything at all.
	 *
	 * @return void
	 */
	public function test_nothing_happens_without_configuration(): void {
		update_option( Settings::OPTION, array( 'api_base_url' => '' ) );

		$http = new FakeHttp( array() );

		$this->expectException( ApiException::class );

		try {
			$this->client( $http )->get_courses();
		} finally {
			$this->assertSame( 0, $http->calls );
		}
	}

	/**
	 * A base URL pointing inside the network is treated as absent.
	 *
	 * @return void
	 */
	public function test_an_internal_base_url_is_rejected(): void {
		update_option( Settings::OPTION, array( 'api_base_url' => 'https://169.254.169.254' ) );

		$http = new FakeHttp( array() );

		$this->expectException( ApiException::class );

		try {
			$this->client( $http )->get_courses();
		} finally {
			$this->assertSame( 0, $http->calls );
		}
	}
}
