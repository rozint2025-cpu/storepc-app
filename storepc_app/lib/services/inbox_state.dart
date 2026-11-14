import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api_service.dart';

/// סופר התראות שלא נקראו (לתג האדום על הלשונית "עוד").
class InboxState {
  InboxState._();
  static final InboxState instance = InboxState._();

  static const _kLastSeen = 'inbox_last_seen_id';
  final ValueNotifier<int> unread = ValueNotifier<int>(0);
  List<StoreNotification> items = [];

  Future<List<StoreNotification>> refresh() async {
    items = await ApiService.fetchNotifications();
    final p = await SharedPreferences.getInstance();
    final lastSeen = p.getInt(_kLastSeen) ?? 0;
    unread.value = items.where((n) => n.id > lastSeen).length;
    return items;
  }

  Future<void> refreshSilently() async {
    try {
      await refresh();
    } catch (_) {}
  }

  Future<void> markAllRead() async {
    if (items.isEmpty) return;
    final maxId = items.map((n) => n.id).reduce((a, b) => a > b ? a : b);
    final p = await SharedPreferences.getInstance();
    await p.setInt(_kLastSeen, maxId);
    unread.value = 0;
  }
}
