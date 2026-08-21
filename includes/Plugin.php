<?php
/**
 * Plugin bootstrap and service container.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS;

use CSCS\Api\CircuitBreaker;
use CSCS\Api\Client;
use CSCS\Api\Mapper;
use CSCS\Api\RateLimiter;
use CSCS\Api\WpHttp;
use CSCS\Cache\Store;
use CSCS\Cli\ApiCommand;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin together.
 *
 * Deliberately small: it builds services on demand and registers integrations.
 * Nothing here reaches out to the network, so activating the plugin never blocks
 * on a remote system.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Built services, keyed by identifier.
	 *
	 * @var array<string, object>
	 */
	private array $services = array();

	/**
	 * Boots the plugin.
	 *
	 * @return void
	 */
	public static function boot(): void {
		if ( version_compare( PHP_VERSION, CSCS_MIN_PHP, '<' ) ) {
			add_action( 'admin_notices', array( self::class, 'render_php_notice' ) );

			return;
		}

		self::instance()->register();
	}

	/**
	 * Returns the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers integrations.
	 *
	 * @return void
	 */
	private function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			ApiCommand::register( $this );
		}

		/**
		 * Fires once the plugin has booted and its services are available.
		 *
		 * @since 0.1.0
		 *
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'cscs_booted', $this );
	}

	/**
	 * Returns the settings service.
	 *
	 * @return Settings
	 */
	public function settings(): Settings {
		return $this->service( 'settings', static fn(): Settings => new Settings() );
	}

	/**
	 * Returns the cache store.
	 *
	 * @return Store
	 */
	public function store(): Store {
		return $this->service( 'store', static fn(): Store => new Store() );
	}

	/**
	 * Returns the API client.
	 *
	 * @return Client
	 */
	public function client(): Client {
		return $this->service(
			'client',
			function (): Client {
				$settings = $this->settings();
				$store    = $this->store();

				return new Client(
					new WpHttp(),
					new Mapper(),
					$store,
					new RateLimiter( $store, $settings->get_int( 'request_cap_per_hour', 1, 3600 ) ),
					new CircuitBreaker(
						$store,
						$settings->get_int( 'breaker_threshold', 1, 20 ),
						$settings->get_int( 'breaker_cooldown', 60, 86400 )
					),
					$settings
				);
			}
		);
	}

	/**
	 * Returns a service, building it on first use.
	 *
	 * @param string   $id      Service identifier.
	 * @param callable $factory Factory returning the service.
	 * @return object
	 */
	private function service( string $id, callable $factory ): object {
		if ( ! isset( $this->services[ $id ] ) ) {
			$this->services[ $id ] = $factory();
		}

		return $this->services[ $id ];
	}

	/**
	 * Renders the notice shown when the PHP version is too old.
	 *
	 * @return void
	 */
	public static function render_php_notice(): void {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: required PHP version, 2: current PHP version */
					__( 'Course & Schedule Connector for iSport requires PHP %1$s or newer. This site runs PHP %2$s.', 'course-schedule-connector' ),
					CSCS_MIN_PHP,
					PHP_VERSION
				)
			)
		);
	}
}
