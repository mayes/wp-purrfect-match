<?php
/**
 * Runs when the plugin is deleted from the WordPress admin.
 *
 * Removes the plugin's saved options. No custom tables or post data are
 * created by this plugin, so nothing else needs cleaning up.
 *
 * @package PurrfectMatch
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'purrfect_match_options' );

// Multisite: clean up per-site options too.
if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( (array) $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		delete_option( 'purrfect_match_options' );
		restore_current_blog();
	}
}
