import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config.dart';
import '../services/inbox_state.dart';
import '../services/push_service.dart';

/// תפריט "עוד": התראות, קישורים, יצירת קשר והגדרות פוש.
/// בלחיצה על קישור לאתר – מחזיר את הכתובת ל-HomeShell דרך Navigator.pop.
class MoreSheet extends StatefulWidget {
  final VoidCallback onOpenInbox;
  final VoidCallback onReload;
  const MoreSheet({super.key, required this.onOpenInbox, required this.onReload});

  @override
  State<MoreSheet> createState() => _MoreSheetState();
}

class _MoreSheetState extends State<MoreSheet> {
  bool _deals = true;

  @override
  void initState() {
    super.initState();
    PushService.instance.dealsEnabled().then((v) {
      if (mounted) setState(() => _deals = v);
    });
  }

  void _go(String url) => Navigator.of(context).pop(url);

  Future<void> _ext(String url) async {
    try {
      await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
    } catch (_) {}
  }

  /// לחיצה ארוכה על מספר הגרסה מציגה את טוקן המכשיר (לשליחת בדיקה מהתוסף).
  void _showToken() {
    final t = PushService.instance.token ?? 'אין טוקן (פוש לא פעיל)';
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('טוקן מכשיר'),
        content: SelectableText(t, style: const TextStyle(fontSize: 12)),
        actions: [
          TextButton(
            onPressed: () {
              Clipboard.setData(ClipboardData(text: t));
              Navigator.of(context).pop();
            },
            child: const Text('העתקה'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.only(bottom: 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ValueListenableBuilder<int>(
                valueListenable: InboxState.instance.unread,
                builder: (_, unread, __) => ListTile(
                  leading: const Icon(Icons.notifications_outlined, color: AppConfig.primary),
                  title: const Text('ההתראות שלי'),
                  trailing: unread > 0
                      ? Badge(label: Text('$unread'))
                      : const Icon(Icons.chevron_left),
                  onTap: widget.onOpenInbox,
                ),
              ),
              _link(Icons.card_membership_outlined, 'מועדון החברים', () => _go(AppConfig.clubUrl)),
              _link(Icons.build_outlined, 'שירותי מעבדה', () => _go(AppConfig.labUrl)),
              _link(Icons.request_quote_outlined, 'בקשה להצעת מחיר', () => _go(AppConfig.quoteUrl)),
              _link(Icons.support_agent_outlined, 'צור קשר', () => _go(AppConfig.contactUrl)),
              const Divider(),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                child: Row(
                  children: [
                    _contactButton(Icons.call, 'חיוג', () => _ext('tel:${AppConfig.phone}')),
                    const SizedBox(width: 8),
                    _contactButton(Icons.chat, 'וואטסאפ', () => _ext(AppConfig.whatsappUrl)),
                    const SizedBox(width: 8),
                    _contactButton(Icons.email_outlined, 'מייל', () => _ext('mailto:${AppConfig.email}')),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text(
                  'מוקד שירות ומכירות ${AppConfig.phoneDisplay} | חיוג מקוצר ${AppConfig.shortDial}',
                  style: const TextStyle(fontSize: 12, color: Colors.black54),
                ),
              ),
              const Divider(),
              SwitchListTile(
                secondary: const Icon(Icons.local_offer_outlined, color: AppConfig.primary),
                title: const Text('התראות על מבצעים והנחות'),
                subtitle: const Text('ירידות מחיר, קופונים ומבצעים חדשים'),
                value: _deals,
                onChanged: (v) {
                  setState(() => _deals = v);
                  PushService.instance.setDealsEnabled(v);
                },
              ),
              _link(Icons.refresh, 'רענון העמוד', widget.onReload),
              GestureDetector(
                onLongPress: _showToken,
                child: const Padding(
                  padding: EdgeInsets.all(12),
                  child: Text('סטור פיסי • גרסה 1.0.0',
                      style: TextStyle(fontSize: 12, color: Colors.black38)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _link(IconData icon, String title, VoidCallback onTap) => ListTile(
        leading: Icon(icon, color: AppConfig.primary),
        title: Text(title),
        trailing: const Icon(Icons.chevron_left),
        onTap: onTap,
      );

  Widget _contactButton(IconData icon, String label, VoidCallback onTap) => Expanded(
        child: OutlinedButton.icon(
          onPressed: onTap,
          icon: Icon(icon, size: 18),
          label: Text(label),
          style: OutlinedButton.styleFrom(
            foregroundColor: AppConfig.primary,
            padding: const EdgeInsets.symmetric(vertical: 12),
          ),
        ),
      );
}
