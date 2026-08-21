<?php
/**
 * Cache with stale-while-revalidate semantics.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * A small wrapper over the transient API that can serve stale data.
 *
 * A visitor must never wait for the remote system. When an entry is past its
 * freshness window but still inside its stale window, the stored value is
 * returned immediately and the caller is told that a refresh is due, so the
 * refresh can happen on a scheduled run rather than in the request.
 */
final class Store {

	/**
	 * Prefix for every transient this plugin writes.
	 */
	private const PREFIX = 'cscs_';

	/**
	 * Reads an entry.
	 *
	 * @param string $key Cache key without the plugin prefix.
	 * @return array{data: mixed, is_stale: bool}|null Null when nothing usable is stored.
	 */
	public function get( string $key ) {
		$entry = get_transient( self::PREFIX . $key );

		if ( ! is_array( $entry ) || ! array_key_exists( 'data', $entry ) || ! isset( $entry['fresh_until'] ) ) {
			return null;
		}

		return array(
			'data'     => $entry['data'],
			'is_stale' => time() > (int) $entry['fresh_until'],
		);
	}

	/**
	 * Writes an entry.
	 *
	 * @param string $key      Cache key without the plugin prefix.
	 * @param mixed  $data     Value to store.
	 * @param int    $fresh_ttl Seconds for which the value is considered fresh.
	 * @param int    $stale_ttl Additional seconds for which a stale value may still be served.
	 * @return void
	 */
	public function set( string $key, $data, int $fresh_ttl, int $stale_ttl ): void {
		set_transient(
			self::PREFIX . $key,
			array(
				'data'        => $data,
				'fresh_until' => time() + max( 0, $fresh_ttl ),
				'stored_at'   => time(),
			),
			max( 60, $fresh_ttl + $stale_ttl )
		);
	}

	/**
	 * Deletes an entry.
	 *
	 * @param string $key Cache key without the plugin prefix.
	 * @return void
	 */
	public function delete( string $key ): void {
		delete_transient( self::PREFIX . $key );
	}

	/**
	 * Reads a raw counter or state value.
	 *
	 * @param string $key Key without the plugin prefix.
	 * @return mixed Stored value, or false when absent.
	 */
	public function get_raw( string $key ) {
		return get_transient( self::PREFIX . $key );
	}

	/**
	 * Writes a raw counter or state value.
	 *
	 * @param string $key        Key without the plugin prefix.
	 * @param mixed  $value      Value to store.
	 * @param int    $expires_in Lifetime in seconds.
	 * @return void
	 */
	public function set_raw( string $key, $value, int $expires_in ): void {
		set_transient( self::PREFIX . $key, $value, max( 1, $expires_in ) );
	}
}
