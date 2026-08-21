<?php
/**
 * API client for the iSport System.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Api;

use CSCS\Api\Dto\Course;
use CSCS\Api\Dto\Lesson;
use CSCS\Cache\Store;
use CSCS\Settings;
use CSCS\Support\Url;

defined( 'ABSPATH' ) || exit;

/**
 * Retrieves and decodes course and class data.
 *
 * Every guard the plugin promises lives here: the rate ceiling, the circuit
 * breaker, bounded retries, a hard timeout, and a cache that can serve stale
 * data. A caller gets typed records or an exception; it never gets a half-parsed
 * payload.
 *
 * A single request returns the complete list including capacity, so there is no
 * separate availability call to make. Freshness is a matter of how often this
 * runs, not of how many endpoints are hit.
 */
final class Client {

	/**
	 * Transport.
	 *
	 * @var Http
	 */
	private Http $http;

	/**
	 * Payload mapper.
	 *
	 * @var Mapper
	 */
	private Mapper $mapper;

	/**
	 * Cache backend.
	 *
	 * @var Store
	 */
	private Store $store;

	/**
	 * Request ceiling.
	 *
	 * @var RateLimiter
	 */
	private RateLimiter $limiter;

	/**
	 * Failure breaker.
	 *
	 * @var CircuitBreaker
	 */
	private CircuitBreaker $breaker;

	/**
	 * Plugin settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Http           $http     Transport.
	 * @param Mapper         $mapper   Payload mapper.
	 * @param Store          $store    Cache backend.
	 * @param RateLimiter    $limiter  Request ceiling.
	 * @param CircuitBreaker $breaker  Failure breaker.
	 * @param Settings       $settings Plugin settings.
	 */
	public function __construct(
		Http $http,
		Mapper $mapper,
		Store $store,
		RateLimiter $limiter,
		CircuitBreaker $breaker,
		Settings $settings
	) {
		$this->http     = $http;
		$this->mapper   = $mapper;
		$this->store    = $store;
		$this->limiter  = $limiter;
		$this->breaker  = $breaker;
		$this->settings = $settings;
	}

	/**
	 * Retrieves courses.
	 *
	 * @param string|null $date_from Optional Ymd date limiting the listing.
	 * @param bool        $force     Bypass the cache.
	 * @return array<int, Course> Courses keyed by course id.
	 * @throws ApiException When the data cannot be retrieved and nothing usable is cached.
	 */
	public function get_courses( ?string $date_from = null, bool $force = false ): array {
		$payload = $this->request(
			'courses.php',
			array( 'date' => $this->compact_date( $date_from ) ),
			$force
		);

		return $this->mapper->map_courses( $payload );
	}

	/**
	 * Retrieves class occurrences.
	 *
	 * @param string|null $date_from Optional Ymd start date.
	 * @param string|null $date_to   Optional Ymd end date.
	 * @param int|null    $id_tab    Optional tab id.
	 * @param int|null    $limit     Optional record limit.
	 * @param bool        $force     Bypass the cache.
	 * @return array<int, Lesson> Lessons keyed by term id.
	 * @throws ApiException When the data cannot be retrieved and nothing usable is cached.
	 */
	public function get_lessons(
		?string $date_from = null,
		?string $date_to = null,
		?int $id_tab = null,
		?int $limit = null,
		bool $force = false
	): array {
		$payload = $this->request(
			'activities.php',
			array(
				'date_from' => $this->compact_date( $date_from ),
				'date_to'   => $this->compact_date( $date_to ),
				'id_tab'    => null === $id_tab ? null : max( 1, $id_tab ),
				'limit'     => null === $limit ? null : max( 1, min( 5000, $limit ) ),
			),
			$force
		);

		return $this->mapper->map_lessons( $payload );
	}

	/**
	 * Returns the number of requests made in the current hour.
	 *
	 * @return int
	 */
	public function requests_this_hour(): int {
		return $this->limiter->count();
	}

	/**
	 * Returns the failure breaker, for reporting on the overview screen.
	 *
	 * @return CircuitBreaker
	 */
	public function breaker(): CircuitBreaker {
		return $this->breaker;
	}

	/**
	 * Performs a cached, guarded request and returns the decoded payload.
	 *
	 * @param string               $endpoint Endpoint file name.
	 * @param array<string, mixed> $query    Query parameters.
	 * @param bool                 $force    Bypass the cache.
	 * @return mixed Decoded payload.
	 * @throws ApiException When the request cannot be made or completed.
	 */
	private function request( string $endpoint, array $query, bool $force ) {
		$base = $this->settings->api_base_url();

		if ( '' === $base ) {
			throw new ApiException( 'No API base URL is configured.', 'not_configured' );
		}

		$url       = Url::endpoint( $base, $endpoint, $query );
		$cache_key = 'api_' . md5( $url );
		$cached    = $force ? null : $this->store->get( $cache_key );

		if ( null !== $cached && false === $cached['is_stale'] ) {
			return $cached['data'];
		}

		try {
			$payload = $this->fetch( $url );
		} catch ( ApiException $e ) {
			// Serving data that is merely old beats serving nothing at all.
			if ( null !== $cached ) {
				return $cached['data'];
			}

			throw $e;
		}

		$this->store->set(
			$cache_key,
			$payload,
			$this->settings->get_int( 'cache_fresh_seconds', 30, 3600 ),
			$this->settings->get_int( 'cache_stale_seconds', 300, 604800 )
		);

		return $payload;
	}

	/**
	 * Performs the network request itself, with retries and guards.
	 *
	 * @param string $url Absolute URL.
	 * @return mixed Decoded payload.
	 * @throws ApiException When the request is refused, fails, or returns something undecodable.
	 */
	private function fetch( string $url ) {
		if ( ! $this->breaker->is_closed() ) {
			throw new ApiException( 'Requests are paused after repeated failures.', 'circuit_open' );
		}

		if ( ! $this->limiter->allows() ) {
			throw new ApiException( 'The hourly request ceiling has been reached.', 'rate_limited' );
		}

		$timeout  = $this->settings->get_int( 'request_timeout', 3, 30 );
		$attempts = $this->settings->get_int( 'request_retries', 0, 5 ) + 1;
		$last     = null;

		for ( $attempt = 1; $attempt <= $attempts; $attempt++ ) {
			try {
				$this->limiter->record();

				$response = $this->http->get( $url, $timeout );
				$payload  = $this->decode( $response );

				$this->breaker->record_success();

				return $payload;
			} catch ( ApiException $e ) {
				$last = $e;

				// A malformed payload will be just as malformed on a retry.
				if ( in_array( $e->get_reason(), array( 'bad_json', 'bad_shape', 'blocked_url' ), true ) ) {
					break;
				}

				if ( $attempt < $attempts ) {
					usleep( 250000 * $attempt );
				}
			}
		}

		$this->breaker->record_failure();

		throw $last instanceof ApiException ? $last : new ApiException( 'The request failed.', 'unknown' );
	}

	/**
	 * Validates and decodes a response.
	 *
	 * @param Response $response Raw response.
	 * @return mixed Decoded payload.
	 * @throws ApiException When the status, content type or body is unusable.
	 */
	private function decode( Response $response ) {
		if ( 200 !== $response->status ) {
			$status = absint( $response->status );

			throw new ApiException(
				esc_html( sprintf( 'The API answered with status %d.', $status ) ),
				'bad_status',
				$status // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- An integer status code passed as the exception code, never output.
			);
		}

		if ( '' !== $response->content_type && ! str_contains( $response->content_type, 'json' ) ) {
			throw new ApiException( 'The API answered with an unexpected content type.', 'bad_content_type' );
		}

		$body = trim( $response->body );

		if ( '' === $body ) {
			throw new ApiException( 'The API returned an empty body.', 'empty_body' );
		}

		$decoded = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			throw new ApiException( esc_html( 'The API response could not be decoded: ' . json_last_error_msg() ), 'bad_json' );
		}

		if ( ! is_array( $decoded ) ) {
			throw new ApiException( 'The API response was not a list of records.', 'bad_shape' );
		}

		return $decoded;
	}

	/**
	 * Validates a Ymd date parameter.
	 *
	 * @param string|null $date Candidate date.
	 * @return string|null Null when absent or invalid, so the parameter is simply omitted.
	 */
	private function compact_date( ?string $date ): ?string {
		if ( null === $date || '' === $date ) {
			return null;
		}

		if ( 1 !== preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $date, $matches ) ) {
			return null;
		}

		if ( ! checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
			return null;
		}

		return $date;
	}
}
