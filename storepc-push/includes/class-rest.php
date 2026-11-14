<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API לאפליקציה:
 *   GET  /wp-json/storepc-push/v1/config         – כתובות לשוניות + CSS
 *   GET  /wp-json/storepc-push/v1/notifications  – ההתראות האחרונות
 *   POST /wp-json/storepc-push/v1/devices        – רישום מכשיר
 */
class StorePC_Push_Rest {
	const NS = 'storepc-push/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			self::NS,
			'/config',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'config' ),
			)
		);
		register_rest_route(
			self::NS,
			'/notifications',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'notifications' ),
			)
		);
		register_rest_route(
			self::NS,
			'/devices',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'register_device' ),
			)
		);
	}

	public static function config() {
		$s = StorePC_Push_Settings::all();
		return rest_ensure_response(
			array(
				'home_url'    => $s['home_url'],
				'deals_url'   => $s['deals_url'],
				'cart_url'    => $s['cart_url'],
				'account_url' => $s['account_url'],
				'custom_css'  => $s['custom_css'],
			)
		);
	}

	public static function notifications() {
		$rows = StorePC_Push_Sender::recent_public( 30 );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'id'      => (int) $r['id'],
				'title'   => $r['title'],
				'body'    => $r['body'],
				'image'   => $r['image'],
				'url'     => $r['url'],
				'sent_at' => $r['sent_at'] ? gmdate( 'c', strtotime( $r['sent_at'] . ' UTC' ) ) : null,
			);
		}
		$res = rest_ensure_response( $out );
		$res->header( 'Cache-Control', 'public, max-age=60' );
		return $res;
	}

	public static function register_device( WP_REST_Request $req ) {
		$token = (string) $req->get_param( 'token' );
		if ( ! preg_match( '/^[A-Za-z0-9_\-:]{100,255}$/', $token ) ) {
			return new WP_Error( 'invalid_token', 'Invalid token', array( 'status' => 400 ) );
		}

		// הגבלת קצב פשוטה לפי IP
		$ip_key = 'storepc_push_rl_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ); // phpcs:ignore
		$count  = (int) get_transient( $ip_key );
		if ( $count > 30 ) {
			return new WP_Error( 'rate_limited', 'Too many requests', array( 'status' => 429 ) );
		}
		set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );

		$platform = in_array( $req->get_param( 'platform' ), array( 'android', 'ios' ), true ) ? $req->get_param( 'platform' ) : '';
		$version  = substr( sanitize_text_field( (string) $req->get_param( 'app_version' ) ), 0, 20 );
		$now      = current_time( 'mysql', true );

		global $wpdb;
		$table = $wpdb->prefix . 'storepc_push_devices';
		$wpdb->query( // phpcs:ignore
			$wpdb->prepare(
				"INSERT INTO $table (token, platform, app_version, created_at, last_seen) VALUES (%s, %s, %s, %s, %s)
				 ON DUPLICATE KEY UPDATE platform = VALUES(platform), app_version = VALUES(app_version), last_seen = VALUES(last_seen)", // phpcs:ignore
				$token,
				$platform,
				$version,
				$now,
				$now
			)
		);
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * סטטיסטיקה לפאנל הניהול.
	 */
	public static function device_stats() {
		global $wpdb;
		$table = $wpdb->prefix . 'storepc_push_devices';
		$since = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		// phpcs:disable
		return array(
			'total'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ),
			'active'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE last_seen >= %s", $since ) ),
			'android' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE platform = 'android'" ),
			'ios'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE platform = 'ios'" ),
		);
		// phpcs:enable
	}
}
