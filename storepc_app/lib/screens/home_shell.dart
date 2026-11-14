import 'dart:async';
import 'dart:convert';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../config.dart';
import '../services/inbox_state.dart';
import '../services/push_service.dart';
import 'inbox_screen.dart';
import 'more_sheet.dart';

/// המסך הראשי: האתר בתוך WebView + תפריט תחתון.
class HomeShell extends StatefulWidget {
  final bool pushReady;
  const HomeShell({super.key, required this.pushReady});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> with WidgetsBindingObserver {
  late final WebViewController _web;
  int _progress = 0;
  bool _offline = false;
  int _tab = 0;
  DateTime? _lastBackPress;
  final List<StreamSubscription> _subs = [];

  // לשוניות: בית, מבצעים, עגלה, החשבון, עוד
  List<String> get _tabUrls => [
        AppConfig.homeUrl,
        AppConfig.dealsUrl,
        AppConfig.cartUrl,
        AppConfig.accountUrl,
      ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _web = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(Colors.white)
      ..setNavigationDelegate(NavigationDelegate(
        onProgress: (p) {
          if (mounted) setState(() => _progress = p);
        },
        onPageStarted: (_) {
          if (mounted && _offline) setState(() => _offline = false);
        },
        onPageFinished: (url) {
          _injectAppStyles();
          _syncTab(url);
        },
        onUrlChange: (c) {
          if (c.url != null) _syncTab(c.url!);
        },
        onWebResourceError: (e) {
          if (e.isForMainFrame ?? false) setState(() => _offline = true);
        },
        onNavigationRequest: _onNavigationRequest,
      ));
    _boot();
  }

  Future<void> _boot() async {
    // מוסיפים סימון ל-User-Agent כדי שהאתר יזהה גלישה מהאפליקציה
    try {
      final ua = await _web.getUserAgent();
      if (ua != null && !ua.contains(AppConfig.userAgentSuffix)) {
        await _web.setUserAgent('$ua ${AppConfig.userAgentSuffix}');
      }
    } catch (_) {}

    String startUrl = AppConfig.homeUrl;
    if (widget.pushReady) {
      final fromPush = await PushService.instance.initialUrl();
      if (fromPush != null) startUrl = fromPush;
    }
    await _web.loadRequest(_toUri(startUrl));

    if (widget.pushReady) {
      _subs.add(PushService.instance.onOpenUrl.listen(_open));
      _subs.add(PushService.instance.onForegroundMessage.listen(_showForegroundMessage));
      PushService.instance.init();
    }
    InboxState.instance.refreshSilently();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) InboxState.instance.refreshSilently();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    for (final s in _subs) {
      s.cancel();
    }
    super.dispose();
  }

  // ---------- ניווט ----------

  static Uri _toUri(String url) => Uri.parse(url.contains('%') ? url : Uri.encodeFull(url));

  void _open(String url) {
    setState(() => _offline = false);
    _web.loadRequest(_toUri(url));
  }

  Future<NavigationDecision> _onNavigationRequest(NavigationRequest req) async {
    final uri = Uri.tryParse(req.url);
    if (uri == null) return NavigationDecision.prevent;

    // tel:, mailto:, whatsapp:, waze: וכו' – לאפליקציה המתאימה בטלפון
    if (uri.scheme != 'http' && uri.scheme != 'https') {
      await _launchExternal(uri);
      return NavigationDecision.prevent;
    }

    final host = uri.host.toLowerCase().replaceFirst('www.', '');
    final isExternal = AppConfig.externalHosts.any((h) => host == h || host.endsWith('.$h'));
    if (isExternal && req.isMainFrame) {
      await _launchExternal(uri);
      return NavigationDecision.prevent;
    }
    return NavigationDecision.navigate;
  }

  Future<void> _launchExternal(Uri uri) async {
    try {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } catch (_) {}
  }

  /// מזריק CSS ומוסיף class לעמוד – כדי שאפשר יהיה להסתיר אלמנטים בתוך האפליקציה.
  void _injectAppStyles() {
    final css = jsonEncode(AppConfig.injectedCss);
    _web.runJavaScript('''
(function(){
  document.documentElement.classList.add('storepc-app');
  if (document.getElementById('storepc-app-css')) return;
  var s = document.createElement('style');
  s.id = 'storepc-app-css';
  s.textContent = $css;
  (document.head || document.documentElement).appendChild(s);
})();
''');
  }

  void _syncTab(String url) {
    final current = _normalize(url);
    var idx = -1;
    for (var i = _tabUrls.length - 1; i >= 0; i--) {
      final t = _normalize(_tabUrls[i]);
      if (i == 0 ? current == t : current.startsWith(t)) {
        idx = i;
        break;
      }
    }
    if (idx != -1 && idx != _tab) setState(() => _tab = idx);
  }

  static String _normalize(String url) {
    var u = url;
    try {
      u = Uri.decodeFull(url);
    } catch (_) {}
    u = u.replaceFirst('://www.', '://').split('?').first.split('#').first;
    return u.endsWith('/') ? u : '$u/';
  }

  Future<void> _onBack(bool didPop) async {
    if (didPop) return;
    if (await _web.canGoBack()) {
      await _web.goBack();
      return;
    }
    final now = DateTime.now();
    if (_lastBackPress == null || now.difference(_lastBackPress!) > const Duration(seconds: 2)) {
      _lastBackPress = now;
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('לחצו שוב כדי לצאת'), duration: Duration(seconds: 2)),
      );
      return;
    }
    SystemNavigator.pop();
  }

  // ---------- התראות ----------

  void _showForegroundMessage(RemoteMessage message) {
    final n = message.notification;
    final url = PushService.urlOf(message);
    InboxState.instance.refreshSilently();
    if (!mounted || n == null) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      behavior: SnackBarBehavior.floating,
      duration: const Duration(seconds: 6),
      backgroundColor: AppConfig.primary,
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(n.title ?? '', style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
          if ((n.body ?? '').isNotEmpty)
            Text(n.body!, style: const TextStyle(color: Colors.white)),
        ],
      ),
      action: url == null
          ? null
          : SnackBarAction(label: 'לצפייה', textColor: Colors.white, onPressed: () => _open(url)),
    ));
  }

  Future<void> _openInbox() async {
    final url = await Navigator.of(context).push<String>(
      MaterialPageRoute(builder: (_) => const InboxScreen()),
    );
    if (url != null) _open(url);
  }

  Future<void> _openMore() async {
    final url = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => MoreSheet(
        onOpenInbox: () {
          Navigator.of(context).pop();
          _openInbox();
        },
        onReload: () {
          Navigator.of(context).pop();
          _web.reload();
        },
      ),
    );
    if (url != null) _open(url);
  }

  // ---------- UI ----------

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: PopScope(
        canPop: false,
        onPopInvokedWithResult: (didPop, _) => _onBack(didPop),
        child: Scaffold(
          body: SafeArea(
            bottom: false,
            child: Stack(
              children: [
                WebViewWidget(controller: _web),
                if (_progress < 100)
                  LinearProgressIndicator(
                    value: _progress / 100,
                    minHeight: 3,
                    color: AppConfig.primaryLight,
                    backgroundColor: Colors.transparent,
                  ),
                if (_offline) _OfflineView(onRetry: () => _web.reload()),
              ],
            ),
          ),
          bottomNavigationBar: ValueListenableBuilder<int>(
            valueListenable: InboxState.instance.unread,
            builder: (context, unread, _) => NavigationBar(
              selectedIndex: _tab,
              height: 64,
              backgroundColor: Colors.white,
              indicatorColor: const Color(0x1F0D47A1),
              labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
              onDestinationSelected: (i) {
                if (i == 4) {
                  _openMore();
                  return;
                }
                setState(() => _tab = i);
                _open(_tabUrls[i]);
              },
              destinations: [
                const NavigationDestination(
                    icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home), label: 'בית'),
                const NavigationDestination(
                    icon: Icon(Icons.local_offer_outlined),
                    selectedIcon: Icon(Icons.local_offer),
                    label: 'מבצעים'),
                const NavigationDestination(
                    icon: Icon(Icons.shopping_cart_outlined),
                    selectedIcon: Icon(Icons.shopping_cart),
                    label: 'עגלה'),
                const NavigationDestination(
                    icon: Icon(Icons.person_outline), selectedIcon: Icon(Icons.person), label: 'החשבון'),
                NavigationDestination(
                  icon: Badge(
                    isLabelVisible: unread > 0,
                    label: Text('$unread'),
                    child: const Icon(Icons.menu),
                  ),
                  label: 'עוד',
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _OfflineView extends StatelessWidget {
  final VoidCallback onRetry;
  const _OfflineView({required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.white,
      alignment: Alignment.center,
      padding: const EdgeInsets.all(32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.wifi_off_rounded, size: 72, color: AppConfig.primary),
          const SizedBox(height: 16),
          const Text('אין חיבור לאינטרנט',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          const Text('בדקו את החיבור ונסו שוב', textAlign: TextAlign.center),
          const SizedBox(height: 24),
          FilledButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh),
            label: const Text('נסו שוב'),
          ),
        ],
      ),
    );
  }
}
