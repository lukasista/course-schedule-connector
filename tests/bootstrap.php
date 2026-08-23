<?php
/**
 * PHPUnit bootstrap.
 *
 * Provides just enough of WordPress for the plugin's data layer to run without
 * a WordPress installation. The classes under test are deliberately written so
 * that only a handful of core functions are needed.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );
define( 'CSCS_VERSION', '0.1.0' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$cscs_composer_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( is_readable( $cscs_composer_autoload ) ) {
	require_once $cscs_composer_autoload;
}

require_once dirname( __DIR__ ) . '/includes/Autoloader.php';

\CSCS\Autoloader::register( dirname( __DIR__ ) . '/includes/' );

/**
 * In-memory transient storage used by the stubs below.
 *
 * @var array<string, array{value: mixed, expires: int}>
 */
$GLOBALS['cscs_test_transients'] = array();

/**
 * In-memory option storage used by the stubs below.
 *
 * @var array<string, mixed>
 */
$GLOBALS['cscs_test_options'] = array();

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Transient stub.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	function get_transient( string $key ) {
		$entry = $GLOBALS['cscs_test_transients'][ $key ] ?? null;

		if ( null === $entry || $entry['expires'] < time() ) {
			return false;
		}

		return $entry['value'];
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Transient stub.
	 *
	 * @param string $key     Key.
	 * @param mixed  $value   Value.
	 * @param int    $expires Lifetime in seconds.
	 * @return bool
	 */
	function set_transient( string $key, $value, int $expires = 0 ): bool {
		$GLOBALS['cscs_test_transients'][ $key ] = array(
			'value'   => $value,
			'expires' => time() + ( $expires > 0 ? $expires : 3600 ),
		);

		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * Transient stub.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	function delete_transient( string $key ): bool {
		unset( $GLOBALS['cscs_test_transients'][ $key ] );

		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Option stub.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default_value Fallback.
	 * @return mixed
	 */
	function get_option( string $key, $default_value = false ) {
		return $GLOBALS['cscs_test_options'][ $key ] ?? $default_value;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Option stub.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	function update_option( string $key, $value ): bool {
		$GLOBALS['cscs_test_options'][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * JSON stub.
	 *
	 * @param mixed $data Data.
	 * @return string|false
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Integer stub.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	function absint( $value ): int {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Escaping stub.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_using_ext_object_cache' ) ) {
	/**
	 * Object cache stub.
	 *
	 * @return bool
	 */
	function wp_using_ext_object_cache(): bool {
		return false;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	/**
	 * Option stub.
	 *
	 * @param string $key      Key.
	 * @param mixed  $value    Value.
	 * @param string $deprecated Unused.
	 * @param bool   $autoload Unused.
	 * @return bool
	 */
	function add_option( string $key, $value = '', string $deprecated = '', bool $autoload = true ): bool {
		if ( array_key_exists( $key, $GLOBALS['cscs_test_options'] ) ) {
			return false;
		}

		$GLOBALS['cscs_test_options'][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Sanitising stub.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	function sanitize_key( string $key ): string {
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Sanitising stub.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function sanitize_text_field( string $text ): string {
		return trim( (string) preg_replace( '/[\r\n\t]+/', ' ', wp_strip_all_tags_stub( $text ) ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags_stub' ) ) {
	/**
	 * Tag-stripping helper for the sanitising stub.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function wp_strip_all_tags_stub( string $text ): string {
		return strip_tags( $text );
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Translation stub: the tests run without a text domain loaded.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( string $text, string $domain = 'default' ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- A stub for the test bootstrap.
		unset( $domain );

		return $text;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Filter stub.
	 *
	 * @param string $hook  Hook name.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Extra arguments.
	 * @return mixed
	 */
	function apply_filters( string $hook, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Action stub.
	 *
	 * @param string $hook Hook name.
	 * @param mixed  ...$args Arguments.
	 * @return void
	 */
	function do_action( string $hook, ...$args ): void {
	}
}

/**
 * Resets all in-memory storage between tests.
 *
 * @return void
 */
function cscs_reset_test_state(): void {
	$GLOBALS['cscs_test_transients'] = array();
	$GLOBALS['cscs_test_options']    = array();
}
