<?php
// הסרה מלאה: טבלאות, הגדרות ומשימות מתוזמנות.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}storepc_push_log" ); // phpcs:ignore
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}storepc_push_devices" ); // phpcs:ignore
delete_option( 'storepc_push_settings' );
delete_option( 'storepc_push_db_version' );
delete_option( 'storepc_push_last_auto' );
delete_transient( 'storepc_push_fcm_access_token' );
wp_unschedule_hook( 'storepc_push_run_scheduled' );
wp_unschedule_hook( 'storepc_push_auto_run' );
