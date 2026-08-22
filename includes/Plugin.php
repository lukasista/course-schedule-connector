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
use CSCS\Admin\Capabilities;
use CSCS\Admin\Menu;
use CSCS\Cache\Store;
use CSCS\Cli\ApiCommand;
use CSCS\Cli\SettingsCommand;
use CSCS\Cli\SyncCommand;
use CSCS\Data\CourseRepository;
use CSCS\Data\LessonRepository;
use CSCS\Data\PostType;
use CSCS\Data\Schema;
use CSCS\Sync\Logger;
use CSCS\Sync\Matcher;
use CSCS\Sync\Retention;
use CSCS\Sync\Scheduler;
use CSCS\Sync\Synchroniser;

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
	 * The minimum PHP version is declared in the plugin header and enforced by
	 * WordPress itself, which refuses to activate a plugin the server cannot
	 * run and explains why. A second check here would only duplicate that, so
	 * there is deliberately none.
	 *
	 * @return void
	 */
	public static function boot(): void {
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
		// A schema change has to apply without the site owner having to guess
		// that deactivating and reactivating is what makes a fix take effect.
		// The check is one autoloaded option read and returns immediately when
		// the stored version is current.
		Schema::install();

		add_action( 'init', array( PostType::class, 'register' ) );

		if ( is_admin() ) {
			( new Menu( $this ) )->register();
		}
		add_action( 'cscs_setting_changed', array( $this, 'on_setting_changed' ) );

		$this->scheduler()->register();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			ApiCommand::register( $this );
			SyncCommand::register( $this );
			SettingsCommand::register( $this );
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
	 * Clears the failure state when a setting that could have caused it changes.
	 *
	 * Nothing is more discouraging than correcting a bad base URL and being told
	 * for the next half hour that requests are paused.
	 *
	 * @param string $key Setting that changed.
	 * @return void
	 */
	public function on_setting_changed( string $key ): void {
		$affects_requests = array(
			'api_base_url',
			'allow_http',
			'request_timeout',
			'request_retries',
			'request_cap_per_hour',
			'breaker_threshold',
			'breaker_cooldown',
		);

		if ( ! in_array( $key, $affects_requests, true ) ) {
			return;
		}

		$this->settings()->flush();
		$this->reset_connection_state();
	}

	/**
	 * Closes the circuit and drops every cached response.
	 *
	 * @return int Number of cached responses removed.
	 */
	public function reset_connection_state(): int {
		$store = $this->store();

		( new CircuitBreaker(
			$store,
			$this->settings()->get_int( 'breaker_threshold', 1, 20 ),
			$this->settings()->get_int( 'breaker_cooldown', 60, 86400 )
		) )->reset();

		return $store->flush_responses();
	}

	/**
	 * Returns the course storage.
	 *
	 * @return CourseRepository
	 */
	public function courses(): CourseRepository {
		return $this->service( 'courses', static fn(): CourseRepository => new CourseRepository() );
	}

	/**
	 * Returns the class occurrence storage.
	 *
	 * @return LessonRepository
	 */
	public function lessons(): LessonRepository {
		return $this->service( 'lessons', static fn(): LessonRepository => new LessonRepository() );
	}

	/**
	 * Returns the synchronisation log.
	 *
	 * @return Logger
	 */
	public function logger(): Logger {
		return $this->service( 'logger', static fn(): Logger => new Logger() );
	}

	/**
	 * Returns the synchroniser.
	 *
	 * @return Synchroniser
	 */
	public function synchroniser(): Synchroniser {
		return $this->service(
			'synchroniser',
			fn(): Synchroniser => new Synchroniser(
				$this->client(),
				$this->courses(),
				$this->lessons(),
				new Matcher(
					(array) $this->settings()->get( 'non_bookable_activities' ),
					(array) $this->settings()->get( 'tag_categories' )
				),
				$this->logger()
			)
		);
	}

	/**
	 * Returns the retention job.
	 *
	 * @return Retention
	 */
	public function retention(): Retention {
		return $this->service(
			'retention',
			fn(): Retention => new Retention( $this->lessons(), $this->logger(), $this->settings() )
		);
	}

	/**
	 * Returns the scheduler.
	 *
	 * @return Scheduler
	 */
	public function scheduler(): Scheduler {
		return $this->service( 'scheduler', fn(): Scheduler => new Scheduler( $this, $this->settings() ) );
	}

	/**
	 * Prepares the site on activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Settings::seed_defaults();
		Schema::install();
		Capabilities::install();
		PostType::register();
		self::instance()->scheduler()->schedule();
		flush_rewrite_rules();
	}

	/**
	 * Stands the plugin down on deactivation, leaving the data alone.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		self::instance()->scheduler()->unschedule();
		flush_rewrite_rules();
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
}
