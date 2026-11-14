"""יוצר lib/firebase_options.dart מתוך google-services.json (בשביל Android).
שימוש: python3 tools/make_firebase_options.py google-services.json lib/firebase_options.dart
"""
import json
import sys

PACKAGE = "il.co.storepc"

src, dst = sys.argv[1], sys.argv[2]
data = json.load(open(src, encoding="utf-8"))
info = data["project_info"]
clients = data.get("client", [])
client = next(
    (c for c in clients if c["client_info"]["android_client_info"]["package_name"] == PACKAGE),
    None,
)
if client is None:
    names = [c["client_info"]["android_client_info"]["package_name"] for c in clients]
    sys.exit(f"ERROR: no Android app with package '{PACKAGE}' in google-services.json (found: {names})")

def q(v):
    return json.dumps(v or "")

out = f"""// נוצר אוטומטית מ-google-services.json – לא לערוך ידנית.
import 'package:firebase_core/firebase_core.dart' show FirebaseOptions;
import 'package:flutter/foundation.dart' show defaultTargetPlatform, TargetPlatform;

class DefaultFirebaseOptions {{
  static FirebaseOptions get currentPlatform {{
    if (defaultTargetPlatform == TargetPlatform.android) return android;
    throw UnsupportedError('Firebase מוגדר כרגע רק ל-Android');
  }}

  static const FirebaseOptions android = FirebaseOptions(
    apiKey: {q(client["api_key"][0]["current_key"])},
    appId: {q(client["client_info"]["mobilesdk_app_id"])},
    messagingSenderId: {q(info["project_number"])},
    projectId: {q(info["project_id"])},
    storageBucket: {q(info.get("storage_bucket"))},
  );
}}
"""
open(dst, "w", encoding="utf-8").write(out)
print(f"firebase_options.dart created for project {info['project_id']}")
