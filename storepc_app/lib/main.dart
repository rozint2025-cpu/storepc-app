import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'config.dart';
import 'firebase_options.dart';
import 'screens/home_shell.dart';
import 'services/api_service.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarColor: AppConfig.primary,
    statusBarIconBrightness: Brightness.light,
  ));

  var pushReady = false;
  try {
    await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
    pushReady = true;
  } catch (e) {
    debugPrint('Firebase init failed: $e');
  }

  // לא מחכים לרשת לפני הצגת האפליקציה – טוענים הגדרות במקביל עם תקרת זמן
  await ApiService.loadRemoteConfig().timeout(const Duration(seconds: 3), onTimeout: () {});

  runApp(StorePcApp(pushReady: pushReady));
}

class StorePcApp extends StatelessWidget {
  final bool pushReady;
  const StorePcApp({super.key, required this.pushReady});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: AppConfig.appName,
      debugShowCheckedModeBanner: false,
      locale: const Locale('he', 'IL'),
      supportedLocales: const [Locale('he', 'IL')],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(
          seedColor: AppConfig.primary,
          primary: AppConfig.primary,
        ),
        scaffoldBackgroundColor: Colors.white,
      ),
      home: HomeShell(pushReady: pushReady),
    );
  }
}
