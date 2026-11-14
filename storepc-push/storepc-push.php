<?php
/**
 * Plugin Name: StorePC Push – התראות לאפליקציה
 * Description: שליחת התראות פוש לאפליקציית סטור פיסי (Firebase Cloud Messaging): פאנל שליחה ידנית, תזמון, ושליחה אוטומטית בירידת מחיר, מוצר חדש וקופון חדש.
 * Version: 1.0.0
 * Author: רוזין טכנולוגיה בע"מ
 * Text Domain: storepc-push
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'STOREPC_PUSH_VERSION', '1.0.0' );
define( 'STOREPC_PUSH_FILE', __FILE__ );
define( 'STOREPC_PUSH_DIR', plugin_dir_path( __FILE__ ) );
define( 'STOREPC_PUSH_URL', plugin_dir_url( __FILE__ ) );

require_once STOREPC_PUSH_DIR . 'includes/class-settings.php';
require_once STOREPC_PUSH_DIR . 'includes/class-fcm.php';
require_once STOREPC_PUSH_DIR . 'includes/class-sender.php';
require_once STOREPC_PUSH_DIR . 'includes/class-auto.php';
require_once STOREPC_PUSH_DIR . 'includes/class-rest.php';
require_once STOREPC_PUSH_DIR . 'includes/class-admin.php';

/**
 * יצירת טבלאות בעת הפעלה.
 */
function storepc_push_activate() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();

	$log = $wpdb->prefix . 'storepc_push_log';
	dbDelta(
		"CREATE TABLE $log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL DEFAULT '',
			body text NOT NULL,
			image varchar(500) NOT NULL DEFAULT '',
			url varchar(500) NOT NULL DEFAULT '',
			audience varchar(20) NOT NULL DEFAULT 'all',
			token varchar(255) NOT NULL DEFAULT '',
			trigger_type varchar(30) NOT NULL DEFAULT 'manual',
			ref_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'sent',
			response text NULL,
			created_at datetime NOT NULL,
			scheduled_at datetime NULL,
			sent_at datetime NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY sent_at (sent_at)
		) $charset;"
	);

	$dev = $wpdb->prefix . 'storepc_push_devices';
	dbDelta(
		"CREATE TABLE $dev (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token varchar(255) NOT NULL,
			platform varchar(10) NOT NULL DEFAULT '',
			app_version varchar(20) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			last_seen datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY last_seen (last_seen)
		) $charset;"
	);

	update_option( 'storepc_push_db_version', STOREPC_PUSH_VERSION );
	if ( false === get_option( StorePC_Push_Settings::OPTION ) ) {
		add_option( StorePC_Push_Settings::OPTION, StorePC_Push_Settings::defaults() );
	}
}
register_activation_hook( __FILE__, 'storepc_push_activate' );

add_action(
	'plugins_loaded',
	function () {
		if ( get_option( 'storepc_push_db_version' ) !== STOREPC_PUSH_VERSION ) {
			storepc_push_activate();
		}
		StorePC_Push_Sender::init();
		StorePC_Push_Auto::init();
		StorePC_Push_Rest::init();
		if ( is_admin() ) {
			StorePC_Push_Admin::init();
		}
	}
);
