<?php
/**
 * WordPress HTTP transport.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Transport backed by the WordPress HTTP API.
 *
 * Certificate verification is left at its default of true. The example in the
 * iSport API documentation disables host verification; that is deliberately not
 * followed, because it would make the connection trivially interceptable.
 */
final class WpHttp implements Http {

	/**
	 * Maximum accepted response size in bytes.
	 *
	 * A response larger than this is refused rather than decoded, so a
	 * misbehaving or hostile endpoint cannot exhaust memory.
	 */
	private const MAX_BYTES = 8388608;

	/**
	 * Performs a GET request through wp_remote_get().
	 *
	 * @param string $url     Absolute URL.
	 * @param int    $timeout Timeout in seconds.
	 * @return Response
	 * @throws ApiException When the request fails or the response is unusable.
	 */
	public function get( string $url, int $timeout ): Response {
		$validated = wp_http_validate_url( $url );

		if ( false === $validated ) {
			throw new ApiException( 'The request URL was rejected by WordPress.', 'blocked_url' );
		}

		/**
		 * Filters the arguments passed to wp_remote_get() for every API request.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, mixed> $args Request arguments.
		 * @param string               $url  Request URL.
		 */
		$args = apply_filters(
			'cscs_api_request_args',
			array(
				'timeout'             => $timeout,
				'redirection'         => 2,
				'httpversion'         => '1.1',
				'user-agent'          => 'CourseScheduleConnector/' . CSCS_VERSION . '; ' . home_url( '/' ),
				'headers'             => array( 'Accept' => 'application/json' ),
				'limit_response_size' => self::MAX_BYTES,
				'reject_unsafe_urls'  => true,
			),
			$url
		);

		$response = wp_remote_get( $validated, $args );

		if ( is_wp_error( $response ) ) {
			$reason = str_contains( $response->get_error_message(), 'timed out' ) ? 'timeout' : 'transport';

			throw new ApiException( $response->get_error_message(), $reason );
		}

		$body = (string) wp_remote_retrieve_body( $response );

		if ( strlen( $body ) >= self::MAX_BYTES ) {
			throw new ApiException( 'The response exceeded the accepted size.', 'too_large' );
		}

		return new Response(
			(int) wp_remote_retrieve_response_code( $response ),
			$body,
			strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) )
		);
	}
}
