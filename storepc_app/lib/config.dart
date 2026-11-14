import 'package:flutter/material.dart';

/// כל ההגדרות של האפליקציה במקום אחד.
/// כתובות הלשוניות נטענות גם מהשרת (תוסף WordPress), כך שאפשר לשנות אותן
/// בלי לבנות גרסה חדשה. הערכים כאן הם ברירת מחדל.
class AppConfig {
  static const String appName = 'סטור פיסי';
  static const String baseUrl = 'https://www.store-pc.co.il';
  static const String siteHost = 'store-pc.co.il';

  /// סימון שמתווסף ל-User-Agent כדי שהאתר ידע שהגלישה מהאפליקציה.
  static const String userAgentSuffix = 'StorePCApp/1.0';

  // כתובות ברירת מחדל ללשוניות
  static String homeUrl = '$baseUrl/';
  static String dealsUrl = '$baseUrl/מבצעים/';
  static String cartUrl = '$baseUrl/cart/';
  static String accountUrl = '$baseUrl/my-account/';
  static String clubUrl = '$baseUrl/מועדון-החדש/';
  static String contactUrl = '$baseUrl/contact-us/';
  static String labUrl = '$baseUrl/שירותי-מעבדה/';
  static String quoteUrl = '$baseUrl/בקשה-להצעה/';

  /// CSS שמוזרק לכל עמוד באפליקציה (למשל להסתרת כפתור וואטסאפ צף או פוטר).
  /// ניתן לעריכה מתוך התוסף בוורדפרס.
  static String injectedCss = '';

  // REST API של תוסף הפוש בוורדפרס
  static const String apiBase = '$baseUrl/wp-json/storepc-push/v1';

  // נושאי פוש (Topics) ב-Firebase
  static const String topicAll = 'all';
  static const String topicDeals = 'deals';

  // פרטי קשר
  static const String phone = '1800015255';
  static const String phoneDisplay = '1800-015-255';
  static const String shortDial = '*8756';
  static const String whatsappUrl = 'https://wa.me/message/BSUVXTBRWMGDN1';
  static const String email = 'service@store-pc.co.il';

  // צבעים (כחול – צבע המותג)
  static const Color primary = Color(0xFF0D47A1);
  static const Color primaryLight = Color(0xFF1E88E5);
  static const Color background = Color(0xFFF5F8FC);

  /// דומיינים שנפתחים מחוץ לאפליקציה (באפליקציה המתאימה בטלפון).
  /// כל השאר – כולל דפי תשלום של חברות סליקה – נשאר בתוך האפליקציה,
  /// כדי שהקופה תעבוד.
  static const List<String> externalHosts = [
    'wa.me',
    'api.whatsapp.com',
    'chat.whatsapp.com',
    'web.whatsapp.com',
    'facebook.com',
    'm.facebook.com',
    'instagram.com',
    'tiktok.com',
    'youtube.com',
    'youtu.be',
    'waze.com',
    'ul.waze.com',
    'maps.google.com',
    'goo.gl',
    'maps.app.goo.gl',
    'play.google.com',
    'apps.apple.com',
    'linkedin.com',
    'twitter.com',
    'x.com',
  ];
}
