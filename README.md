# The Stacks — Android app

An Android app that wraps your hosted **The Stacks** site (PHP + MySQL on InfinityFree) and makes it
feel like a real app.

## 1. Put your site address in the app (required)
Open `app/src/main/res/values/strings.xml` and change:

    <string name="app_url">https://YOUR-SITE.infinityfreeapp.com/index.php</string>

to your real InfinityFree URL (the page that shows the "Sign in to the board" screen).

## 2. Upload the updated website files
From the `htdocs/` folder that came with this app, upload to your InfinityFree `htdocs/`:

| File | What it fixes |
|------|---------------|
| `index.php` | smoother UI, loading skeletons, upload progress bar, bottom-sheet dialogs, toasts, back-button support |
| `includes/auth.php` | stays signed in for 14 days (before, the app asked for your name again on every restart) |
| `assets/grain.png` (new folder) | replaces the heavy paper-texture filter that made scrolling laggy on phones |

Do not overwrite your `config.php` or the `uploads/` folder.

## 3. Build the APK
**GitHub (no Android Studio needed):** push this folder to a GitHub repo → *Actions* → *Build Android APK*
→ download `the-stacks-debug-apk` → install `app-debug.apk`.

**Android Studio:** *File → Open* this folder → *Build → Build APK(s)*.

## What the app does
- **Downloads work**: tapping *Download* on an APK / shared file goes to Android's download manager
  (progress notification, saved in Downloads, tap to install). A plain WebView can't do this.
- **Offline**: the last board you opened is saved on the phone and opens with no internet
  (with an "Offline" bar). Saved boards are wiped on sign-in / sign-out.
- **Native feel**: status bar + navigation bar follow the light/dark toggle, pull-to-refresh only at the very
  top, smooth rotation, no white flash, small vibration tick on taps.
- **Back button** closes the open dialog first, then goes back, then leaves the app.

## Notes
- Package name: `com.thestacks.app`.
- If the app shows a blank page / "checking your browser", open the site once in Chrome, then retry
  (InfinityFree's bot check).
- InfinityFree's own PHP upload limit is much lower than the 10 GB shown in the page; big uploads fail on the
  server side. The app now shows a clear message when that happens.
