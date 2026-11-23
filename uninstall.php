<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 * Removes data only if "Delete all rules and settings when the plugin is deleted" is ticked.
 *
 * @package AnchorAutopilot
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

function aa_uninstall_site() {
	global $wpdb;
	$settings = get_option( 'aa_settings', array() );
	if ( empty( $settings['delete_on_uninstall'] ) ) {
		return;
	}
	$table = $wpdb->prefix . 'aa_rules';
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	delete_option( 'aa_settings' );
	delete_option( 'aa_db_version' );
	delete_option( 'aa_rules_version' );
	delete_transient( 'aa_rules_cache' );
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_aa_disable'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
		switch_to_blog( $site_id );
		aa_uninstall_site();
		restore_current_blog();
	}
} else {
	aa_uninstall_site();
}
