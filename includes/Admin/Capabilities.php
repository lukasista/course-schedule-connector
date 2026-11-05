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
	 * Roles MANAGE_CONTENT always belongs to. Settings can add more roles on
	 * top of these (see sync_extra_roles()), but can never take it away from
	 * one of these three, and never grants MANAGE_DESIGN to anyone else.
	 *
	 * @var array<int, string>
	 */
	private const BASELINE_ROLES = array( 'administrator', 'editor', self::ROLE );

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
	 * stored version is current, the same way the schema check does. Note that
	 * this guards only the fixed baseline below — the roles Settings has
	 * chosen are reconciled every time they are saved, not gated by VERSION.
	 *
	 * @param array<int, string> $extra_roles Role slugs Settings currently
	 *                                        says should also manage content.
	 * @return void
	 */
	public static function ensure( array $extra_roles = array() ): void {
		if ( self::VERSION === (int) get_option( self::VERSION_OPTION, 0 ) ) {
			return;
		}

		self::install( $extra_roles );
	}

	/**
	 * Creates the role, grants the fixed baseline, and reconciles whichever
	 * extra roles Settings currently names.
	 *
	 * Safe to run repeatedly: an existing role keeps everything else it has,
	 * and granting a capability twice grants it once.
	 *
	 * @param array<int, string> $extra_roles Role slugs Settings currently
	 *                                        says should also manage content.
	 * @return void
	 */
	public static function install( array $extra_roles = array() ): void {
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

		self::sync_extra_roles( $extra_roles );

		update_option( self::VERSION_OPTION, self::VERSION, true );
	}

	/**
	 * Grants or revokes MANAGE_CONTENT on every role beyond the fixed
	 * baseline, to match exactly what Settings currently says.
	 *
	 * Administrator, Editor and the iSport manager role are never touched
	 * here — they are set in install() above and stay outside this list's
	 * reach. Every other role on the site is reconciled against the given
	 * list: named in it, the role is granted the capability; not named in
	 * it, the role has the capability removed, whether this call granted it
	 * earlier or something else did. Settings is this plugin's single
	 * source of truth for who, beyond the baseline, may manage content, so
	 * calling this with an empty list deliberately strips every extra grant.
	 *
	 * @param array<int, string> $role_slugs Role slugs that should manage
	 *                                        content, in addition to the
	 *                                        fixed baseline above.
	 * @return void
	 */
	public static function sync_extra_roles( array $role_slugs ): void {
		foreach ( wp_roles()->roles as $slug => $definition ) {
			if ( in_array( $slug, self::BASELINE_ROLES, true ) ) {
				continue;
			}

			$role = get_role( $slug );

			if ( ! ( $role instanceof \WP_Role ) ) {
				continue;
			}

			if ( in_array( $slug, $role_slugs, true ) ) {
				$role->add_cap( self::MANAGE_CONTENT );
			} else {
				$role->remove_cap( self::MANAGE_CONTENT );
			}
		}
	}

	/**
	 * Removes the role and the capabilities. Only called from uninstall.
	 *
	 * @return void
	 */
	public static function remove(): void {
		self::sync_extra_roles( array() );

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
