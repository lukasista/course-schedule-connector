<?php
/**
 * Validation of the administrator-supplied API base URL.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Support;

/**
 * Validates and normalises the base URL of an iSport System installation.
 *
 * The base URL is the only value that decides where the plugin sends a request,
 * and it is supplied by a human through an admin field. It is therefore treated
 * as a server-side request forgery vector: an attacker who reached the settings
 * screen must not be able to point the plugin at an internal address and use the
 * site as a proxy into the private network.
 *
 * This class contains no WordPress calls so that it can be unit-tested directly.
 * The caller is expected to additionally pass the result through
 * wp_http_validate_url(), which honours the site's own external-request policy.
 */
final class Url {

	/**
	 * Ports the plugin is willing to talk to.
	 */
	private const ALLOWED_PORTS = array( 80, 443 );

	/**
	 * Validates a base URL and returns it without a trailing slash.
	 *
	 * @param string $url        Candidate base URL.
	 * @param bool   $allow_http Whether plain http is acceptable. Intended for local development only.
	 * @return string Normalised base URL.
	 * @throws \InvalidArgumentException When the URL is unusable or points somewhere it must not.
	 */
	public static function validate_base( string $url, bool $allow_http = false ): string {
		$url = trim( $url );

		if ( '' === $url ) {
			throw new \InvalidArgumentException( 'The API base URL is empty.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- This class stays free of WordPress so it can be unit-tested in isolation.
		$parts = parse_url( $url );

		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) ) {
			throw new \InvalidArgumentException( 'The API base URL is not a complete URL.' );
		}

		$scheme = strtolower( $parts['scheme'] );

		if ( 'https' !== $scheme && ! ( $allow_http && 'http' === $scheme ) ) {
			throw new \InvalidArgumentException( 'The API base URL must use https.' );
		}

		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			throw new \InvalidArgumentException( 'The API base URL must not contain credentials.' );
		}

		if ( isset( $parts['port'] ) && ! in_array( (int) $parts['port'], self::ALLOWED_PORTS, true ) ) {
			throw new \InvalidArgumentException( 'The API base URL must use the standard http or https port.' );
		}

		$host = strtolower( $parts['host'] );

		if ( ! self::is_acceptable_host( $host ) ) {
			throw new \InvalidArgumentException( 'The API base URL points at a private or reserved address.' );
		}

		$path = isset( $parts['path'] ) ? rtrim( $parts['path'], '/' ) : '';

		return $scheme . '://' . $host . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' ) . $path;
	}

	/**
	 * Decides whether a host may be contacted.
	 *
	 * Rejects literal addresses in private, loopback, link-local and other
	 * reserved ranges, and rejects hostnames that cannot be public.
	 *
	 * @param string $host Lowercase hostname or IP literal.
	 * @return bool
	 */
	public static function is_acceptable_host( string $host ): bool {
		$host = trim( $host, '[]' );

		if ( '' === $host ) {
			return false;
		}

		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return self::is_public_ip( $host );
		}

		if ( 'localhost' === $host || str_ends_with( $host, '.localhost' ) ) {
			return false;
		}

		// Reserved special-use names that can never resolve to a public host.
		foreach ( array( '.local', '.internal', '.intranet', '.private', '.home.arpa', '.test', '.invalid' ) as $suffix ) {
			if ( str_ends_with( $host, $suffix ) ) {
				return false;
			}
		}

		return 1 === preg_match( '/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host );
	}

	/**
	 * Decides whether an IP literal is in publicly routable space.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	public static function is_public_ip( string $ip ): bool {
		return false !== filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	}

	/**
	 * Builds an endpoint URL from a validated base and a query.
	 *
	 * @param string               $base     Validated base URL.
	 * @param string               $endpoint Endpoint path, for example "courses.php".
	 * @param array<string, mixed> $query    Query parameters. Null values are dropped.
	 * @return string
	 */
	public static function endpoint( string $base, string $endpoint, array $query = array() ): string {
		$url = rtrim( $base, '/' ) . '/api/' . ltrim( $endpoint, '/' );

		$query = array_filter(
			$query,
			static fn( $value ): bool => null !== $value && '' !== $value
		);

		if ( array() === $query ) {
			return $url;
		}

		return $url . '?' . http_build_query( $query );
	}
}
