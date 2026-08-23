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
		// The listing route is public because a listing is public: it renders
		// what any visitor sees on the page it sits on, and it exists so that
		// pressing "next week" need not reload everything around it.
		register_rest_route(
			self::NAMESPACE,
			'/listing',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'listing' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'set'  => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
					'page' => array( 'type' => 'integer' ),
					'week' => array( 'type' => 'integer' ),
					'room' => array( 'type' => 'integer' ),
					'url'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

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

		register_rest_route(
			self::NAMESPACE,
			'/field',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'field' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'name'     => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
					'settings' => array( 'type' => 'string' ),
				),
			)
		);
	}

	/**
	 * Renders one field, for a builder that cannot draw it itself.
	 *
	 * The settings arrive as JSON in one parameter rather than as a parameter
	 * each, because the set of them belongs to the renderer and a route that
	 * listed them would be a second place to change every time one is added.
	 * Nothing is trusted: the renderer checks every value it is handed, exactly
	 * as it does for a value that arrived from a saved post.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function field( \WP_REST_Request $request ): \WP_REST_Response {
		$name  = sanitize_key( (string) $request->get_param( 'name' ) );
		$field = Fields::get( $name );

		if ( null === $field ) {
			return new \WP_REST_Response(
				array(
					'found' => false,
					'html'  => '',
				),
				404
			);
		}

		$settings = json_decode( (string) $request->get_param( 'settings' ), true );
		$settings = is_array( $settings ) ? $settings : array();

		return new \WP_REST_Response(
			array(
				'found' => true,
				'html'  => FieldRenderer::render( $this->plugin, $name, $settings ),
			)
		);
	}

	/**
	 * Renders a listing again, for a visitor who changed week, room or page.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function listing( \WP_REST_Request $request ): \WP_REST_Response {
		$id  = sanitize_key( (string) $request->get_param( 'set' ) );
		$set = $this->plugin->sets()->find( $id );

		if ( null === $set ) {
			return new \WP_REST_Response(
				array(
					'found' => false,
					'html'  => '',
				),
				404
			);
		}

		$args = ListingArgs::from_array(
			array(
				'page' => $request->get_param( 'page' ),
				'week' => $request->get_param( 'week' ),
				'room' => $request->get_param( 'room' ),
			)
		);

		// The address the listing sits on decides where its own links point.
		// It is taken from the request and only used to build links, so the
		// worst a made-up one can do is send its author somewhere odd.
		$base = (string) $request->get_param( 'url' );

		return new \WP_REST_Response(
			array(
				'found' => true,
				'html'  => $this->plugin->renderer()->render( $set, $args, $base ),
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
