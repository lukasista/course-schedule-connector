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
	 * Option recording which set of capabilities has been granted.
	 */
	public const VERSION_OPTION = 'cscs_capabilities_version';

	/**
	 * The set of capabilities this version of the plugin expects to exist.
	 *
	 * Raise it whenever a capability is added, or the sites already running the
	 * plugin will never be granted it.
	 */
	public const VERSION = 1;

	/**
	 * Grants the capabilities when they have not been granted yet.
	 *
	 * Activation is the obvious moment to do this and the wrong one to rely on:
	 * a plugin is activated once, and a capability added in a later version
	 * would then reach nobody who was already running it. This site learned
	 * that the hard way — the admin menu is hidden behind `cscs_manage_content`,
	 * and on an installation activated before that capability existed the whole
	 * menu simply was not there, with nothing anywhere to say why.
	 *
	 * The check is one autoloaded option read and returns immediately when the
	 * stored version is current, the same way the schema check does.
	 *
	 * @return void
	 */
	public static function ensure(): void {
		if ( self::VERSION === (int) get_option( self::VERSION_OPTION, 0 ) ) {
			return;
		}

		self::install();
	}

	/**
	 * Creates the role and grants the capabilities.
	 *
	 * Safe to run repeatedly: an existing role keeps everything else it has,
	 * and granting a capability twice grants it once.
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

		// A role created by an earlier version, before a capability existed,
		// would otherwise keep whatever it was created with for ever.
		$manager = get_role( self::ROLE );

		if ( $manager instanceof \WP_Role ) {
			$manager->add_cap( self::MANAGE_CONTENT );
		}

		update_option( self::VERSION_OPTION, self::VERSION, true );
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

		delete_option( self::VERSION_OPTION );
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
