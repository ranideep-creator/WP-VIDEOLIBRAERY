<?php
// Runs only when the plugin is deleted from wp-admin, not on plain deactivation.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin settings. The wp_lvl_videos table is left in place on
// purpose, so re-installing the plugin doesn't lose the synced library.
delete_option( 'lvl_sheet_id' );
delete_option( 'lvl_sheet_gid' );
delete_option( 'lvl_per_page' );
delete_option( 'lvl_interval' );
delete_option( 'lvl_last_sync' );

$timestamp = wp_next_scheduled( 'lvl_sync_cron_hook' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'lvl_sync_cron_hook' );
}
