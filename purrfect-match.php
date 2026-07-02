<?php
/**
 * Plugin Name:       Purrfect Match
 * Plugin URI:        https://www.andrewmayes.com/
 * Description:       Display your shelter's adoptable pets from Petfinder in a beautiful, filterable grid — "Find your purr-fect match." Listings load live in the visitor's browser, so no API key is required.
 * Version:           1.6.4
 * Requires at least: 5.6
 * Requires PHP:      7.0
 * Author:            Andrew Mayes
 * Author URI:        https://www.andrewmayes.com/
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

define( 'PURRFECT_MATCH_VERSION', '1.6.4' );
define( 'PURRFECT_MATCH_FILE', __FILE__ );
define( 'PURRFECT_MATCH_PATH', plugin_dir_path( __FILE__ ) );
define( 'PURRFECT_MATCH_URL', plugin_dir_url( __FILE__ ) );

require_once PURRFECT_MATCH_PATH . 'includes/class-settings.php';
require_once PURRFECT_MATCH_PATH . 'includes/class-rest.php';
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
