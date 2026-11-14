import 'dart:async';
import 'dart:io';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../config.dart';
import 'api_service.dart';

/// ניהול התראות פוש: הרשאות, נושאים (Topics), רישום טוקן ולחיצה על התראה.
class PushService {
  PushService._();
  static final PushService instance = PushService._();

  final _fcm = FirebaseMessaging.instance;

  /// נפתחת כתובת כשהמשתמש לוחץ על התראה.
  final StreamController<String> _openUrl = StreamController<String>.broadcast();
  Stream<String> get onOpenUrl => _openUrl.stream;

  /// התראה שהגיעה בזמן שהאפליקציה פתוחה.
  final StreamController<RemoteMessage> _foreground = StreamController<RemoteMessage>.broadcast();
  Stream<RemoteMessage> get onForegroundMessage => _foreground.stream;

  String? token;
  static const _kDeals = 'push_deals_enabled';

  Future<void> init() async {
    await _fcm.requestPermission(alert: true, badge: true, sound: true);

    // ב-iOS: להציג התראות גם כשהאפליקציה פתוחה
    await _fcm.setForegroundNotificationPresentationOptions(alert: true, badge: true, sound: true);

    // ב-iOS חייבים טוקן APNs לפני הרשמה לנושאים
    if (Platform.isIOS) {
      String? apns;
      for (var i = 0; i < 10 && apns == null; i++) {
        apns = await _fcm.getAPNSToken();
        if (apns == null) await Future.delayed(const Duration(seconds: 1));
      }
    }

    try {
      await _fcm.subscribeToTopic(AppConfig.topicAll);
      if (await dealsEnabled()) {
        await _fcm.subscribeToTopic(AppConfig.topicDeals);
      }
    } catch (_) {}

    try {
      token = await _fcm.getToken();
      if (token != null) await ApiService.registerDevice(token!);
    } catch (_) {}
    _fcm.onTokenRefresh.listen((t) {
      token = t;
      ApiService.registerDevice(t);
    });

    FirebaseMessaging.onMessage.listen(_foreground.add);
    FirebaseMessaging.onMessageOpenedApp.listen(_handleOpen);
  }

  /// אם האפליקציה נפתחה מלחיצה על התראה (כשהייתה סגורה לגמרי).
  Future<String?> initialUrl() async {
    final msg = await _fcm.getInitialMessage();
    return msg == null ? null : urlOf(msg);
  }

  void _handleOpen(RemoteMessage m) {
    final url = urlOf(m);
    if (url != null) _openUrl.add(url);
  }

  static String? urlOf(RemoteMessage m) {
    final u = m.data['url'];
    return (u is String && u.startsWith('http')) ? u : null;
  }

  Future<bool> dealsEnabled() async {
    final p = await SharedPreferences.getInstance();
    return p.getBool(_kDeals) ?? true;
  }

  Future<void> setDealsEnabled(bool v) async {
    final p = await SharedPreferences.getInstance();
    await p.setBool(_kDeals, v);
    try {
      if (v) {
        await _fcm.subscribeToTopic(AppConfig.topicDeals);
      } else {
        await _fcm.unsubscribeFromTopic(AppConfig.topicDeals);
      }
    } catch (_) {}
  }
}
