<?php
/**
 * Purrfect Match uninstall handler.
 *
 * Runs when the plugin is deleted via the WordPress admin.
 * Removes all plugin options and cached data from the database.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove plugin options.
delete_option( 'purrfect_match_options' );

// Remove all plugin transients.
global $wpdb;
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_purrfect_match_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_purrfect_match_' ) . '%'
	)
);
