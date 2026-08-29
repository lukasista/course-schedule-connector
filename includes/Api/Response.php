<?php
/**
 * Transport-agnostic HTTP response.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Api;

defined( 'ABSPATH' ) || exit;

/**
 * The parts of an HTTP response this plugin cares about.
 */
final class Response {

	/**
	 * Constructor.
	 *
	 * @param int    $status       HTTP status code.
	 * @param string $body         Response body.
	 * @param string $content_type Content-Type header, lowercased, may be empty.
	 */
	public function __construct(
		public readonly int $status,
		public readonly string $body,
		public readonly string $content_type = ''
	) {}
}
