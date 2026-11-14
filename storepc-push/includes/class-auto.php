<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * שליחה אוטומטית:
 *  - ירידת מחיר / מוצר שנכנס למבצע (כולל מבצעים מתוזמנים של WooCommerce)
 *  - מוצר חדש שפורסם
 *  - קופון חדש שסומן "שלח פוש"
 *
 * הגנות: מניעת כפילות לאותו מוצר, מרווח מינימלי בין שליחות אוטומטיות,
 * והשהיה קצרה לפני שליחה (כדי שהמוצר יישמר במלואו).
 */
class StorePC_Push_Auto {
	const CRON_HOOK      = 'storepc_push_auto_run';
	const LAST_AUTO_OPT  = 'storepc_push_last_auto';
	const META_LAST_PUSH = '_storepc_push_last';

	/** @var array מחירים לפני שמירה, לפי מזהה מוצר */
	private static $before = array();

	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run' ), 10, 2 );

		foreach ( array( 'product', 'product_variation' ) as $type ) {
			add_action( "woocommerce_before_{$type}_object_save", array( __CLASS__, 'capture_before' ), 10, 1 );
			add_action( "woocommerce_after_{$type}_object_save", array( __CLASS__, 'check_after' ), 10, 1 );
		}

		add_action( 'transition_post_status', array( __CLASS__, 'on_status_change' ), 10, 3 );

		add_action( 'add_meta_boxes_shop_coupon', array( __CLASS__, 'coupon_metabox' ) );
		add_action( 'save_post_shop_coupon', array( __CLASS__, 'save_coupon' ), 20, 2 );
	}

	// ---------------------------------------------------------------- ירידת מחיר

	public static function capture_before( $product ) {
		$id = $product->get_id();
		if ( ! $id || isset( self::$before[ $id ] ) ) {
			return;
		}
		self::$before[ $id ] = array(
			'price' => (float) get_post_meta( $id, '_price', true ),
			'sale'  => (string) get_post_meta( $id, '_sale_price', true ),
		);
	}

	public static function check_after( $product ) {
		$id = $product->get_id();
		if ( ! $id || ! isset( self::$before[ $id ] ) ) {
			return;
		}
		$old = self::$before[ $id ];
		unset( self::$before[ $id ] );

		if ( ! StorePC_Push_Settings::get( 'auto_sale' ) || ! $product->is_on_sale( 'edit' ) ) {
			return;
		}
		$new_price = (float) $product->get_price( 'edit' );
		$regular   = (float) $product->get_regular_price( 'edit' );
		if ( $new_price <= 0 || $regular <= 0 ) {
			return;
		}

		$min_pct  = max( 0, (float) StorePC_Push_Settings::get( 'auto_sale_min_pct' ) );
		$discount = ( $regular - $new_price ) / $regular * 100;
		if ( $discount < $min_pct ) {
			return;
		}

		$just_started = '' === $old['sale'] || $old['price'] <= 0 || $old['price'] >= $regular;
		$dropped      = $old['price'] > 0 && $new_price < $old['price'];
		if ( ! $just_started && ! $dropped ) {
			return;
		}

		$parent_id = $product->get_parent_id() ? $product->get_parent_id() : $id;
		self::queue( 'sale', $parent_id, $id );
	}

	// ---------------------------------------------------------------- מוצר חדש

	public static function on_status_change( $new, $old, $post ) {
		if ( 'product' !== $post->post_type || 'publish' !== $new || 'publish' === $old ) {
			return;
		}
		if ( StorePC_Push_Settings::get( 'auto_new_product' ) ) {
			self::queue( 'new_product', $post->ID, $post->ID );
		}
	}

	// ---------------------------------------------------------------- קופון

	public static function coupon_metabox() {
		add_meta_box( 'storepc_push_coupon', 'התראת פוש לאפליקציה', array( __CLASS__, 'render_coupon_metabox' ), 'shop_coupon', 'side', 'high' );
	}

	public static function render_coupon_metabox( $post ) {
		wp_nonce_field( 'storepc_push_coupon', 'storepc_push_coupon_nonce' );
		$sent = get_post_meta( $post->ID, '_storepc_push_sent', true );
		if ( $sent ) {
			echo '<p>✅ נשלחה התראה בתאריך ' . esc_html( get_date_from_gmt( $sent, 'd/m/Y H:i' ) ) . '</p>';
			return;
		}
		if ( ! StorePC_Push_Settings::get( 'auto_coupon' ) ) {
			echo '<p>שליחה אוטומטית לקופונים כבויה בהגדרות התוסף.</p>';
			return;
		}
		$on  = get_post_meta( $post->ID, '_storepc_push_enabled', true );
		$url = get_post_meta( $post->ID, '_storepc_push_url', true );
		?>
		<p><label><input type="checkbox" name="storepc_push_enabled" value="1" <?php checked( $on, '1' ); ?>>
			לשלוח פוש לכל משתמשי האפליקציה כשהקופון מתפרסם</label></p>
		<p><label>קישור בלחיצה (לא חובה):<br>
			<input type="url" name="storepc_push_url" value="<?php echo esc_attr( $url ); ?>" style="width:100%" placeholder="<?php echo esc_attr( StorePC_Push_Settings::get( 'deals_url' ) ); ?>"></label></p>
		<p class="description">הטקסט נלקח מתיאור הקופון. אפשר לערוך את התבנית בהגדרות התוסף.</p>
		<?php
	}

	public static function save_coupon( $post_id, $post ) {
		if ( ! isset( $_POST['storepc_push_coupon_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['storepc_push_coupon_nonce'] ) ), 'storepc_push_coupon' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_storepc_push_enabled', empty( $_POST['storepc_push_enabled'] ) ? '0' : '1' );
		update_post_meta( $post_id, '_storepc_push_url', esc_url_raw( wp_unslash( $_POST['storepc_push_url'] ?? '' ) ) );

		if ( 'publish' === $post->post_status
			&& ! empty( $_POST['storepc_push_enabled'] )
			&& StorePC_Push_Settings::get( 'auto_coupon' )
			&& ! get_post_meta( $post_id, '_storepc_push_sent', true ) ) {
			update_post_meta( $post_id, '_storepc_push_sent', current_time( 'mysql', true ) );
			wp_schedule_single_event( time() + 5, self::CRON_HOOK, array( 'coupon', $post_id ) );
		}
	}

	// ---------------------------------------------------------------- תור ושליחה

	/**
	 * מוסיף שליחה אוטומטית לתור, עם הגנות כפילות וקצב.
	 */
	private static function queue( $trigger, $product_id, $source_id ) {
		$now = time();

		$dedupe_h = max( 0, (int) StorePC_Push_Settings::get( 'auto_dedupe_hours' ) );
		$last     = (int) get_post_meta( $product_id, self::META_LAST_PUSH, true );
		if ( $last && ( $now - $last ) < $dedupe_h * HOUR_IN_SECONDS ) {
			return;
		}

		$interval  = max( 0, (int) StorePC_Push_Settings::get( 'auto_min_interval' ) );
		$last_auto = (int) get_option( self::LAST_AUTO_OPT, 0 );
		if ( $last_auto && ( $now - $last_auto ) < $interval * MINUTE_IN_SECONDS ) {
			return;
		}

		update_post_meta( $product_id, self::META_LAST_PUSH, $now );
		update_option( self::LAST_AUTO_OPT, $now, false );

		$delay = max( 0, (int) StorePC_Push_Settings::get( 'auto_delay' ) ) * MINUTE_IN_SECONDS;
		wp_schedule_single_event( $now + $delay + 5, self::CRON_HOOK, array( $trigger, (int) $source_id ) );
	}

	private static function money( $v ) {
		$v = (float) $v;
		return floor( $v ) == $v ? number_format( $v, 0, '.', ',' ) : number_format( $v, 2, '.', ',' ); // phpcs:ignore
	}

	/**
	 * רץ ב-WP-Cron: בונה את ההודעה ושולח.
	 */
	public static function run( $trigger, $id ) {
		$s = StorePC_Push_Settings::all();

		if ( 'coupon' === $trigger ) {
			$coupon = get_post( $id );
			if ( ! $coupon || 'publish' !== $coupon->post_status ) {
				return;
			}
			$vars = array(
				'code'        => strtoupper( $coupon->post_title ),
				'description' => $coupon->post_excerpt ? $coupon->post_excerpt : 'הזינו את הקוד בקופה וקבלו הנחה',
			);
			$url = get_post_meta( $id, '_storepc_push_url', true );
			StorePC_Push_Sender::send_now(
				array(
					'title'        => StorePC_Push_Settings::fill( $s['tpl_coupon_title'], $vars ),
					'body'         => StorePC_Push_Settings::fill( $s['tpl_coupon_body'], $vars ),
					'url'          => $url ? $url : $s['deals_url'],
					'audience'     => 'deals',
					'trigger_type' => 'coupon',
					'ref_id'       => $id,
				)
			);
			return;
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}
		$product = wc_get_product( $id );
		if ( ! $product ) {
			return;
		}
		$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
		if ( ! $parent || 'publish' !== $parent->get_status() || 'hidden' === $parent->get_catalog_visibility() ) {
			return;
		}

		$price   = (float) $product->get_price();
		$regular = (float) $product->get_regular_price();
		if ( 'sale' === $trigger && ( ! $product->is_on_sale() || $price <= 0 ) ) {
			return; // המבצע בוטל בינתיים
		}

		$vars = array(
			'name'          => $parent->get_name(),
			'price'         => self::money( $price ),
			'regular_price' => self::money( $regular ),
			'discount'      => $regular > 0 ? round( ( $regular - $price ) / $regular * 100 ) : 0,
		);
		$image_id = $product->get_image_id() ? $product->get_image_id() : $parent->get_image_id();
		$image    = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';

		$is_sale = 'sale' === $trigger;
		StorePC_Push_Sender::send_now(
			array(
				'title'        => StorePC_Push_Settings::fill( $is_sale ? $s['tpl_sale_title'] : $s['tpl_new_title'], $vars ),
				'body'         => StorePC_Push_Settings::fill( $is_sale ? $s['tpl_sale_body'] : $s['tpl_new_body'], $vars ),
				'image'        => $image ? $image : '',
				'url'          => $parent->get_permalink(),
				'audience'     => $is_sale ? 'deals' : 'all',
				'trigger_type' => $trigger,
				'ref_id'       => $parent->get_id(),
			)
		);
	}
}
