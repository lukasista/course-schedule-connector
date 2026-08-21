<?php
/**
 * Hard ceiling on outbound requests.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Api;

use CSCS\Cache\Store;

defined( 'ABSPATH' ) || exit;

/**
 * Counts outbound requests and refuses to exceed a configured hourly ceiling.
 *
 * The remote system belongs to somebody else. A runaway loop or a misconfigured
 * schedule must not be able to hammer it, and the site owner must be able to
 * show exactly how many requests the plugin makes.
 */
final class RateLimiter {

	/**
	 * Cache key holding the current hour's counter.
	 */
	private const KEY = 'requests_this_hour';

	/**
	 * Cache backend.
	 *
	 * @var Store
	 */
	private Store $store;

	/**
	 * Maximum requests allowed in one hour.
	 *
	 * @var int
	 */
	private int $limit;

	/**
	 * Constructor.
	 *
	 * @param Store $store Cache backend.
	 * @param int   $limit Maximum requests per hour.
	 */
	public function __construct( Store $store, int $limit ) {
		$this->store = $store;
		$this->limit = max( 1, $limit );
	}

	/**
	 * Whether another request may be made right now.
	 *
	 * @return bool
	 */
	public function allows(): bool {
		return $this->count() < $this->limit;
	}

	/**
	 * Records that a request was made.
	 *
	 * @return void
	 */
	public function record(): void {
		$this->store->set_raw( self::KEY, $this->count() + 1, HOUR_IN_SECONDS );
	}

	/**
	 * Returns the number of requests made in the current window.
	 *
	 * @return int
	 */
	public function count(): int {
		$value = $this->store->get_raw( self::KEY );

		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * Returns the configured ceiling.
	 *
	 * @return int
	 */
	public function limit(): int {
		return $this->limit;
	}
}
