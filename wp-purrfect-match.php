<?php
/**
 * Plugin Name: Purrfect Match
 * Plugin URI:  https://github.com/mayes/wp-purrfect-match
 * Description: A modern, accessible pet listing widget for cat rescue organizations. Displays adoptable cats from Petfinder with filtering, favorites, and social sharing.
 * Version:     1.0.0
 * Author:      Purrfect Match
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: purrfect-match
 * Domain Path: /languages
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

defined( 'ABSPATH' ) || exit;

define( 'PURRFECT_MATCH_VERSION', '1.0.0' );
define( 'PURRFECT_MATCH_DIR', plugin_dir_path( __FILE__ ) );
define( 'PURRFECT_MATCH_URL', plugin_dir_url( __FILE__ ) );
define( 'PURRFECT_MATCH_BASENAME', plugin_basename( __FILE__ ) );

if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Purrfect Match requires PHP 8.0 or higher.', 'purrfect-match' )
			);
		}
	);
	return;
}

require_once PURRFECT_MATCH_DIR . 'includes/class-plugin.php';
PurrfectMatch\Plugin::get_instance();
