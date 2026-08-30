<?php
/**
 * CLI contract checks for the shortcode sort mode and its release docs.
 *
 * Usage:
 *   php tools/verify-shortcode-sort.php
 *   php tools/verify-shortcode-sort.php --docs
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 2 );
}

$root      = dirname( __DIR__ );
$docs_mode = in_array( '--docs', $argv, true );

function pm_sort_fail( $message ) {
	fwrite( STDERR, 'SORT CONTRACT FAILURE: ' . $message . "\n" );
	exit( 1 );
}

function pm_sort_assert( $condition, $message ) {
	if ( ! $condition ) {
		pm_sort_fail( $message );
	}
}

function pm_sort_read( $root, $path ) {
	$contents = file_get_contents( $root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $path ) );
	if ( false === $contents ) {
		pm_sort_fail( 'could not read ' . $path );
	}

	return $contents;
}

function pm_sort_contains( $contents, $needle, $message ) {
	pm_sort_assert( false !== strpos( $contents, $needle ), $message );
}

if ( $docs_mode ) {
	$bootstrap    = pm_sort_read( $root, 'purrfect-match.php' );
	$readme       = pm_sort_read( $root, 'readme.txt' );
	$readme_md    = pm_sort_read( $root, 'README.md' );
	$settings     = pm_sort_read( $root, 'includes/class-settings.php' );
	$cjpaws       = pm_sort_read( $root, 'examples/cjpaws.md' );
	$admin        = pm_sort_read( $root, 'preview/admin.html' );
	$public       = pm_sort_read( $root, 'preview/public.php' );
	$teaser       = '[purrfect_match limit="4" per_page="4" columns="4" sort="newest"]';

	pm_sort_assert( 1 === preg_match( '/^ \* Version:\s+1\.8\.0$/m', $bootstrap ), 'plugin header is not 1.8.0' );
	pm_sort_contains( $bootstrap, "define( 'PURRFECT_MATCH_VERSION', '1.8.0' );", 'runtime constant is not 1.8.0' );
	pm_sort_assert( 1 === preg_match( '/^Stable tag:\s*1\.8\.0$/m', $readme ), 'stable tag is not 1.8.0' );
	pm_sort_contains( $readme_md, 'version-1.8.0-e93396', 'README badge is not 1.8.0' );
	pm_sort_contains( $readme_md, 'alt="Version 1.8.0"', 'README badge alt text is not 1.8.0' );
	pm_sort_contains( $admin, 'pm-ver">v1.8.0', 'admin preview is not 1.8.0' );
	pm_sort_contains( $public, 'Purrfect Match 1.8.0', 'public preview is not 1.8.0' );
	pm_sort_assert( 2 === substr_count( $readme, '= 1.8.0 =' ), '1.8.0 must have both changelog and upgrade-notice entries' );
	$upgrade_notice = strstr( $readme, '== Upgrade Notice ==' );
	pm_sort_assert( false !== $upgrade_notice, 'upgrade notice section is missing' );
	pm_sort_contains( $upgrade_notice, '= 1.8.0 =', '1.8.0 upgrade notice is missing' );

	foreach ( array( 'README.md' => $readme_md, 'readme.txt' => $readme, 'Settings help' => $settings, 'CJ Paws example' => $cjpaws ) as $label => $contents ) {
		pm_sort_contains( $contents, $teaser, $label . ' is missing the exact four-card teaser' );
		pm_sort_contains( strtolower( $contents ), 'cache lifetime', $label . ' does not explain cache-lifetime lag' );
	}

	pm_sort_contains( $readme_md, '`sort`', 'README shortcode attribute table is missing sort' );
	pm_sort_contains( $readme, '* `sort`', 'WordPress readme shortcode list is missing sort' );
	pm_sort_contains( $settings, "\$code( 'sort' )", 'Settings help is missing the sort attribute' );
	pm_sort_contains( $cjpaws, 'most recently published adoptable cats', 'CJ Paws teaser does not explain newest ordering' );
	pm_sort_contains( $public, "\$_GET['teaser']", 'public preview is missing the teaser query fixture' );
	pm_sort_contains( $public, "array_slice( \$pets, 0, 4 )", 'teaser fixture does not select exactly four pets' );

	$_GET = array(
		'state'     => 'ready',
		'same_site' => 1,
		'teaser'    => 1,
	);
	ob_start();
	require $root . '/preview/public.php';
	$teaser_html = ob_get_clean();
	pm_sort_assert( 4 === substr_count( $teaser_html, '<article class="pm-card' ), 'teaser preview does not render exactly four pet cards' );
	pm_sort_contains( $teaser_html, 'class="pm-wrap pm-cols-4"', 'teaser preview is not using the four-column widget class' );
	pm_sort_contains( $teaser_html, '--pm-cols:4', 'teaser preview is not using the four-column token' );
	pm_sort_contains( $teaser_html, 'Showing 4 adoptable cats', 'teaser preview count does not match four cards' );
	pm_sort_contains( $teaser_html, '/ newest teaser', 'teaser preview note does not identify the fixture' );
	$widget_html = strstr( $teaser_html, '<section class="pm-wrap' );
	pm_sort_assert( false !== $widget_html && false === stripos( $widget_html, 'newest' ), 'plugin-owned preview copy labels the fallback-sensitive result as newest' );

	echo "NEWEST SORT DOCUMENTATION VERIFICATION PASSED\n";
	exit( 0 );
}

// Minimal WordPress compatibility layer for exercising the production classes.
define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );

function get_current_blog_id() {
	return 1;
}

function get_option( $name, $default = false ) {
	return $default;
}

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, is_array( $args ) ? $args : array() );
}

function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	unset( $shortcode );
	return array_merge( $pairs, array_intersect_key( is_array( $atts ) ? $atts : array(), $pairs ) );
}

function absint( $value ) {
	return abs( (int) $value );
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function esc_url_raw( $value ) {
	return is_scalar( $value ) ? trim( (string) $value ) : '';
}

function trailingslashit( $value ) {
	return rtrim( (string) $value, "/\\" ) . '/';
}

function current_user_can( $capability ) {
	unset( $capability );
	return false;
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

function rest_url( $path = '' ) {
	return 'https://example.test/wp-json/' . ltrim( $path, '/' );
}

function wp_create_nonce( $action ) {
	unset( $action );
	return 'test-nonce';
}

function __( $text, $domain = 'default' ) {
	unset( $domain );
	return $text;
}

class Purrfect_Match_REST {
	const NS = 'purrfect-match/v1';
}

require $root . '/includes/class-settings.php';
require $root . '/includes/class-purrfect-match.php';

$defaults = Purrfect_Match_Settings::defaults();
pm_sort_assert( ! array_key_exists( 'sort', $defaults ), 'sort must not become a saved/global setting' );

$reflection = new ReflectionClass( 'Purrfect_Match' );
$plugin     = $reflection->newInstanceWithoutConstructor();
$resolve    = $reflection->getMethod( 'resolve_atts' );
$build      = $reflection->getMethod( 'build_config' );
$resolve->setAccessible( true );
$build->setAccessible( true );

$absent  = $resolve->invoke( $plugin, array() );
$default = $resolve->invoke( $plugin, array( 'sort' => 'default' ) );
$newest  = $resolve->invoke( $plugin, array( 'sort' => 'newest' ) );
$trimmed = $resolve->invoke( $plugin, array( 'sort' => ' NEWEST ' ) );
$invalid = $resolve->invoke( $plugin, array( 'sort' => 'publish_time DESC' ) );
$array   = $resolve->invoke( $plugin, array( 'sort' => array( 'newest' ) ) );
$unknown = $resolve->invoke( $plugin, array( 'not_allowed' => 'value' ) );

pm_sort_assert( 'default' === $absent['sort'], 'absent sort does not normalize to default' );
pm_sort_assert( 'default' === $default['sort'], 'explicit default sort changed' );
pm_sort_assert( 'newest' === $newest['sort'], 'newest sort is not retained' );
pm_sort_assert( 'newest' === $trimmed['sort'], 'normalized newest sort is not retained' );
pm_sort_assert( 'default' === $invalid['sort'], 'arbitrary sort text did not fall back to default' );
pm_sort_assert( 'default' === $array['sort'], 'non-scalar sort did not fall back to default' );
pm_sort_assert( ! array_key_exists( 'not_allowed', $unknown ), 'unknown shortcode attributes are not discarded' );

$absent_config = $build->invoke( $plugin, $absent );
$default_config = $build->invoke( $plugin, $default );
$newest_config = $build->invoke( $plugin, $newest );
$invalid_config = $build->invoke( $plugin, $invalid );

pm_sort_assert( 'default' === $absent_config['sort'], 'absent config does not emit default' );
pm_sort_assert( $absent_config === $default_config, 'absent and explicit default config differ' );
pm_sort_assert( 'newest' === $newest_config['sort'], 'newest config is not propagated' );
pm_sort_assert( 'default' === $invalid_config['sort'], 'invalid config is not forced to default' );
foreach ( array( $absent_config, $default_config, $newest_config, $invalid_config ) as $config ) {
	pm_sort_assert( in_array( $config['sort'], array( 'default', 'newest' ), true ), 'runtime config emitted a non-allow-listed sort' );
}

$runtime_source = pm_sort_read( $root, 'includes/class-purrfect-match.php' );
pm_sort_contains( $runtime_source, "'sort'                 => 'default'", 'shortcode_atts does not declare the sort attribute' );
pm_sort_contains( $runtime_source, "array( 'default', 'newest' )", 'sort normalization allow-list is missing' );
pm_sort_contains( $runtime_source, "'sort'              => 'newest' === \$atts['sort'] ? 'newest' : 'default'", 'browser config is not defensively constrained' );

echo "PHP SORT CONTRACT VERIFICATION PASSED\n";
