<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * הגדרות התוסף (אופציה אחת במסד הנתונים).
 */
class StorePC_Push_Settings {
	const OPTION = 'storepc_push_settings';

	public static function defaults() {
		$home = home_url( '/' );
		return array(
			'service_account'      => '',
			'test_token'           => '',

			// כתובות הלשוניות באפליקציה
			'home_url'             => $home,
			'deals_url'            => $home . 'מבצעים/',
			'cart_url'             => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : $home . 'cart/',
			'account_url'          => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : $home . 'my-account/',
			'custom_css'           => '',

			// אוטומציות
			'auto_sale'            => 1,
			'auto_sale_min_pct'    => 5,
			'auto_new_product'     => 0,
			'auto_coupon'          => 1,
			'auto_dedupe_hours'    => 24,
			'auto_min_interval'    => 30,
			'auto_delay'           => 2,

			// תבניות
			'tpl_sale_title'       => '🔥 ירידת מחיר: {name}',
			'tpl_sale_body'        => 'עכשיו ב-₪{price} במקום ₪{regular_price} ({discount}% הנחה)',
			'tpl_new_title'        => '✨ חדש בחנות: {name}',
			'tpl_new_body'         => 'כבר זמין באתר ב-₪{price}',
			'tpl_coupon_title'     => '🎁 קופון חדש: {code}',
			'tpl_coupon_body'      => '{description}',
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function save( array $values ) {
		update_option( self::OPTION, wp_parse_args( $values, self::all() ), false );
	}

	/**
	 * מחליף משתנים בתבנית, למשל {name}.
	 */
	public static function fill( $template, array $vars ) {
		foreach ( $vars as $k => $v ) {
			$template = str_replace( '{' . $k . '}', (string) $v, $template );
		}
		return trim( preg_replace( '/\{[a-z_]+\}/', '', $template ) );
	}
}
