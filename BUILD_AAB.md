# בניית קובץ App Bundle (.aab) בלי להתקין כלום

GitHub בונה את הקובץ בענן, בחינם. לוקח בערך 15 דקות בפעם הראשונה.

## 1. Firebase – קובץ google-services.json (בשביל התראות פוש)
1. נכנסים ל-https://console.firebase.google.com ופותחים את הפרויקט (או יוצרים חדש).
2. מוסיפים אפליקציה מסוג **Android** (Add app ← Android).
3. בשדה **Android package name** כותבים בדיוק: `il.co.storepc`
4. מורידים את הקובץ **google-services.json**.

> אפשר לדלג על השלב הזה. האפליקציה תיבנה ותעבוד, אבל בלי התראות פוש.

## 2. מעלים את הפרויקט ל-GitHub
1. יוצרים חשבון ב-https://github.com (אם אין).
2. לוחצים **New repository**. שם: `storepc-app`. מסמנים **Private** (חשוב!) ← Create.
3. בעמוד הריפו לוחצים **uploading an existing file**.
4. גוררים את **כל** מה שבתיקייה שחולצה מה-ZIP: `README.md`, `BUILD_AAB.md`, `storepc_app`, `storepc-push`, ו-**`.github`** ← Commit changes.

> **ב-Mac:** התיקייה `.github` מוסתרת. לוחצים `Cmd+Shift+.` ב-Finder כדי לראות אותה.

## 3. מוסיפים סודות (Secrets)
בריפו: **Settings ← Secrets and variables ← Actions ← New repository secret**.

מוסיפים חמישה סודות. את הערכים לוקחים מהקובץ `SECRETS.txt` שבחבילת מפתח החתימה:

| Name | Value |
|---|---|
| `KEYSTORE_BASE64` |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 |
| `KEYSTORE_PASSWORD` | Tu7zQPzn9ODypW9v9XYY` |
| `KEY_PASSWORD` |Tu7zQPzn9ODypW9v9XYY |
| `KEY_ALIAS` | `upload` |
| `GOOGLE_SERVICES_JSON` | כל התוכן של `google-services.json` משלב 1 |

## 4. בונים
1. בריפו נכנסים ל-**Actions** (אם GitHub מבקש לאשר הפעלת Workflows, מאשרים).
2. בוחרים **Build Android App Bundle** ← **Run workflow** ← Run workflow.
3. כשמופיע ✅ ירוק, לוחצים על הריצה. למטה, תחת **Artifacts**, מורידים את `storepc-app-bundle-N`.
4. בתוך ה-ZIP שירד נמצא הקובץ **`app-release.aab`**. אותו מעלים ל-Google Play Console.

כל ריצה מעלה את מספר הגרסה (versionCode) אוטומטית, כך ש-Google Play יקבל גם עדכונים.

## 5. Google Play Console
1. Create app ← שם: "סטור פיסי" ← שפה: עברית.
2. בשחרור הראשון, Google Play מציע **Play App Signing**. מאשרים.
3. מעלים את הקובץ `app-release.aab`. מומלץ להתחיל ב-**Internal testing** כדי לבדוק על הטלפון לפני הפרסום לכולם.

---

## ⚠️ מפתח החתימה
- הקבצים `upload-keystore.jks` ו-`SECRETS.txt` הם מפתח העלאה לחנות.
- שמרו אותם במקום בטוח, כמו מנהל סיסמאות או גיבוי מוצפן.
- **אל תעלו אותם לריפו**. הם נכנסים לבנייה רק דרך ה-Secrets.
- בזכות Play App Signing, אם המפתח אובד אפשר לבקש מגוגל לאפס אותו. זה מסורבל, ולכן עדיף לא לאבד אותו.

## אם הבנייה נכשלת
1. לוחצים על הריצה האדומה ← על השלב שנכשל.
2. מעתיקים את השורות האדומות האחרונות ושולחים אליי. אתקן.
