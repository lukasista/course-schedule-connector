<?php
declare( strict_types=1 );
namespace PHPUnit\Framework;

class AssertionFailedError extends \Exception {}

abstract class TestCase {
	public static int $assertions = 0;

	protected function assertTrue( $v, string $m = '' ): void { self::check( true === $v, $m ?: 'Expected true, got ' . var_export( $v, true ) ); }
	protected function assertFalse( $v, string $m = '' ): void { self::check( false === $v, $m ?: 'Expected false, got ' . var_export( $v, true ) ); }
	protected function assertNull( $v, string $m = '' ): void { self::check( null === $v, $m ?: 'Expected null.' ); }
	protected function assertNotNull( $v, string $m = '' ): void { self::check( null !== $v, $m ?: 'Expected not null.' ); }
	protected function assertSame( $e, $a, string $m = '' ): void { self::check( $e === $a, $m ?: 'Expected ' . var_export( $e, true ) . ', got ' . var_export( $a, true ) ); }
	protected function assertNotSame( $e, $a, string $m = '' ): void { self::check( $e !== $a, $m ?: 'Values are identical.' ); }
	protected function assertEquals( $e, $a, string $m = '' ): void { self::check( $e == $a, $m ?: 'Expected ' . var_export( $e, true ) . ', got ' . var_export( $a, true ) ); }
	protected function assertCount( int $e, $a, string $m = '' ): void { self::check( $e === count( $a ), $m ?: 'Expected ' . $e . ' items, got ' . count( $a ) ); }
	protected function assertEmpty( $a, string $m = '' ): void { self::check( empty( $a ), $m ?: 'Expected empty.' ); }
	protected function assertNotEmpty( $a, string $m = '' ): void { self::check( ! empty( $a ), $m ?: 'Expected not empty.' ); }
	protected function assertIsArray( $a, string $m = '' ): void { self::check( is_array( $a ), $m ?: 'Expected array.' ); }
	protected function assertIsString( $a, string $m = '' ): void { self::check( is_string( $a ), $m ?: 'Expected string.' ); }
	protected function assertIsInt( $a, string $m = '' ): void { self::check( is_int( $a ), $m ?: 'Expected int.' ); }
	protected function assertIsFloat( $a, string $m = '' ): void { self::check( is_float( $a ), $m ?: 'Expected float.' ); }
	protected function assertIsBool( $a, string $m = '' ): void { self::check( is_bool( $a ), $m ?: 'Expected bool.' ); }
	protected function assertArrayHasKey( $k, $a, string $m = '' ): void { self::check( array_key_exists( $k, $a ), $m ?: 'Missing key ' . var_export( $k, true ) ); }
	protected function assertArrayNotHasKey( $k, $a, string $m = '' ): void { self::check( ! array_key_exists( $k, $a ), $m ?: 'Unexpected key ' . var_export( $k, true ) ); }
	protected function assertContains( $n, $h, string $m = '' ): void { self::check( in_array( $n, $h, true ), $m ?: 'Value not found.' ); }
	protected function assertNotContains( $n, $h, string $m = '' ): void { self::check( ! in_array( $n, $h, true ), $m ?: 'Value found.' ); }
	protected function assertStringContainsString( string $n, string $h, string $m = '' ): void { self::check( str_contains( $h, $n ), $m ?: 'Missing "' . $n . '" in "' . $h . '"' ); }
	protected function assertStringNotContainsString( string $n, string $h, string $m = '' ): void { self::check( ! str_contains( $h, $n ), $m ?: 'Found "' . $n . '"' ); }
	protected function assertStringStartsWith( string $n, string $h, string $m = '' ): void { self::check( str_starts_with( $h, $n ), $m ?: 'Does not start with ' . $n ); }
	protected function assertGreaterThan( $e, $a, string $m = '' ): void { self::check( $a > $e, $m ?: 'Not greater.' ); }
	protected function assertGreaterThanOrEqual( $e, $a, string $m = '' ): void { self::check( $a >= $e, $m ?: 'Not greater or equal.' ); }
	protected function assertLessThan( $e, $a, string $m = '' ): void { self::check( $a < $e, $m ?: 'Not less.' ); }
	protected function assertLessThanOrEqual( $e, $a, string $m = '' ): void { self::check( $a <= $e, $m ?: 'Not less or equal.' ); }
	protected function assertInstanceOf( string $c, $a, string $m = '' ): void { self::check( $a instanceof $c, $m ?: 'Not an instance of ' . $c ); }
	protected function assertMatchesRegularExpression( string $p, string $s, string $m = '' ): void { self::check( 1 === preg_match( $p, $s ), $m ?: 'Pattern did not match.' ); }
	protected function fail( string $m = '' ): void { self::check( false, $m ?: 'Failed.' ); }
	protected function expectException( string $c ): void { $this->expected_exception = $c; }
	protected function expectExceptionMessage( string $m ): void { $this->expected_message = $m; }
	protected function expectExceptionCode( $c ): void {}
	public ?string $expected_exception = null;
	public ?string $expected_message = null;
	protected function setUp(): void {}
	protected function tearDown(): void {}

	private static function check( bool $ok, string $message ): void {
		++self::$assertions;
		if ( ! $ok ) {
			throw new AssertionFailedError( $message );
		}
	}
}
