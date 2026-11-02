<?php
/**
 * CLI-only verification for arbitrary organization brand colors.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 2 );
}

define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );
require dirname( __DIR__ ) . '/includes/class-settings.php';
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

// The settings screen's live preview (admin.js) must pick the same foreground
// as the widget. Run its contrastFor() under Node when Node is available.
$colors = array();
for ( $i = 0; $i < 4096; $i++ ) {
	$colors[] = sprintf( '#%02x%02x%02x', ( $i >> 8 ) * 17, ( ( $i >> 4 ) & 15 ) * 17, ( $i & 15 ) * 17 );
}
$php_picks = array();
foreach ( $colors as $color ) {
	$php_picks[ $color ] = Purrfect_Match_Settings::brand_tokens( $color )['contrast'];
}
$node = getenv( 'PM_NODE' ) ? getenv( 'PM_NODE' ) : 'node';
$probe = @shell_exec( escapeshellarg( $node ) . ' --version 2>&1' );
if ( is_string( $probe ) && 0 === strpos( trim( $probe ), 'v' ) ) {
	$admin = file_get_contents( dirname( __DIR__ ) . '/assets/js/admin.js' );
	if ( ! preg_match( '/var luminanceOf = function[\s\S]*?\n\t\t};\n\t\tvar contrastFor = function[\s\S]*?\n\t\t};\n/', $admin, $m ) ) {
		fwrite( STDERR, "admin.js contrastFor() not found\n" );
		exit( 5 );
	}
	$script = 'var COPY = { onBrand: ' . json_encode( Purrfect_Match_Settings::ON_BRAND_CANDIDATES ) . ' };'
		. 'var ON_BRAND = COPY.onBrand;' . str_replace( 'var ON_BRAND = COPY.onBrand ||', 'var _unused =', $m[0] )
		. 'var out = {};' . json_encode( $colors ) . '.forEach(function (c) { out[c] = contrastFor(c); });'
		. 'process.stdout.write(JSON.stringify(out));';
	$tmp = tempnam( sys_get_temp_dir(), 'pmc' );
	file_put_contents( $tmp, $script );
	$js_picks = json_decode( (string) shell_exec( escapeshellarg( $node ) . ' ' . escapeshellarg( $tmp ) ), true );
	unlink( $tmp );
	if ( $js_picks !== $php_picks ) {
		fwrite( STDERR, "admin.js preview foreground differs from the widget\n" );
		exit( 6 );
	}
	echo "ADMIN PREVIEW CONTRAST PARITY OK\n";
} else {
	echo "ADMIN PREVIEW CONTRAST PARITY SKIPPED (node not found)\n";
}

echo "BRAND CONTRAST OK\n";
