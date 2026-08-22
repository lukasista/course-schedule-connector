<?php
/**
 * The editor block.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

use CSCS\Data\DisplaySet;
use CSCS\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * One block, one field: which display set.
 *
 * The block renders on the server through the same renderer as everything else,
 * which buys two things at once. The editor's preview is the page — not a
 * drawing of it that drifts out of date — and the plugin does not become
 * unusable the day somebody switches away from a page builder, which is what
 * WordPress.org means when it says a plugin may not require a commercial theme.
 *
 * The editor asks the block-renderer endpoint that WordPress already has, so
 * there is no route of the plugin's own to secure. Rendering is a read, and it
 * shows a logged-in editor exactly what a visitor would see.
 */
final class Block {

	/**
	 * Block name.
	 */
	public const NAME = 'cscs/display';

	/**
	 * Editor script handle.
	 */
	private const SCRIPT = 'cscs-block-editor';

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
		// Late on init: the block's own directory is read here, and the sets it
		// offers are read from an option, both of which want the plugin to have
		// finished booting.
		add_action( 'init', array( $this, 'register_block' ), 20 );
	}

	/**
	 * Registers the block and the script that edits it.
	 *
	 * @return void
	 */
	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			self::SCRIPT,
			CSCS_URL . 'blocks/display/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-server-side-render' ),
			CSCS_VERSION,
			true
		);

		wp_set_script_translations( self::SCRIPT, 'course-schedule-connector', CSCS_DIR . 'languages' );

		wp_localize_script( self::SCRIPT, 'cscsBlockSets', $this->choices() );

		register_block_type(
			CSCS_DIR . 'blocks/display',
			array( 'render_callback' => array( $this, 'render' ) )
		);
	}

	/**
	 * Renders the block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render( array $attributes ): string {
		$id = sanitize_key( (string) ( $attributes['set'] ?? '' ) );

		if ( '' === $id ) {
			return current_user_can( 'edit_posts' )
				? '<p class="cscs-notice">' . esc_html__( 'Choose a display set in the block settings.', 'course-schedule-connector' ) . '</p>'
				: '';
		}

		return $this->plugin->renderer()->render_id( $id );
	}

	/**
	 * Returns the sets the editor may choose from.
	 *
	 * The list is handed to the script rather than fetched, because it is a
	 * handful of names that are already in memory: a request to find out what
	 * this site calls its listings would be a request for nothing.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private function choices(): array {
		$choices = array(
			array(
				'value' => '',
				'label' => __( '— choose a set —', 'course-schedule-connector' ),
			),
		);

		foreach ( $this->plugin->sets()->all() as $set ) {
			$choices[] = array(
				'value' => $set->id,
				'label' => sprintf(
					'%s (%s)',
					$set->name,
					DisplaySet::TYPE_SCHEDULE === $set->type
						? __( 'classes', 'course-schedule-connector' )
						: __( 'courses', 'course-schedule-connector' )
				),
			);
		}

		return $choices;
	}
}
