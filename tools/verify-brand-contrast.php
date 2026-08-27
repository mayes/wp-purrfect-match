<?php
/**
 * CLI-only verification for arbitrary organization brand colors.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 2 );
}

define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );
require dirname( __DIR__ ) . '/includes/class-purrfect-match.php';

function pm_test_luminance( $hex ) {
	$hex      = ltrim( $hex, '#' );
	$channels = array(
		hexdec( substr( $hex, 0, 2 ) ),
		hexdec( substr( $hex, 2, 2 ) ),
		hexdec( substr( $hex, 4, 2 ) ),
	);

	foreach ( $channels as &$channel ) {
		$channel /= 255;
		$channel  = $channel <= 0.03928 ? $channel / 12.92 : pow( ( $channel + 0.055 ) / 1.055, 2.4 );
	}

	return ( 0.2126 * $channels[0] ) + ( 0.7152 * $channels[1] ) + ( 0.0722 * $channels[2] );
}

function pm_test_ratio( $first, $second ) {
	$a = pm_test_luminance( $first );
	$b = pm_test_luminance( $second );
	return ( max( $a, $b ) + 0.05 ) / ( min( $a, $b ) + 0.05 );
}

$reflection = new ReflectionClass( 'Purrfect_Match' );
$instance   = $reflection->newInstanceWithoutConstructor();
$method     = $reflection->getMethod( 'brand_tokens' );
$method->setAccessible( true );

foreach ( array( '#000000', '#777777', '#e93396', '#ffffff', '#008080', '#ffcc00' ) as $brand ) {
	$tokens = $method->invoke( $instance, $brand );
	if ( '#000000' === $tokens['contrast'] || pm_test_ratio( $brand, $tokens['contrast'] ) < 4.5 ) {
		exit( 4 );
	}
}

echo "BRAND CONTRAST OK\n";
