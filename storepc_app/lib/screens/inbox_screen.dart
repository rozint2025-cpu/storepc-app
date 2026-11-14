import 'package:flutter/material.dart';

import '../config.dart';
import '../services/api_service.dart';
import '../services/inbox_state.dart';

/// "ההתראות שלי" – כל המבצעים וההודעות שנשלחו לאחרונה.
/// בלחיצה על התראה עם קישור – חוזרים למסך הראשי ופותחים את הקישור.
class InboxScreen extends StatefulWidget {
  const InboxScreen({super.key});

  @override
  State<InboxScreen> createState() => _InboxScreenState();
}

class _InboxScreenState extends State<InboxScreen> {
  late Future<List<StoreNotification>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<StoreNotification>> _load() async {
    final items = await InboxState.instance.refresh();
    await InboxState.instance.markAllRead();
    return items;
  }

  String _date(DateTime? d) {
    if (d == null) return '';
    final l = d.toLocal();
    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(l.day)}/${two(l.month)}/${l.year} ${two(l.hour)}:${two(l.minute)}';
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppConfig.background,
        appBar: AppBar(
          title: const Text('ההתראות שלי'),
          backgroundColor: AppConfig.primary,
          foregroundColor: Colors.white,
        ),
        body: RefreshIndicator(
          onRefresh: () async {
            setState(() => _future = _load());
            await _future;
          },
          child: FutureBuilder<List<StoreNotification>>(
            future: _future,
            builder: (context, snap) {
              if (snap.connectionState != ConnectionState.done) {
                return const Center(child: CircularProgressIndicator());
              }
              if (snap.hasError) {
                return _message(Icons.cloud_off, 'לא הצלחנו לטעון את ההתראות', 'משכו למטה כדי לנסות שוב');
              }
              final items = snap.data ?? [];
              if (items.isEmpty) {
                return _message(Icons.notifications_none, 'אין עדיין התראות',
                    'כאן יופיעו מבצעים, קופונים והטבות');
              }
              return ListView.separated(
                padding: const EdgeInsets.all(12),
                itemCount: items.length,
                separatorBuilder: (_, __) => const SizedBox(height: 10),
                itemBuilder: (_, i) => _card(items[i]),
              );
            },
          ),
        ),
      ),
    );
  }

  Widget _card(StoreNotification n) {
    return Card(
      clipBehavior: Clip.antiAlias,
      elevation: 0,
      color: Colors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: InkWell(
        onTap: n.url.isEmpty ? null : () => Navigator.of(context).pop(n.url),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (n.image.isNotEmpty)
              AspectRatio(
                aspectRatio: 2,
                child: Image.network(n.image, fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => const SizedBox.shrink()),
              ),
            Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(n.title, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                  if (n.body.isNotEmpty) ...[
                    const SizedBox(height: 4),
                    Text(n.body, style: const TextStyle(color: Colors.black87)),
                  ],
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Text(_date(n.sentAt), style: const TextStyle(fontSize: 12, color: Colors.black45)),
                      const Spacer(),
                      if (n.url.isNotEmpty)
                        const Text('לצפייה ←',
                            style: TextStyle(color: AppConfig.primary, fontWeight: FontWeight.w600)),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _message(IconData icon, String title, String sub) => ListView(
        children: [
          const SizedBox(height: 120),
          Icon(icon, size: 64, color: AppConfig.primary),
          const SizedBox(height: 12),
          Text(title, textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          const SizedBox(height: 6),
          Text(sub, textAlign: TextAlign.center, style: const TextStyle(color: Colors.black54)),
        ],
      );
}
