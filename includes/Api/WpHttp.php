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
				// Not followed by WordPress. A redirect is followed here, once,
				// and only to the same scheme and host — the checklist says
				// requests go to the configured host and a redirect elsewhere
				// is refused, and WordPress following two hops for us would
				// have made that untrue without anything saying so. An open
				// redirect on the booking system would otherwise have been an
				// open redirect out of this site's server.
				'redirection'         => 0,
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

			throw new ApiException( esc_html( $response->get_error_message() ), esc_html( $reason ) );
		}

		$moved = self::redirect( $validated, $response );

		if ( '' !== $moved ) {
			$response = wp_remote_get( $moved, array_merge( $args, array( 'redirection' => 0 ) ) );

			if ( is_wp_error( $response ) ) {
				throw new ApiException( esc_html( $response->get_error_message() ), 'transport' );
			}
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

	/**
	 * Returns where a response says to go next, when that is the same host.
	 *
	 * A booking system that moves `/api` to `/api/` is answering the question
	 * it was asked; one that answers with somebody else's address is not, and
	 * following it would mean this site's server fetching whatever that address
	 * serves, with this site's network position.
	 *
	 * @param string              $from     The URL that was requested.
	 * @param array<string, mixed> $response Response as returned by wp_remote_get().
	 * @return string The URL to follow, or an empty string.
	 */
	private static function redirect( string $from, array $response ): string {
		if ( ! in_array( (int) wp_remote_retrieve_response_code( $response ), array( 301, 302, 303, 307, 308 ), true ) ) {
			return '';
		}

		$to = (string) wp_remote_retrieve_header( $response, 'location' );

		if ( '' === $to ) {
			return '';
		}

		// A relative Location is the same host by definition.
		$to = ( str_starts_with( $to, '/' ) && ! str_starts_with( $to, '//' ) )
			? untrailingslashit( (string) preg_replace( '#^(https?://[^/]+).*#i', '$1', $from ) ) . $to
			: $to;

		$here  = wp_parse_url( $from );
		$there = wp_parse_url( $to );

		if ( ! is_array( $here ) || ! is_array( $there ) ) {
			return '';
		}

		$same = ( $here['scheme'] ?? '' ) === ( $there['scheme'] ?? '' )
			&& strtolower( (string) ( $here['host'] ?? '' ) ) === strtolower( (string) ( $there['host'] ?? '' ) )
			&& ( $here['port'] ?? null ) === ( $there['port'] ?? null );

		return $same && false !== wp_http_validate_url( $to ) ? $to : '';
	}
}
