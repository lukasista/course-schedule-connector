<?php
/**
 * Tests for base URL validation.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Support\Url;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the base URL guard, including the addresses it must refuse.
 *
 * @covers \CSCS\Support\Url
 */
final class UrlTest extends TestCase {

	/**
	 * A well-formed public https URL is accepted and normalised.
	 *
	 * @return void
	 */
	public function test_public_https_url_is_accepted(): void {
		$this->assertSame(
			'https://jojogym.isportsystem.cz',
			Url::validate_base( 'https://jojogym.isportsystem.cz/' )
		);
	}

	/**
	 * Addresses that could be used to reach inside the network are refused.
	 *
	 * @dataProvider provide_forbidden_urls
	 *
	 * @param string $url Candidate URL.
	 * @return void
	 */
	public function test_internal_targets_are_refused( string $url ): void {
		$this->expectException( \InvalidArgumentException::class );

		Url::validate_base( $url );
	}

	/**
	 * URLs that must never be accepted.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function provide_forbidden_urls(): array {
		return array(
			'loopback'        => array( 'https://127.0.0.1' ),
			'ipv6 loopback'   => array( 'https://[::1]' ),
			'private range'   => array( 'https://192.168.1.10' ),
			'private range b' => array( 'https://10.0.0.5' ),
			'link local'      => array( 'https://169.254.169.254' ),
			'localhost'       => array( 'https://localhost' ),
			'mdns'            => array( 'https://printer.local' ),
			'credentials'     => array( 'https://user:pass@example.com' ),
			'odd port'        => array( 'https://example.com:8443' ),
			'plain http'      => array( 'http://example.com' ),
			'no scheme'       => array( 'example.com' ),
			'empty'           => array( '' ),
		);
	}

	/**
	 * Plain http is available only when explicitly permitted for local work.
	 *
	 * @return void
	 */
	public function test_http_is_only_allowed_when_asked_for(): void {
		$this->assertSame( 'http://example.com', Url::validate_base( 'http://example.com', true ) );
	}

	/**
	 * Empty query parameters are dropped rather than sent as blanks.
	 *
	 * @return void
	 */
	public function test_endpoint_drops_empty_parameters(): void {
		$this->assertSame(
			'https://example.com/api/activities.php?date_from=20260911&limit=3',
			Url::endpoint(
				'https://example.com',
				'activities.php',
				array(
					'date_from' => '20260911',
					'date_to'   => null,
					'id_tab'    => '',
					'limit'     => 3,
				)
			)
		);
	}
}
