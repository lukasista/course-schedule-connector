<?php
/**
 * HTTP transport contract.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Api;

/**
 * Minimal transport interface.
 *
 * Exists so that the client can be exercised in tests without WordPress and
 * without touching the network.
 */
interface Http {

	/**
	 * Performs a GET request.
	 *
	 * @param string $url     Absolute URL.
	 * @param int    $timeout Timeout in seconds.
	 * @return Response
	 * @throws ApiException When the request cannot be completed at all.
	 */
	public function get( string $url, int $timeout ): Response;
}
