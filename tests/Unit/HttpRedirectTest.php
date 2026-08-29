<?php
/**
 * Tests for the one redirect the plugin will follow.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Api\WpHttp;
use PHPUnit\Framework\TestCase;

/**
 * Requests go to the configured host, and a redirect elsewhere is refused.
 *
 * WordPress was being asked to follow two hops to anywhere, which meant an open
 * redirect on the booking system would have been an open redirect out of this
 * site's server — the audit of 29 August found it. One hop is followed now, and
 * only when the scheme, host and port are the ones that were asked for.
 *
 * @covers \CSCS\Api\WpHttp
 */
final class HttpRedirectTest extends TestCase {

	/**
	 * Asks the private helper where it would go next.
	 *
	 * @param string                $from     The URL that was requested.
	 * @param int                   $code     Response code.
	 * @param string                $location Location header.
	 * @return string
	 */
	private function next( string $from, int $code, string $location ): string {
		$method = new \ReflectionMethod( WpHttp::class, 'redirect' );
		$method->setAccessible( true );

		return (string) $method->invoke(
			null,
			$from,
			array(
				'response' => array( 'code' => $code ),
				'headers'  => array( 'location' => $location ),
			)
		);
	}

	/**
	 * A redirect to somebody else's address is not followed.
	 *
	 * @return void
	 */
	public function test_a_redirect_to_another_host_is_refused(): void {
		$this->assertSame(
			'',
			$this->next( 'https://gym.isportsystem.cz/api', 302, 'https://evil.example/api' )
		);
	}

	/**
	 * Nor is one that only changes the scheme, or the port.
	 *
	 * @return void
	 */
	public function test_the_scheme_and_the_port_have_to_match_too(): void {
		$this->assertSame( '', $this->next( 'https://gym.example/api', 301, 'http://gym.example/api' ) );
		$this->assertSame( '', $this->next( 'https://gym.example/api', 301, 'https://gym.example:8443/api' ) );
	}

	/**
	 * The same host is followed, which is what a trailing slash costs.
	 *
	 * @return void
	 */
	public function test_the_same_host_is_followed(): void {
		$this->assertSame(
			'https://gym.example/api/',
			$this->next( 'https://gym.example/api', 301, 'https://gym.example/api/' )
		);
	}

	/**
	 * A relative Location is the same host by definition.
	 *
	 * @return void
	 */
	public function test_a_relative_location_is_resolved_against_the_request(): void {
		$this->assertSame(
			'https://gym.example/api/v2',
			$this->next( 'https://gym.example/api', 308, '/api/v2' )
		);
	}

	/**
	 * A protocol-relative address is another host wearing a shorthand.
	 *
	 * @return void
	 */
	public function test_a_protocol_relative_location_is_not_a_relative_one(): void {
		$this->assertSame( '', $this->next( 'https://gym.example/api', 302, '//evil.example/api' ) );
	}

	/**
	 * A response that is not a redirect sends nobody anywhere.
	 *
	 * @return void
	 */
	public function test_an_ordinary_response_is_not_a_redirect(): void {
		$this->assertSame( '', $this->next( 'https://gym.example/api', 200, 'https://gym.example/elsewhere' ) );
		$this->assertSame( '', $this->next( 'https://gym.example/api', 302, '' ) );
	}
}
