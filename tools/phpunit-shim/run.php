<?php
declare( strict_types=1 );
require __DIR__ . '/testcase.php';
$root = dirname( __DIR__, 2 );
require $root . '/tests/bootstrap.php';

$files = glob( $root . '/tests/Unit/*.php' );
foreach ( $files as $file ) { require_once $file; }

$pass = 0; $fail = 0; $failures = array();
foreach ( get_declared_classes() as $class ) {
	if ( ! is_subclass_of( $class, \PHPUnit\Framework\TestCase::class ) ) { continue; }
	$rc = new ReflectionClass( $class );
	if ( $rc->isAbstract() ) { continue; }
	foreach ( $rc->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) {
		if ( ! str_starts_with( $method->getName(), 'test' ) ) { continue; }
		$cases = array( array() );
		$provider = null;
		foreach ( $method->getAttributes() as $attribute ) {
			if ( str_contains( $attribute->getName(), 'DataProvider' ) ) { $provider = $attribute->getArguments()[0] ?? null; }
		}
		if ( null === $provider && preg_match( '/@dataProvider\s+(\w+)/', (string) $method->getDocComment(), $found ) ) { $provider = $found[1]; }
		if ( null !== $provider && $rc->hasMethod( $provider ) ) {
			$source = $rc->getMethod( $provider );
			$source->setAccessible( true );
			$cases = array();
			foreach ( $source->invoke( $source->isStatic() ? null : $rc->newInstance() ) as $case ) { $cases[] = (array) $case; }
		}
		foreach ( $cases as $case ) {
			$instance = $rc->newInstance();
			$setup = $rc->hasMethod( 'setUp' ) ? $rc->getMethod( 'setUp' ) : null;
			if ( $setup ) { $setup->setAccessible( true ); $setup->invoke( $instance ); }
			try {
				$method->invokeArgs( $instance, $case );
				if ( null !== $instance->expected_exception ) { throw new Exception( 'Expected exception ' . $instance->expected_exception . ' was not thrown.' ); }
				++$pass;
			} catch ( Throwable $e ) {
				if ( null !== $instance->expected_exception && $e instanceof $instance->expected_exception
					&& ( null === $instance->expected_message || str_contains( $e->getMessage(), $instance->expected_message ) ) ) { ++$pass; continue; }
				++$fail;
				$failures[] = $rc->getShortName() . '::' . $method->getName() . ' — ' . $e->getMessage();
			}
		}
	}
}
echo "Tests: " . ( $pass + $fail ) . ", passed: {$pass}, failed: {$fail}, assertions: " . \PHPUnit\Framework\TestCase::$assertions . "\n";
foreach ( $failures as $f ) { echo "FAIL  {$f}\n"; }
exit( $fail > 0 ? 1 : 0 );
