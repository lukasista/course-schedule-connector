<?php
/**
 * Stylesheet registration.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

use CSCS\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the one stylesheet, in one place.
 *
 * The listing is rendered from three directions — a shortcode, a block, and
 * later a builder module — and each of them would otherwise register the same
 * style with the same handle and add the same generated rule after it. Twice
 * registered is harmless; twice added is a duplicated media query in the page
 * source and the sort of thing that makes a stylesheet grow without anybody
 * deciding to.
 *
 * Registering is not enqueueing. The file reaches a page only where something
 * actually renders a listing.
 */
final class Assets {

	/**
	 * Style handle.
	 */
	public const HANDLE = 'cscs';

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
	 * Hooks the registration.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_style' ) );
	}

	/**
	 * Registers the stylesheet and the rule that depends on a setting.
	 *
	 * @return void
	 */
	public function register_style(): void {
		if ( wp_style_is( self::HANDLE, 'registered' ) ) {
			return;
		}

		wp_register_style( self::HANDLE, CSCS_URL . 'assets/css/cscs.css', array(), CSCS_VERSION );

		$breakpoint = $this->plugin->settings()->get_int( 'table_breakpoint', 320, 1600 );

		wp_add_inline_style( self::HANDLE, Renderer::responsive_css( $breakpoint ) );
	}
}
