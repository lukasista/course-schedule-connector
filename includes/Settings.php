<?php
/**
 * Plugin settings.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS;

use CSCS\Support\Url;

defined( 'ABSPATH' ) || exit;

/**
 * Typed access to the plugin's single settings option.
 *
 * Everything the plugin can be configured with lives in one option so that a
 * read costs a single query. Values are validated here rather than at the point
 * of use, so no caller has to wonder whether a stored value can be trusted.
 */
final class Settings {

	/**
	 * Option name.
	 */
	public const OPTION = 'cscs_settings';

	/**
	 * Cached option contents.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $values = null;

	/**
	 * Returns the default value of every setting.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'api_base_url'             => '',
			'allow_http'               => false,
			'semester_from'            => '',
			'semester_to'              => '',
			'interval_courses'         => 600,
			'interval_lessons_near'    => 900,
			'near_window_days'         => 21,
			'request_cap_per_hour'     => 60,
			'request_timeout'          => 10,
			'request_retries'          => 2,
			'cache_fresh_seconds'      => 300,
			'cache_stale_seconds'      => 86400,
			'breaker_threshold'        => 3,
			'breaker_cooldown'         => 1800,
			'lesson_price_when_empty'  => 'on_request',
			'lesson_retention_days'    => 30,
			'show_isport_button'       => true,
			'show_canceled_lessons'    => true,
			'table_breakpoint'         => 768,
			'delete_data_on_uninstall' => false,
		);
	}

	/**
	 * Returns one setting.
	 *
	 * @param string $key Setting name.
	 * @return mixed Stored value, or the default when unset.
	 */
	public function get( string $key ) {
		$values = $this->all();

		return $values[ $key ] ?? null;
	}

	/**
	 * Returns every setting merged over the defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null === $this->values ) {
			$stored = get_option( self::OPTION, array() );

			$this->values = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		}

		return $this->values;
	}

	/**
	 * Returns the validated API base URL.
	 *
	 * @return string Empty string when no usable URL is configured.
	 */
	public function api_base_url(): string {
		$raw = (string) $this->get( 'api_base_url' );

		if ( '' === $raw ) {
			return '';
		}

		try {
			return Url::validate_base( $raw, (bool) $this->get( 'allow_http' ) );
		} catch ( \InvalidArgumentException $e ) {
			return '';
		}
	}

	/**
	 * Whether the plugin has enough configuration to contact the remote system.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return '' !== $this->api_base_url();
	}

	/**
	 * Returns an integer setting clamped to a range.
	 *
	 * @param string $key Setting name.
	 * @param int    $min Lower bound.
	 * @param int    $max Upper bound.
	 * @return int
	 */
	public function get_int( string $key, int $min, int $max ): int {
		$value = (int) $this->get( $key );

		return max( $min, min( $max, $value ) );
	}

	/**
	 * Writes one setting, validating it against what the key expects.
	 *
	 * @param string $key   Setting name.
	 * @param mixed  $value Raw value.
	 * @return mixed The stored value.
	 * @throws \InvalidArgumentException When the key is unknown or the value unusable.
	 */
	public function set( string $key, $value ) {
		$defaults = self::defaults();

		if ( ! array_key_exists( $key, $defaults ) ) {
			throw new \InvalidArgumentException( 'Unknown setting.' );
		}

		$clean  = $this->sanitise( $key, $value, $defaults[ $key ] );
		$values = $this->all();

		$values[ $key ] = $clean;

		update_option( self::OPTION, $values, false );

		$this->values = $values;

		/**
		 * Fires after one setting has been written and validated.
		 *
		 * @since 0.2.0
		 *
		 * @param string $key   Setting name.
		 * @param mixed  $clean The stored value.
		 */
		do_action( 'cscs_setting_changed', $key, $clean );

		return $clean;
	}

	/**
	 * Writes the defaults if the option does not exist yet.
	 *
	 * Called on activation so that there is always something to read, and always
	 * something for an administrator to edit rather than an absent option that
	 * every tool then refuses to patch.
	 *
	 * @return void
	 */
	public static function seed_defaults(): void {
		add_option( self::OPTION, self::defaults(), '', false );
	}

	/**
	 * Coerces and validates a value for a given key.
	 *
	 * @param string $key     Setting name.
	 * @param mixed  $value   Raw value.
	 * @param mixed  $default_value The default, used to infer the expected type.
	 * @return mixed
	 * @throws \InvalidArgumentException When the value cannot be used.
	 */
	private function sanitise( string $key, $value, $default_value ) {
		if ( 'api_base_url' === $key ) {
			$raw = trim( (string) $value );

			if ( '' === $raw ) {
				return '';
			}

			// Throws when the URL is malformed or points somewhere it must not.
			return Url::validate_base( $raw, (bool) $this->get( 'allow_http' ) );
		}

		if ( in_array( $key, array( 'semester_from', 'semester_to' ), true ) ) {
			$raw = trim( (string) $value );

			if ( '' !== $raw && 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
				throw new \InvalidArgumentException( 'Expected a date in Y-m-d form.' );
			}

			return $raw;
		}

		if ( 'non_bookable_activities' === $key ) {
			$names = is_array( $value ) ? $value : explode( "\n", (string) $value );

			return array_values(
				array_filter(
					array_map(
						static fn( $name ): string => sanitize_text_field( trim( (string) $name ) ),
						$names
					),
					static fn( string $name ): bool => '' !== $name
				)
			);
		}

		if ( 'lesson_price_when_empty' === $key ) {
			$raw = strtolower( trim( (string) $value ) );

			if ( ! in_array( $raw, array( 'free', 'on_request' ), true ) ) {
				throw new \InvalidArgumentException( 'Expected "free" or "on_request".' );
			}

			return $raw;
		}

		if ( is_bool( $default_value ) ) {
			return in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'on' ), true );
		}

		if ( is_int( $default_value ) ) {
			if ( ! is_numeric( $value ) ) {
				throw new \InvalidArgumentException( 'Expected a number.' );
			}

			return (int) $value;
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Discards the in-memory copy so the next read hits the database.
	 *
	 * @return void
	 */
	public function flush(): void {
		$this->values = null;
	}
}
