import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import '../config.dart';

/// הודעה שנשלחה מהחנות (מוצגת במסך "ההתראות שלי").
class StoreNotification {
  final int id;
  final String title;
  final String body;
  final String image;
  final String url;
  final DateTime? sentAt;

  StoreNotification({
    required this.id,
    required this.title,
    required this.body,
    required this.image,
    required this.url,
    this.sentAt,
  });

  factory StoreNotification.fromJson(Map<String, dynamic> j) => StoreNotification(
        id: int.tryParse('${j['id']}') ?? 0,
        title: '${j['title'] ?? ''}',
        body: '${j['body'] ?? ''}',
        image: '${j['image'] ?? ''}',
        url: '${j['url'] ?? ''}',
        sentAt: DateTime.tryParse('${j['sent_at'] ?? ''}'),
      );
}

/// תקשורת עם תוסף "StorePC Push" בוורדפרס.
class ApiService {
  static const _timeout = Duration(seconds: 12);

  /// טוען הגדרות מהשרת (כתובות לשוניות + CSS להזרקה). שגיאה = נשארים עם ברירת המחדל.
  static Future<void> loadRemoteConfig() async {
    try {
      final res = await http.get(Uri.parse('${AppConfig.apiBase}/config')).timeout(_timeout);
      if (res.statusCode != 200) return;
      final j = jsonDecode(res.body) as Map<String, dynamic>;
      String pick(String key, String current) {
        final v = '${j[key] ?? ''}'.trim();
        return v.startsWith('http') ? v : current;
      }

      AppConfig.homeUrl = pick('home_url', AppConfig.homeUrl);
      AppConfig.dealsUrl = pick('deals_url', AppConfig.dealsUrl);
      AppConfig.cartUrl = pick('cart_url', AppConfig.cartUrl);
      AppConfig.accountUrl = pick('account_url', AppConfig.accountUrl);
      AppConfig.injectedCss = '${j['custom_css'] ?? ''}';
    } catch (_) {}
  }

  /// רישום המכשיר בשרת (לסטטיסטיקה ולשליחות בדיקה).
  static Future<void> registerDevice(String token) async {
    try {
      await http
          .post(
            Uri.parse('${AppConfig.apiBase}/devices'),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({
              'token': token,
              'platform': Platform.isIOS ? 'ios' : 'android',
              'app_version': '1.0.0',
            }),
          )
          .timeout(_timeout);
    } catch (_) {}
  }

  /// רשימת ההתראות האחרונות שנשלחו.
  static Future<List<StoreNotification>> fetchNotifications() async {
    final res = await http.get(Uri.parse('${AppConfig.apiBase}/notifications')).timeout(_timeout);
    if (res.statusCode != 200) {
      throw Exception('HTTP ${res.statusCode}');
    }
    final list = jsonDecode(res.body) as List<dynamic>;
    return list.map((e) => StoreNotification.fromJson(e as Map<String, dynamic>)).toList();
  }
}
