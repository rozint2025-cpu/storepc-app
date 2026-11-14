// קובץ זמני. הפקודה `flutterfire configure` מחליפה אותו בהגדרות האמיתיות של הפרויקט.
// עד אז האפליקציה עובדת, אבל בלי התראות פוש.
import 'package:firebase_core/firebase_core.dart' show FirebaseOptions;

class DefaultFirebaseOptions {
  static FirebaseOptions get currentPlatform {
    throw UnsupportedError('הריצו flutterfire configure כדי לחבר את Firebase');
  }
}
