<?php
/**
 * API failure exception.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Raised when the remote system cannot be reached or answers unusably.
 *
 * Never allowed to escape to the front end: callers catch it, log it, and fall
 * back to the last known data.
 */
final class ApiException extends \RuntimeException {

	/**
	 * Machine-readable reason, used by the log and the circuit breaker.
	 *
	 * @var string
	 */
	private string $reason;

	/**
	 * Constructor.
	 *
	 * @param string          $message  Human-readable message.
	 * @param string          $reason   Machine-readable reason such as "timeout" or "bad_status".
	 * @param int             $code     HTTP status code where one is known, otherwise 0.
	 * @param \Throwable|null $previous Previous exception.
	 */
	public function __construct( string $message, string $reason = 'unknown', int $code = 0, ?\Throwable $previous = null ) {
		parent::__construct( $message, $code, $previous );

		$this->reason = $reason;
	}

	/**
	 * Returns the machine-readable reason.
	 *
	 * @return string
	 */
	public function get_reason(): string {
		return $this->reason;
	}
}
