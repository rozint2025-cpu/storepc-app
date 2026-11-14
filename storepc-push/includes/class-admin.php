<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * מסכי ניהול: שליחה, היסטוריה, הגדרות.
 */
class StorePC_Push_Admin {
	const SLUG = 'storepc-push';

	public static function cap() {
		return class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_storepc_push_send', array( __CLASS__, 'handle_send' ) );
		add_action( 'admin_post_storepc_push_settings', array( __CLASS__, 'handle_settings' ) );
		add_action( 'admin_post_storepc_push_cancel', array( __CLASS__, 'handle_cancel' ) );
		add_action( 'admin_post_storepc_push_test_connection', array( __CLASS__, 'handle_test_connection' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'product_row_action' ), 10, 2 );
		add_action( 'add_meta_boxes_product', array( __CLASS__, 'product_metabox' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	public static function menu() {
		add_menu_page( 'פוש לאפליקציה', 'פוש לאפליקציה', self::cap(), self::SLUG, array( __CLASS__, 'page_send' ), 'dashicons-smartphone', 56 );
		add_submenu_page( self::SLUG, 'שליחת התראה', 'שליחת התראה', self::cap(), self::SLUG, array( __CLASS__, 'page_send' ) );
		add_submenu_page( self::SLUG, 'היסטוריה', 'היסטוריה', self::cap(), self::SLUG . '-history', array( __CLASS__, 'page_history' ) );
		add_submenu_page( self::SLUG, 'הגדרות', 'הגדרות ואוטומציות', self::cap(), self::SLUG . '-settings', array( __CLASS__, 'page_settings' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'storepc-push-admin', STOREPC_PUSH_URL . 'assets/admin.css', array(), STOREPC_PUSH_VERSION );
		wp_enqueue_script( 'storepc-push-admin', STOREPC_PUSH_URL . 'assets/admin.js', array( 'jquery' ), STOREPC_PUSH_VERSION, true );
	}

	private static function page_url( $page = '', $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG . ( $page ? '-' . $page : '' ) ), $args ), admin_url( 'admin.php' ) );
	}

	private static function redirect( $page, $type, $msg ) {
		set_transient( 'storepc_push_notice_' . get_current_user_id(), array( $type, $msg ), 60 );
		wp_safe_redirect( self::page_url( $page ) );
		exit;
	}

	public static function notices() {
		$key = 'storepc_push_notice_' . get_current_user_id();
		$n   = get_transient( $key );
		if ( ! $n ) {
			return;
		}
		delete_transient( $key );
		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $n[0] ), esc_html( $n[1] ) );
	}

	private static function check( $action ) {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( 'אין הרשאה' );
		}
		check_admin_referer( $action );
	}

	// ================================================================ שליחה

	public static function page_send() {
		$s        = StorePC_Push_Settings::all();
		$stats    = StorePC_Push_Rest::device_stats();
		$prefill  = array( 'title' => '', 'body' => '', 'image' => '', 'url' => '' );
		$product  = isset( $_GET['product_id'] ) && function_exists( 'wc_get_product' ) ? wc_get_product( absint( $_GET['product_id'] ) ) : null; // phpcs:ignore
		if ( $product ) {
			$price   = (float) $product->get_price();
			$regular = (float) $product->get_regular_price();
			$prefill = array(
				'title' => $product->is_on_sale() ? '🔥 מבצע: ' . $product->get_name() : $product->get_name(),
				'body'  => $product->is_on_sale() && $regular > 0
					? sprintf( 'עכשיו ב-₪%s במקום ₪%s', number_format( $price ), number_format( $regular ) )
					: sprintf( 'זמין עכשיו באתר ב-₪%s', number_format( $price ) ),
				'image' => $product->get_image_id() ? (string) wp_get_attachment_image_url( $product->get_image_id(), 'large' ) : '',
				'url'   => $product->get_permalink(),
			);
		}
		?>
		<div class="wrap spp-wrap" dir="rtl">
			<h1>שליחת התראת פוש לאפליקציה</h1>

			<?php if ( ! StorePC_Push_FCM::is_configured() ) : ?>
				<div class="notice notice-warning"><p>עדיין לא חובר Firebase. <a href="<?php echo esc_url( self::page_url( 'settings' ) ); ?>">להגדרות ←</a></p></div>
			<?php endif; ?>

			<div class="spp-stats">
				<div><strong><?php echo esc_html( number_format( $stats['total'] ) ); ?></strong><span>מכשירים רשומים</span></div>
				<div><strong><?php echo esc_html( number_format( $stats['active'] ) ); ?></strong><span>פעילים ב-30 יום</span></div>
				<div><strong><?php echo esc_html( number_format( $stats['android'] ) ); ?></strong><span>Android</span></div>
				<div><strong><?php echo esc_html( number_format( $stats['ios'] ) ); ?></strong><span>iPhone</span></div>
			</div>

			<div class="spp-grid">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spp-card">
					<?php wp_nonce_field( 'storepc_push_send' ); ?>
					<input type="hidden" name="action" value="storepc_push_send">

					<p><label for="spp-title"><strong>כותרת</strong></label>
						<input type="text" id="spp-title" name="title" maxlength="65" required class="widefat" value="<?php echo esc_attr( $prefill['title'] ); ?>" placeholder="לדוגמה: 🔥 סוף שבוע של מבצעים!"></p>

					<p><label for="spp-body"><strong>תוכן ההודעה</strong></label>
						<textarea id="spp-body" name="body" rows="3" maxlength="240" class="widefat" placeholder="עד 20% הנחה על מחשבים ניידים – רק עד מוצאי שבת"><?php echo esc_textarea( $prefill['body'] ); ?></textarea></p>

					<p><label for="spp-image"><strong>תמונה (לא חובה)</strong></label>
						<span class="spp-row">
							<input type="url" id="spp-image" name="image" class="widefat" value="<?php echo esc_attr( $prefill['image'] ); ?>" placeholder="https://...">
							<button type="button" class="button" id="spp-pick-image">בחירה מהספרייה</button>
						</span>
						<span class="description">מומלץ יחס 2:1, עד 1MB.</span></p>

					<p><label for="spp-url"><strong>לאן הלחיצה מובילה?</strong></label>
						<input type="text" dir="ltr" id="spp-url" name="url" class="widefat" value="<?php echo esc_attr( $prefill['url'] ); ?>" placeholder="<?php echo esc_attr( $s['deals_url'] ); ?>">
						<span class="description">קישור לעמוד מוצר, קטגוריה או עמוד המבצעים. ריק = דף הבית.
						טיפ: ברשימת המוצרים יש קישור "שליחת פוש" שממלא הכול אוטומטית.</span></p>

					<p><strong>למי לשלוח?</strong><br>
						<label><input type="radio" name="audience" value="all" checked> כל משתמשי האפליקציה</label><br>
						<label><input type="radio" name="audience" value="deals"> רק מי שהפעיל התראות על מבצעים</label></p>

					<p><strong>מתי?</strong><br>
						<label><input type="radio" name="when" value="now" checked> עכשיו</label>
						<label style="margin-inline-start:16px"><input type="radio" name="when" value="later"> בזמן מתוזמן:</label>
						<input type="datetime-local" name="send_at" id="spp-send-at"></p>

					<p class="spp-actions">
						<button type="submit" name="mode" value="send" class="button button-primary button-hero">שליחה</button>
						<?php if ( $s['test_token'] ) : ?>
							<button type="submit" name="mode" value="test" class="button">שליחת בדיקה למכשיר שלי</button>
						<?php endif; ?>
					</p>
				</form>

				<div class="spp-preview-wrap">
					<p><strong>תצוגה מקדימה</strong></p>
					<div class="spp-phone">
						<div class="spp-notif">
							<div class="spp-notif-head"><span class="spp-dot"></span> סטור פיסי · עכשיו</div>
							<div class="spp-notif-title" id="spp-prev-title">כותרת ההתראה</div>
							<div class="spp-notif-body" id="spp-prev-body">תוכן ההתראה יופיע כאן</div>
							<img id="spp-prev-img" alt="" style="display:none">
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public static function handle_send() {
		self::check( 'storepc_push_send' );
		$p = wp_unslash( $_POST );

		$args = array(
			'title'        => sanitize_text_field( $p['title'] ?? '' ),
			'body'         => sanitize_textarea_field( $p['body'] ?? '' ),
			'image'        => esc_url_raw( $p['image'] ?? '' ),
			'url'          => esc_url_raw( $p['url'] ?? '' ),
			'audience'     => ( $p['audience'] ?? '' ) === 'deals' ? 'deals' : 'all',
			'trigger_type' => 'manual',
		);
		if ( '' === $args['title'] ) {
			self::redirect( '', 'error', 'חובה למלא כותרת.' );
		}
		if ( '' === $args['url'] ) {
			$args['url'] = StorePC_Push_Settings::get( 'home_url' );
		}

		if ( ( $p['mode'] ?? '' ) === 'test' ) {
			$args['audience']     = 'token';
			$args['token']        = StorePC_Push_Settings::get( 'test_token' );
			$args['trigger_type'] = 'test';
			list( , $res )        = StorePC_Push_Sender::send_now( $args );
			is_wp_error( $res )
				? self::redirect( '', 'error', 'הבדיקה נכשלה: ' . $res->get_error_message() )
				: self::redirect( '', 'success', 'נשלחה התראת בדיקה למכשיר שלך.' );
		}

		if ( ( $p['when'] ?? 'now' ) === 'later' ) {
			$local = sanitize_text_field( $p['send_at'] ?? '' );
			try {
				$dt = new DateTimeImmutable( $local, wp_timezone() );
			} catch ( Exception $e ) {
				$dt = null;
			}
			if ( ! $local || ! $dt || $dt->getTimestamp() < time() + 60 ) {
				self::redirect( '', 'error', 'בחרו תאריך ושעה עתידיים לתזמון.' );
			}
			StorePC_Push_Sender::schedule( $args, $dt->getTimestamp() );
			self::redirect( 'history', 'success', 'ההתראה תוזמנה ל-' . $dt->format( 'd/m/Y H:i' ) . '.' );
		}

		list( , $res ) = StorePC_Push_Sender::send_now( $args );
		is_wp_error( $res )
			? self::redirect( '', 'error', 'השליחה נכשלה: ' . $res->get_error_message() )
			: self::redirect( 'history', 'success', 'ההתראה נשלחה בהצלחה!' );
	}

	// ================================================================ היסטוריה

	public static function page_history() {
		global $wpdb;
		$table    = StorePC_Push_Sender::table();
		$per_page = 30;
		$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore
		$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ); // phpcs:ignore
		$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, ( $paged - 1 ) * $per_page ), ARRAY_A ); // phpcs:ignore

		$triggers = array(
			'manual'      => 'ידני',
			'test'        => 'בדיקה',
			'sale'        => 'אוטומטי – ירידת מחיר',
			'new_product' => 'אוטומטי – מוצר חדש',
			'coupon'      => 'אוטומטי – קופון',
		);
		$statuses = array(
			'sent'      => array( 'נשלח', 'ok' ),
			'failed'    => array( 'נכשל', 'err' ),
			'scheduled' => array( 'מתוזמן', 'wait' ),
			'pending'   => array( 'בתהליך', 'wait' ),
			'canceled'  => array( 'בוטל', 'muted' ),
		);
		$audiences = array(
			'all'   => 'כולם',
			'deals' => 'מנויי מבצעים',
			'token' => 'מכשיר בדיקה',
		);
		?>
		<div class="wrap spp-wrap" dir="rtl">
			<h1>היסטוריית התראות</h1>
			<table class="widefat striped spp-table">
				<thead><tr><th>תאריך</th><th>התראה</th><th>קהל</th><th>מקור</th><th>סטטוס</th><th></th></tr></thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="6">עדיין לא נשלחו התראות.</td></tr>
				<?php endif; ?>
				<?php foreach ( (array) $rows as $r ) : ?>
					<?php
					$when = $r['sent_at'] ? $r['sent_at'] : ( $r['scheduled_at'] ? $r['scheduled_at'] : $r['created_at'] );
					$st   = $statuses[ $r['status'] ] ?? array( $r['status'], 'muted' );
					?>
					<tr>
						<td><?php echo esc_html( get_date_from_gmt( $when, 'd/m/Y H:i' ) ); ?></td>
						<td>
							<strong><?php echo esc_html( $r['title'] ); ?></strong><br>
							<span class="spp-muted"><?php echo esc_html( $r['body'] ); ?></span>
							<?php if ( $r['url'] ) : ?><br><a href="<?php echo esc_url( $r['url'] ); ?>" target="_blank" class="spp-small"><?php echo esc_html( urldecode( $r['url'] ) ); ?></a><?php endif; ?>
						</td>
						<td><?php echo esc_html( $audiences[ $r['audience'] ] ?? $r['audience'] ); ?></td>
						<td><?php echo esc_html( $triggers[ $r['trigger_type'] ] ?? $r['trigger_type'] ); ?></td>
						<td><span class="spp-badge spp-<?php echo esc_attr( $st[1] ); ?>" title="<?php echo esc_attr( (string) $r['response'] ); ?>"><?php echo esc_html( $st[0] ); ?></span>
							<?php if ( 'failed' === $r['status'] ) : ?><div class="spp-small spp-muted"><?php echo esc_html( mb_substr( (string) $r['response'], 0, 160 ) ); ?></div><?php endif; ?>
						</td>
						<td>
							<?php if ( 'scheduled' === $r['status'] ) : ?>
								<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=storepc_push_cancel&id=' . (int) $r['id'] ), 'storepc_push_cancel' ) ); ?>">ביטול</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			$pages = (int) ceil( $total / $per_page );
			if ( $pages > 1 ) {
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo paginate_links( // phpcs:ignore
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => $pages,
					)
				);
				echo '</div></div>';
			}
			?>
		</div>
		<?php
	}

	public static function handle_cancel() {
		self::check( 'storepc_push_cancel' );
		StorePC_Push_Sender::cancel( absint( $_GET['id'] ?? 0 ) );
		self::redirect( 'history', 'success', 'ההתראה המתוזמנת בוטלה.' );
	}

	// ================================================================ הגדרות

	public static function page_settings() {
		$s     = StorePC_Push_Settings::all();
		$cred  = StorePC_Push_FCM::credentials();
		$field = function ( $key, $label, $type = 'text', $desc = '' ) use ( $s ) {
			echo '<tr><th><label for="spp-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
			if ( 'checkbox' === $type ) {
				echo '<label><input type="checkbox" id="spp-' . esc_attr( $key ) . '" name="s[' . esc_attr( $key ) . ']" value="1" ' . checked( ! empty( $s[ $key ] ), true, false ) . '> ' . esc_html( $desc ) . '</label>';
			} else {
				$cls = 'number' === $type ? 'small-text' : 'regular-text';
				echo '<input type="' . esc_attr( $type ) . '" id="spp-' . esc_attr( $key ) . '" class="' . esc_attr( $cls ) . '" name="s[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $s[ $key ] ) . '" dir="auto">';
				if ( $desc ) {
					echo '<p class="description">' . esc_html( $desc ) . '</p>';
				}
			}
			echo '</td></tr>';
		};
		?>
		<div class="wrap spp-wrap" dir="rtl">
			<h1>הגדרות פוש לאפליקציה</h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'storepc_push_settings' ); ?>
				<input type="hidden" name="action" value="storepc_push_settings">

				<h2>חיבור ל-Firebase</h2>
				<table class="form-table">
					<tr><th>סטטוס</th><td>
						<?php if ( is_wp_error( $cred ) ) : ?>
							<span class="spp-badge spp-err">לא מחובר</span>
						<?php else : ?>
							<span class="spp-badge spp-ok">מחובר לפרויקט <?php echo esc_html( $cred['project_id'] ); ?></span>
							<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=storepc_push_test_connection' ), 'storepc_push_test_connection' ) ); ?>">בדיקת חיבור</a>
						<?php endif; ?>
					</td></tr>
					<tr><th><label for="spp-sa">Service Account (JSON)</label></th><td>
						<?php if ( defined( 'STOREPC_PUSH_SERVICE_ACCOUNT_FILE' ) ) : ?>
							<p>המפתח נטען מקובץ שהוגדר ב-wp-config.php.</p>
						<?php else : ?>
							<textarea id="spp-sa" name="service_account" rows="5" class="large-text code" dir="ltr" placeholder="<?php echo is_wp_error( $cred ) ? '{ &quot;type&quot;: &quot;service_account&quot;, ... }' : 'המפתח שמור. הדביקו מפתח חדש רק כדי להחליף אותו.'; ?>"></textarea>
							<p class="description">Firebase Console ← הגדרות הפרויקט ← Service accounts ← Generate new private key. הדביקו כאן את כל תוכן הקובץ.</p>
						<?php endif; ?>
					</td></tr>
					<?php $field( 'test_token', 'טוקן מכשיר לבדיקות', 'text', 'באפליקציה: עוד ← לחיצה ארוכה על מספר הגרסה ← העתקה. מאפשר "שליחת בדיקה" רק לטלפון שלך.' ); ?>
				</table>

				<h2>אוטומציות</h2>
				<table class="form-table">
					<?php
					$field( 'auto_sale', 'ירידת מחיר / מבצע', 'checkbox', 'לשלוח פוש למנויי המבצעים כשמוצר נכנס למבצע או שמחירו יורד' );
					$field( 'auto_sale_min_pct', 'הנחה מינימלית (%)', 'number', 'לא לשלוח על הנחות קטנות מזה.' );
					$field( 'auto_new_product', 'מוצר חדש', 'checkbox', 'לשלוח פוש לכולם כשמוצר חדש מתפרסם' );
					$field( 'auto_coupon', 'קופונים', 'checkbox', 'להציג במסך עריכת קופון אפשרות "שלח פוש כשמתפרסם"' );
					$field( 'auto_dedupe_hours', 'לא לחזור על אותו מוצר (שעות)', 'number', 'מונע כמה התראות על אותו מוצר.' );
					$field( 'auto_min_interval', 'מרווח מינימלי בין התראות אוטומטיות (דקות)', 'number', 'מגן מהצפה בעדכון מחירים גורף או ייבוא.' );
					$field( 'auto_delay', 'השהיה לפני שליחה (דקות)', 'number', 'זמן לתקן טעות לפני שההתראה יוצאת.' );
					?>
				</table>

				<h2>תבניות הודעה</h2>
				<p class="description">משתנים: {name} שם מוצר, {price} מחיר, {regular_price} מחיר לפני הנחה, {discount} אחוז הנחה, {code} קוד קופון, {description} תיאור קופון.</p>
				<table class="form-table">
					<?php
					$field( 'tpl_sale_title', 'ירידת מחיר – כותרת' );
					$field( 'tpl_sale_body', 'ירידת מחיר – תוכן' );
					$field( 'tpl_new_title', 'מוצר חדש – כותרת' );
					$field( 'tpl_new_body', 'מוצר חדש – תוכן' );
					$field( 'tpl_coupon_title', 'קופון – כותרת' );
					$field( 'tpl_coupon_body', 'קופון – תוכן' );
					?>
				</table>

				<h2>האפליקציה</h2>
				<p class="description">הכתובות של הלשוניות בתפריט התחתון. שינוי כאן מתעדכן באפליקציה בפתיחה הבאה, בלי גרסה חדשה.</p>
				<table class="form-table">
					<?php
					$field( 'home_url', 'בית', 'text' );
					$field( 'deals_url', 'מבצעים', 'text' );
					$field( 'cart_url', 'עגלה', 'text' );
					$field( 'account_url', 'החשבון שלי', 'text' );
					?>
					<tr><th><label for="spp-css">CSS לאפליקציה בלבד</label></th><td>
						<textarea id="spp-css" name="s[custom_css]" rows="6" class="large-text code" dir="ltr" placeholder="/* דוגמה: הסתרת כפתור וואטסאפ צף ופוטר */&#10;.joinchat, footer { display:none !important; }"><?php echo esc_textarea( $s['custom_css'] ); ?></textarea>
						<p class="description">מוחל רק בתוך האפליקציה. בנוסף, לכל עמוד באפליקציה מתווסף class בשם <code>storepc-app</code> על תגית html.</p>
					</td></tr>
				</table>

				<?php submit_button( 'שמירת הגדרות' ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_settings() {
		self::check( 'storepc_push_settings' );
		$in       = isset( $_POST['s'] ) && is_array( $_POST['s'] ) ? wp_unslash( $_POST['s'] ) : array(); // phpcs:ignore
		$defaults = StorePC_Push_Settings::defaults();
		$out      = array();

		foreach ( array( 'auto_sale', 'auto_new_product', 'auto_coupon' ) as $k ) {
			$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
		foreach ( array( 'auto_sale_min_pct', 'auto_dedupe_hours', 'auto_min_interval', 'auto_delay' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? max( 0, (int) $in[ $k ] ) : $defaults[ $k ];
		}
		foreach ( array( 'tpl_sale_title', 'tpl_sale_body', 'tpl_new_title', 'tpl_new_body', 'tpl_coupon_title', 'tpl_coupon_body', 'test_token' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : $defaults[ $k ];
		}
		foreach ( array( 'home_url', 'deals_url', 'cart_url', 'account_url' ) as $k ) {
			$v         = isset( $in[ $k ] ) ? esc_url_raw( trim( $in[ $k ] ) ) : '';
			$out[ $k ] = $v ? $v : $defaults[ $k ];
		}
		$out['custom_css'] = isset( $in['custom_css'] ) ? wp_strip_all_tags( $in['custom_css'] ) : '';

		$sa = isset( $_POST['service_account'] ) ? trim( wp_unslash( $_POST['service_account'] ) ) : ''; // phpcs:ignore
		if ( '' !== $sa ) {
			$json = json_decode( $sa, true );
			if ( ! is_array( $json ) || empty( $json['private_key'] ) || empty( $json['client_email'] ) || empty( $json['project_id'] ) ) {
				StorePC_Push_Settings::save( $out );
				self::redirect( 'settings', 'error', 'שאר ההגדרות נשמרו, אבל קובץ ה-Service Account לא תקין: הדביקו את כל תוכן קובץ ה-JSON.' );
			}
			$out['service_account'] = wp_json_encode( $json );
			delete_transient( StorePC_Push_FCM::TOKEN_TRANSIENT );
		}

		StorePC_Push_Settings::save( $out );
		self::redirect( 'settings', 'success', 'ההגדרות נשמרו.' );
	}

	public static function handle_test_connection() {
		self::check( 'storepc_push_test_connection' );
		delete_transient( StorePC_Push_FCM::TOKEN_TRANSIENT );
		$t = StorePC_Push_FCM::access_token();
		is_wp_error( $t )
			? self::redirect( 'settings', 'error', 'החיבור נכשל: ' . $t->get_error_message() )
			: self::redirect( 'settings', 'success', 'החיבור ל-Firebase תקין.' );
	}

	// ================================================================ מוצרים

	public static function product_row_action( $actions, $post ) {
		if ( 'product' === $post->post_type && 'publish' === $post->post_status && current_user_can( self::cap() ) ) {
			$actions['storepc_push'] = '<a href="' . esc_url( self::page_url( '', array( 'product_id' => $post->ID ) ) ) . '">📲 שליחת פוש</a>';
		}
		return $actions;
	}

	public static function product_metabox( $post ) {
		if ( 'publish' !== $post->post_status || ! current_user_can( self::cap() ) ) {
			return;
		}
		add_meta_box(
			'storepc_push_product',
			'פוש לאפליקציה',
			function ( $post ) {
				echo '<p>שליחת התראה על המוצר הזה לכל משתמשי האפליקציה.</p>';
				echo '<a class="button button-primary" href="' . esc_url( self::page_url( '', array( 'product_id' => $post->ID ) ) ) . '">📲 שליחת פוש על המוצר</a>';
			},
			'product',
			'side',
			'default'
		);
	}
}
