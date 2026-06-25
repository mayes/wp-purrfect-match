<?php
/**
 * Runs when the plugin is deleted from the WordPress admin.
 *
 * Removes the plugin's saved options and the optional shared-cache transients
 * it creates (pm_cache_*). No custom tables or post data are created.
 *
 * @package PurrfectMatch
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete this plugin's data for the current site.
 *
 * @return void
 */
function purrfect_match_delete_site_data() {
	global $wpdb;

	delete_option( 'purrfect_match_options' );

	// Remove the shared-cache transients (value + timeout rows) created by the
	// REST cache. `_` is escaped so it is treated literally in the LIKE.
	$like_value   = $wpdb->esc_like( '_transient_pm_cache_' ) . '%';
	$like_timeout = $wpdb->esc_like( '_transient_timeout_pm_cache_' ) . '%';

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$like_value,
			$like_timeout
		)
	);
}

purrfect_match_delete_site_data();

// Multisite: clean up every site too.
if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( (array) $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		purrfect_match_delete_site_data();
		restore_current_blog();
	}
}
