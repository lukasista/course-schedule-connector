<?php
/**
 * Preview endpoint.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

use CSCS\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Lets a builder ask the server what a listing looks like.
 *
 * Divi's Visual Builder draws a module in React, which for most modules means
 * the markup exists twice — once in PHP for the page and once in JavaScript for
 * the builder — and the two drift. A listing is not worth that: it has one
 * definition, in PHP, and the builder asks for it.
 *
 * The route returns nothing a person could not already see. It is still limited
 * to somebody who may edit posts, because it is a builder's convenience and not
 * a public interface, and because a listing rendered here does not carry the
 * page's own caching.
 */
final class RestPreview {

	/**
	 * Namespace.
	 */
	public const NAMESPACE = 'cscs/v1';

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Hooks the route.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/**
	 * Registers the route.
	 *
	 * @return void
	 */
	public function register_route(): void {
		register_rest_route(
			self::NAMESPACE,
			'/preview',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'set' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * Renders one display set.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function preview( \WP_REST_Request $request ): \WP_REST_Response {
		$id = sanitize_key( (string) $request->get_param( 'set' ) );
		$set = $this->plugin->sets()->find( $id );

		return new \WP_REST_Response(
			array(
				'set'   => $id,
				'found' => null !== $set,
				'html'  => null === $set ? '' : $this->plugin->renderer()->render( $set ),
			)
		);
	}
}
