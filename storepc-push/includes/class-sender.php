<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * יצירת רשומת שליחה, שליחה מיידית / מתוזמנת, ותיעוד בהיסטוריה.
 */
class StorePC_Push_Sender {
	const CRON_HOOK = 'storepc_push_run_scheduled';

	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'dispatch' ) );
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'storepc_push_log';
	}

	/**
	 * יוצר רשומה בהיסטוריה.
	 *
	 * @param array $a title, body, image, url, audience(all|deals|token), token, trigger_type, ref_id.
	 */
	public static function create( array $a, $status = 'pending', $scheduled_gmt = null ) {
		global $wpdb;
		$wpdb->insert(
			self::table(),
			array(
				'title'        => mb_substr( wp_strip_all_tags( $a['title'] ?? '' ), 0, 120 ),
				'body'         => mb_substr( wp_strip_all_tags( $a['body'] ?? '' ), 0, 300 ),
				'image'        => esc_url_raw( $a['image'] ?? '' ),
				'url'          => esc_url_raw( $a['url'] ?? '' ),
				'audience'     => in_array( $a['audience'] ?? 'all', array( 'all', 'deals', 'token' ), true ) ? $a['audience'] : 'all',
				'token'        => sanitize_text_field( $a['token'] ?? '' ),
				'trigger_type' => sanitize_key( $a['trigger_type'] ?? 'manual' ),
				'ref_id'       => absint( $a['ref_id'] ?? 0 ),
				'status'       => $status,
				'created_at'   => current_time( 'mysql', true ),
				'scheduled_at' => $scheduled_gmt,
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
	}

	/**
	 * שליחה מיידית.
	 *
	 * @return array [id, true|WP_Error]
	 */
	public static function send_now( array $a ) {
		$id = self::create( $a, 'pending' );
		return array( $id, self::dispatch( $id ) );
	}

	/**
	 * תזמון שליחה לזמן עתידי (timestamp).
	 */
	public static function schedule( array $a, $timestamp ) {
		$id = self::create( $a, 'scheduled', gmdate( 'Y-m-d H:i:s', $timestamp ) );
		wp_schedule_single_event( $timestamp, self::CRON_HOOK, array( $id ) );
		return $id;
	}

	public static function cancel( $id ) {
		global $wpdb;
		wp_clear_scheduled_hook( self::CRON_HOOK, array( (int) $id ) );
		$wpdb->update( self::table(), array( 'status' => 'canceled' ), array( 'id' => (int) $id, 'status' => 'scheduled' ) );
	}

	/**
	 * שולח רשומה קיימת.
	 *
	 * @return true|WP_Error
	 */
	public static function dispatch( $id ) {
		global $wpdb;
		$row = self::get( $id );
		if ( ! $row || ! in_array( $row['status'], array( 'pending', 'scheduled' ), true ) ) {
			return new WP_Error( 'storepc_push_state', 'ההודעה כבר נשלחה או בוטלה.' );
		}

		$target = 'token' === $row['audience']
			? array( 'token' => $row['token'] )
			: array( 'topic' => $row['audience'] );

		$result = StorePC_Push_FCM::send( $target, $row['title'], $row['body'], $row['image'], $row['url'], $row['id'] );

		$wpdb->update(
			self::table(),
			array(
				'status'   => is_wp_error( $result ) ? 'failed' : 'sent',
				'response' => is_wp_error( $result ) ? $result->get_error_message() : 'OK',
				'sent_at'  => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id )
		);
		return $result;
	}

	/**
	 * ההתראות האחרונות שנשלחו (למסך "ההתראות שלי" באפליקציה).
	 */
	public static function recent_public( $limit = 30 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, title, body, image, url, sent_at FROM ' . self::table() . // phpcs:ignore
				" WHERE status = 'sent' AND audience IN ('all','deals') ORDER BY id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}
}
