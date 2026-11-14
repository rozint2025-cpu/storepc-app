<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * שליחה ל-Firebase Cloud Messaging ב-API החדש (HTTP v1), בלי ספריות חיצוניות.
 * נדרש קובץ Service Account (JSON) מ-Firebase.
 */
class StorePC_Push_FCM {
	const TOKEN_TRANSIENT = 'storepc_push_fcm_access_token';

	/**
	 * @return array|WP_Error
	 */
	public static function credentials() {
		$raw = StorePC_Push_Settings::get( 'service_account' );
		if ( defined( 'STOREPC_PUSH_SERVICE_ACCOUNT_FILE' ) && is_readable( STOREPC_PUSH_SERVICE_ACCOUNT_FILE ) ) {
			$raw = file_get_contents( STOREPC_PUSH_SERVICE_ACCOUNT_FILE ); // phpcs:ignore
		}
		$json = json_decode( (string) $raw, true );
		if ( ! is_array( $json ) || empty( $json['client_email'] ) || empty( $json['private_key'] ) || empty( $json['project_id'] ) ) {
			return new WP_Error( 'storepc_push_no_credentials', 'לא הוגדר קובץ Service Account תקין של Firebase (בהגדרות התוסף).' );
		}
		return $json;
	}

	public static function is_configured() {
		return ! is_wp_error( self::credentials() );
	}

	private static function b64url( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * @return string|WP_Error
	 */
	public static function access_token() {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( $cached ) {
			return $cached;
		}
		$cred = self::credentials();
		if ( is_wp_error( $cred ) ) {
			return $cred;
		}
		if ( ! function_exists( 'openssl_sign' ) ) {
			return new WP_Error( 'storepc_push_openssl', 'הרחבת OpenSSL של PHP אינה זמינה בשרת.' );
		}

		$now    = time();
		$header = self::b64url( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$claims = self::b64url(
			wp_json_encode(
				array(
					'iss'   => $cred['client_email'],
					'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
					'aud'   => 'https://oauth2.googleapis.com/token',
					'iat'   => $now,
					'exp'   => $now + 3600,
				)
			)
		);
		$signature = '';
		$key       = openssl_pkey_get_private( $cred['private_key'] );
		if ( ! $key || ! openssl_sign( $header . '.' . $claims, $signature, $key, 'sha256WithRSAEncryption' ) ) {
			return new WP_Error( 'storepc_push_sign', 'חתימת JWT נכשלה – בדקו שהמפתח הפרטי ב-Service Account תקין.' );
		}
		$jwt = $header . '.' . $claims . '.' . self::b64url( $signature );

		$res = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 15,
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $body['access_token'] ) ) {
			return new WP_Error( 'storepc_push_oauth', 'קבלת טוקן מ-Google נכשלה: ' . wp_remote_retrieve_body( $res ) );
		}
		$ttl = max( 60, (int) ( $body['expires_in'] ?? 3600 ) - 300 );
		set_transient( self::TOKEN_TRANSIENT, $body['access_token'], $ttl );
		return $body['access_token'];
	}

	/**
	 * שולח הודעה.
	 *
	 * @param array $target ['topic' => 'all'] או ['token' => '...'].
	 * @return true|WP_Error
	 */
	public static function send( array $target, $title, $body, $image = '', $url = '', $id = 0 ) {
		$cred = self::credentials();
		if ( is_wp_error( $cred ) ) {
			return $cred;
		}
		$token = self::access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$notification = array(
			'title' => $title,
			'body'  => $body,
		);
		if ( $image ) {
			$notification['image'] = $image;
		}

		$message = array_merge(
			$target,
			array(
				'notification' => $notification,
				'data'         => array(
					'url' => (string) $url,
					'id'  => (string) $id,
				),
				'android'      => array(
					'priority'     => 'high',
					'notification' => array( 'sound' => 'default' ),
				),
				'apns'         => array(
					'payload' => array(
						'aps' => array(
							'sound'           => 'default',
							'mutable-content' => 1,
						),
					),
				),
			)
		);
		if ( $image ) {
			$message['apns']['fcm_options'] = array( 'image' => $image );
		}

		$res = wp_remote_post(
			'https://fcm.googleapis.com/v1/projects/' . rawurlencode( $cred['project_id'] ) . '/messages:send',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json; charset=UTF-8',
				),
				'body'    => wp_json_encode( array( 'message' => $message ) ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		if ( 401 === $code ) {
			delete_transient( self::TOKEN_TRANSIENT );
		}
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'storepc_push_fcm', 'FCM ' . $code . ': ' . wp_remote_retrieve_body( $res ) );
		}
		return true;
	}
}
