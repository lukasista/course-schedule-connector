<?php
/**
 * Roles and capabilities.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Defines who may change what.
 *
 * The plugin splits its own surface in two. Content — which courses appear,
 * what they say, how a listing is configured — belongs to whoever runs the
 * site day to day. Design and anything that decides where requests go belongs
 * to an administrator.
 *
 * The split is enforced on save, not by hiding fields. A hidden field is a
 * courtesy; a capability check is the rule.
 */
final class Capabilities {

	/**
	 * Role given to whoever maintains the course listings.
	 */
	public const ROLE = 'cscs_manager';

	/**
	 * Editing course content, display sets, texts and manual assignments.
	 */
	public const MANAGE_CONTENT = 'cscs_manage_content';

	/**
	 * Design controls, plugin settings and anything that reaches the network.
	 */
	public const MANAGE_DESIGN = 'cscs_manage_design';

	/**
	 * Creates the role and grants the capabilities.
	 *
	 * Called on activation. Existing roles keep everything else they have.
	 *
	 * @return void
	 */
	public static function install(): void {
		add_role(
			self::ROLE,
			__( 'iSport manager', 'course-schedule-connector' ),
			array(
				'read'               => true,
				'upload_files'       => true,
				self::MANAGE_CONTENT => true,
			)
		);

		$administrator = get_role( 'administrator' );

		if ( $administrator instanceof \WP_Role ) {
			$administrator->add_cap( self::MANAGE_CONTENT );
			$administrator->add_cap( self::MANAGE_DESIGN );
		}

		$editor = get_role( 'editor' );

		if ( $editor instanceof \WP_Role ) {
			$editor->add_cap( self::MANAGE_CONTENT );
		}
	}

	/**
	 * Removes the role and the capabilities. Only called from uninstall.
	 *
	 * @return void
	 */
	public static function remove(): void {
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );

			if ( $role instanceof \WP_Role ) {
				$role->remove_cap( self::MANAGE_CONTENT );
				$role->remove_cap( self::MANAGE_DESIGN );
			}
		}

		remove_role( self::ROLE );
	}

	/**
	 * Whether the current user may edit content.
	 *
	 * @return bool
	 */
	public static function can_manage_content(): bool {
		return current_user_can( self::MANAGE_CONTENT );
	}

	/**
	 * Whether the current user may change design and settings.
	 *
	 * @return bool
	 */
	public static function can_manage_design(): bool {
		return current_user_can( self::MANAGE_DESIGN );
	}
}
