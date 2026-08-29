<?php
/**
 * Failure circuit breaker.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Api;

defined( 'ABSPATH' ) || exit;

use CSCS\Cache\Store;

/**
 * Stops calling a remote system that keeps failing.
 *
 * After a threshold of consecutive failures the circuit opens for a cool-down
 * period, during which no request is attempted at all. This protects the remote
 * host from a site that would otherwise retry every fifteen minutes forever, and
 * it gives the administrator a single, meaningful notification instead of a
 * stream of identical errors.
 */
final class CircuitBreaker {

	/**
	 * Cache key holding the consecutive failure count.
	 */
	private const FAILURES_KEY = 'breaker_failures';

	/**
	 * Cache key holding the timestamp until which the circuit stays open.
	 */
	private const OPEN_KEY = 'breaker_open_until';

	/**
	 * Cache backend.
	 *
	 * @var Store
	 */
	private Store $store;

	/**
	 * Consecutive failures required to open the circuit.
	 *
	 * @var int
	 */
	private int $threshold;

	/**
	 * Cool-down in seconds.
	 *
	 * @var int
	 */
	private int $cooldown;

	/**
	 * Constructor.
	 *
	 * @param Store $store     Cache backend.
	 * @param int   $threshold Consecutive failures required to open the circuit.
	 * @param int   $cooldown  Cool-down in seconds.
	 */
	public function __construct( Store $store, int $threshold = 3, int $cooldown = 1800 ) {
		$this->store     = $store;
		$this->threshold = max( 1, $threshold );
		$this->cooldown  = max( 60, $cooldown );
	}

	/**
	 * Whether a request may be attempted.
	 *
	 * @return bool
	 */
	public function is_closed(): bool {
		$open_until = $this->store->get_raw( self::OPEN_KEY );

		return ! is_numeric( $open_until ) || time() >= (int) $open_until;
	}

	/**
	 * Returns the timestamp at which the circuit will close again.
	 *
	 * @return int Zero when the circuit is already closed.
	 */
	public function open_until(): int {
		$open_until = $this->store->get_raw( self::OPEN_KEY );

		return is_numeric( $open_until ) ? (int) $open_until : 0;
	}

	/**
	 * Records a successful request and resets the failure count.
	 *
	 * @return void
	 */
	public function record_success(): void {
		$this->reset();
	}

	/**
	 * Closes the circuit and forgets the failure count.
	 *
	 * Called when something has changed that could plausibly fix the cause: a
	 * new base URL, an updated timeout, or an administrator saying so directly.
	 * A breaker that keeps refusing after the fault has been repaired is just a
	 * thirty-minute punishment for fixing it.
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->store->delete( self::FAILURES_KEY );
		$this->store->delete( self::OPEN_KEY );
	}

	/**
	 * Records a failed request, opening the circuit once the threshold is reached.
	 *
	 * @return bool True when this failure opened the circuit.
	 */
	public function record_failure(): bool {
		$failures = $this->failures() + 1;

		$this->store->set_raw( self::FAILURES_KEY, $failures, DAY_IN_SECONDS );

		if ( $failures < $this->threshold ) {
			return false;
		}

		$this->store->set_raw( self::OPEN_KEY, time() + $this->cooldown, $this->cooldown );

		/**
		 * Fires when repeated failures have opened the circuit.
		 *
		 * @since 0.1.0
		 *
		 * @param int $failures Consecutive failure count.
		 * @param int $cooldown Cool-down in seconds.
		 */
		do_action( 'cscs_circuit_opened', $failures, $this->cooldown );

		return true;
	}

	/**
	 * Returns the current consecutive failure count.
	 *
	 * @return int
	 */
	public function failures(): int {
		$value = $this->store->get_raw( self::FAILURES_KEY );

		return is_numeric( $value ) ? (int) $value : 0;
	}
}
