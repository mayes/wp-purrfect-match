<?php
/**
 * Plugin Name:       Purrfect Match
 * Plugin URI:        https://cjpaws.org/
 * Description:       Display adoptable pets from Petfinder in a beautiful, filterable grid. Built for CJ Paws (cjpaws.org) — "Find your purr-fect match." Data is loaded live in the visitor's browser, so no API key is required.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.0
 * Author:            CJ Paws
 * Author URI:        https://cjpaws.org/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       purrfect-match
 * Domain Path:       /languages
 *
 * @package PurrfectMatch
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PURRFECT_MATCH_VERSION', '1.0.0' );
define( 'PURRFECT_MATCH_FILE', __FILE__ );
define( 'PURRFECT_MATCH_PATH', plugin_dir_path( __FILE__ ) );
define( 'PURRFECT_MATCH_URL', plugin_dir_url( __FILE__ ) );

require_once PURRFECT_MATCH_PATH . 'includes/class-settings.php';
require_once PURRFECT_MATCH_PATH . 'includes/class-purrfect-match.php';

/**
 * Boot the plugin.
 *
 * @return Purrfect_Match
 */
function purrfect_match() {
	return Purrfect_Match::instance();
}

purrfect_match();
