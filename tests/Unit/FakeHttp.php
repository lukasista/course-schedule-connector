<?php
/**
 * Scripted HTTP transport for tests.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Api\ApiException;
use CSCS\Api\Http;
use CSCS\Api\Response;

/**
 * Returns queued responses, or throws queued exceptions, and counts calls.
 */
final class FakeHttp implements Http {

	/**
	 * Queued outcomes.
	 *
	 * @var array<int, Response|ApiException>
	 */
	private array $queue;

	/**
	 * Number of calls received.
	 *
	 * @var int
	 */
	public int $calls = 0;

	/**
	 * Constructor.
	 *
	 * @param array<int, Response|ApiException> $queue Outcomes to return in order.
	 */
	public function __construct( array $queue ) {
		$this->queue = $queue;
	}

	/**
	 * Returns the next queued outcome.
	 *
	 * @param string $url     URL.
	 * @param int    $timeout Timeout.
	 * @return Response
	 * @throws ApiException When the queued outcome is an exception.
	 */
	public function get( string $url, int $timeout ): Response {
		++$this->calls;

		$outcome = array_shift( $this->queue );

		if ( $outcome instanceof ApiException ) {
			throw $outcome;
		}

		if ( $outcome instanceof Response ) {
			return $outcome;
		}

		throw new ApiException( 'The fake transport ran out of queued responses.', 'transport' );
	}
}
