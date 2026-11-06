package com.thestacks.app;

import android.Manifest;
import android.annotation.SuppressLint;
import android.app.DownloadManager;
import android.content.Context;
import android.os.Build;
import android.os.Environment;
import android.view.HapticFeedbackConstants;
import android.webkit.URLUtil;
import android.widget.Toast;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsControllerCompat;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.content.res.Configuration;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.net.Uri;
import android.net.http.SslError;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.View;
import android.view.WindowManager;
import android.webkit.CookieManager;
import android.webkit.JavascriptInterface;
import android.webkit.PermissionRequest;
import android.webkit.SslErrorHandler;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.ProgressBar;

import androidx.activity.OnBackPressedCallback;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;
import androidx.webkit.WebSettingsCompat;
import androidx.webkit.WebViewFeature;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.util.Locale;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * The Stacks — Android wrapper around the hosted site (res/values/strings.xml -> app_url).
 *
 * WHAT THE APP ADDS ON TOP OF THE WEBSITE
 *  - Downloads: tapping "Download" on an APK / shared file hands it to Android's DownloadManager
 *    (progress notification, saved in Downloads, tap to open / install). A plain WebView ignores those links.
 *  - Offline mode: every board page opened online is saved inside the app (OfflineCache +
 *    assets/native/snapshot.js). With no internet the saved board opens instantly with a small
 *    "Offline" bar. Saved copies are wiped on sign-in / sign-out so nobody sees another member's board.
 *  - Native feel: status bar + navigation bar follow the page's light/dark toggle, pull-to-refresh only
 *    at the very top of the page, smooth cross-fade rotation, no white flash, haptic tick on taps.
 *  - Back button: closes the open dialog first, then goes back, then leaves the app.
 *
 * AUTO-ROTATE: the activity follows the phone's own auto-rotate switch (screenOrientation=fullUser)
 * and handles every configuration change itself (see AndroidManifest.xml), so turning the phone
 * never reloads the page or loses your place.
 */
public class MainActivity extends AppCompatActivity {

    private static final String STATE_URL = "state_url";
    private static final String PREFS = "offline_state";
    private static final String KEY_LAST_PAGE = "last_page";

    private WebView webView;
    private SwipeRefreshLayout swipeRefresh;
    private ProgressBar progressBar;
    private View offlineView;
    private View savedHint;
    private Button openSavedButton;

    private OfflineCache offlineCache;
    private SharedPreferences prefs;
    private String appBase;            // e.g. https://your-site.infinityfreeapp.com
    private String snapshotJs;         // assets/native/snapshot.js
    private String offlineJs;          // assets/native/offline.js
    private String currentUrl;         // last real page the student is on
    private String failedUrl;          // main-frame URL whose load just failed (see onPageFinished)
    private boolean showingSnapshot;   // true while a saved copy is on screen
    private ConnectivityManager.NetworkCallback networkCallback;
    private final Handler mainHandler = new Handler(Looper.getMainLooper());
    private final Runnable snapshotRunner = new Runnable() {
        @Override
        public void run() {
            if (webView != null && snapshotJs != null) webView.evaluateJavascript(snapshotJs, null);
        }
    };

    private ValueCallback<Uri[]> filePathCallback;
    private static final int FILE_CHOOSER_REQUEST_CODE = 1001;

    // Camera (QR scanner on the Scan QR page): the WebView must be allowed by
    // BOTH Android (runtime CAMERA permission) and our WebChromeClient below.
    private static final int CAMERA_PERMISSION_REQUEST_CODE = 2001;
    private PermissionRequest pendingWebPermission;

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        // Smooth auto-rotate: cross-fade between portrait and landscape instead of a hard jump.
        WindowManager.LayoutParams lp = getWindow().getAttributes();
        lp.rotationAnimation = WindowManager.LayoutParams.ROTATION_ANIMATION_CROSSFADE;
        getWindow().setAttributes(lp);

        offlineCache = new OfflineCache(this);
        prefs = getSharedPreferences(PREFS, MODE_PRIVATE);
        appBase = getString(R.string.app_url).replaceAll("/[^/]*$", "");
        snapshotJs = readAsset("native/snapshot.js");
        offlineJs = readAsset("native/offline.js");

        webView = findViewById(R.id.webView);
        swipeRefresh = findViewById(R.id.swipeRefresh);
        progressBar = findViewById(R.id.progressBar);
        offlineView = findViewById(R.id.offlineView);
        savedHint = findViewById(R.id.savedHint);
        openSavedButton = findViewById(R.id.openSavedButton);
        Button retryButton = findViewById(R.id.retryButton);

        setupWebView();
        swipeRefresh.setColorSchemeResources(R.color.accent);
        swipeRefresh.setProgressBackgroundColorSchemeResource(R.color.surface);
        applyTheme(isNightMode());

        // SMOOTH SCROLL FIX: pull-to-refresh only when the page is really at
        // the very top. Without this, scrolling up inside the page keeps
        // triggering a refresh, which feels laggy/stuck.
        swipeRefresh.setOnChildScrollUpCallback((parent, child) -> webView.getScrollY() > 0);

        retryButton.setOnClickListener(v -> refreshCurrent());
        openSavedButton.setOnClickListener(v -> openLastSaved());
        swipeRefresh.setOnRefreshListener(this::refreshCurrent);

        // Proper back-button behaviour: go back through WebView history
        // first; only exit the app when there's nothing left to go back to.
        getOnBackPressedCallback();

        String restore = savedInstanceState != null ? savedInstanceState.getString(STATE_URL) : null;
        if (restore != null) {
            loadUrlSmart(restore, false);
        } else {
            loadApp();
        }
    }

    private void getOnBackPressedCallback() {
        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                if (offlineView.getVisibility() != View.VISIBLE && webView.canGoBack()) {
                    webView.goBack();
                } else {
                    setEnabled(false);
                    getOnBackPressedDispatcher().onBackPressed();
                }
            }
        });
    }

    @SuppressLint({"SetJavaScriptEnabled", "JavascriptInterface"})
    private void setupWebView() {
        WebSettings settings = webView.getSettings();

        // Native-app feel: no stretchy overscroll glow, no white flash.
        // (The WebView is already GPU-accelerated; forcing a hardware layer on it made
        // rotation flicker/black-flash on some phones, so that line was removed.)
        webView.setOverScrollMode(View.OVER_SCROLL_NEVER);
        webView.setScrollBarStyle(View.SCROLLBARS_INSIDE_OVERLAY);
        webView.setBackgroundColor(ContextCompat.getColor(this, R.color.appBackground));
        settings.setOffscreenPreRaster(true);
        settings.setTextZoom(100);   // phone's font-size setting won't break the layout
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);          // needed for localStorage / most modern JS
        settings.setDatabaseEnabled(true);
        settings.setLoadWithOverviewMode(true);
        settings.setUseWideViewPort(true);
        settings.setSupportZoom(false);
        settings.setBuiltInZoomControls(false);
        settings.setCacheMode(WebSettings.LOAD_DEFAULT);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_COMPATIBILITY_MODE);
        settings.setAllowFileAccess(false);
        settings.setMediaPlaybackRequiresUserGesture(false);

        // The Stacks has its own light/dark toggle, so stop Android from "auto-darkening" the page
        // on top of it (that would turn the paper theme muddy).
        if (WebViewFeature.isFeatureSupported(WebViewFeature.ALGORITHMIC_DARKENING)) {
            WebSettingsCompat.setAlgorithmicDarkeningAllowed(settings, false);
        } else if (WebViewFeature.isFeatureSupported(WebViewFeature.FORCE_DARK)) {
            WebSettingsCompat.setForceDark(settings, WebSettingsCompat.FORCE_DARK_OFF);
        }

        CookieManager cm = CookieManager.getInstance();
        cm.setAcceptCookie(true);
        cm.setAcceptThirdPartyCookies(webView, true);

        webView.addJavascriptInterface(new OfflineBridge(), "AndroidOffline");

        // "Download" buttons (APKs / shared files) -> Android DownloadManager
        webView.setDownloadListener((url, userAgent, contentDisposition, mimeType, contentLength) ->
                startDownload(url, userAgent, contentDisposition, mimeType));

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, String url) {
                if (isAppUrl(url)) {
                    if (isAuthPage(url) && isOnline()) offlineCache.clear();
                    if (!isOnline()) {
                        // No internet: open the saved copy right away (no error-page flash)
                        showOfflineContent(url, false);
                        return true;
                    }
                    showingSnapshot = false;
                    view.getSettings().setCacheMode(WebSettings.LOAD_DEFAULT);
                    return false;
                }
                // other sites / mailto / tel open outside the app
                try {
                    startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(url)));
                } catch (Exception ignored) {
                    // nothing on the phone can open it
                }
                return true;
            }

            @Override
            public void onPageStarted(WebView view, String url, android.graphics.Bitmap favicon) {
                super.onPageStarted(view, url, favicon);
                failedUrl = null;
                if (isAppUrl(url) && !isDataUrl(url)) {
                    // Sign-in / sign-out: forget saved boards so one member's data never shows for the next.
                    if (isAuthPage(url)) {
                        if (isOnline()) offlineCache.clear();
                    } else {
                        currentUrl = OfflineCache.normalize(url);
                    }
                }
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                CookieManager.getInstance().flush();

                // After a failed load Android still calls onPageFinished for the error page.
                // Ignore that one so it can't hide our offline screen / saved copy.
                if (failedUrl != null && failedUrl.equals(url)) {
                    failedUrl = null;
                    return;
                }

                progressBar.setVisibility(View.GONE);
                swipeRefresh.setRefreshing(false);
                swipeRefresh.setVisibility(View.VISIBLE);
                offlineView.setVisibility(View.GONE);

                if (isAppUrl(url) && !showingSnapshot) {
                    // Give the page a moment to finish drawing, then save a copy of it.
                    mainHandler.removeCallbacks(snapshotRunner);
                    mainHandler.postDelayed(snapshotRunner, 700);
                }
            }

            @Override
            public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                // Only the main page matters (not icons, fonts and other small files).
                if (request == null || !request.isForMainFrame()) return;
                String url = request.getUrl().toString();
                if (!isAppUrl(url)) return;
                failedUrl = url;
                boolean isStartPage = "GET".equalsIgnoreCase(request.getMethod())
                        && OfflineCache.normalize(url).equals(OfflineCache.normalize(getString(R.string.app_url)));
                showOfflineContent(url, isStartPage);
            }

            @Override
            public void onReceivedSslError(WebView view, SslErrorHandler handler, SslError error) {
                // Don't silently trust bad certs; let the user know instead
                // of the page just failing to load with no explanation.
                super.onReceivedSslError(view, handler, error);
            }
        });

        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView view, int newProgress) {
                super.onProgressChanged(view, newProgress);
                if (newProgress >= 95) {
                    progressBar.setVisibility(View.GONE);
                }
            }

            // Lets the website use the camera (Scan QR page -> getUserMedia).
            // Without this, the page shows "NotAllowedError: Permission denied".
            @Override
            public void onPermissionRequest(final PermissionRequest request) {
                runOnUiThread(() -> handleWebPermission(request));
            }

            @Override
            public void onPermissionRequestCanceled(PermissionRequest request) {
                if (pendingWebPermission == request) pendingWebPermission = null;
            }

            // Handles <input type="file"> (e.g. avatar upload, message attachments)
            @Override
            public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> callback,
                                              FileChooserParams params) {
                if (filePathCallback != null) {
                    filePathCallback.onReceiveValue(null);
                }
                filePathCallback = callback;

                Intent intent = params.createIntent();
                try {
                    startActivityForResult(intent, FILE_CHOOSER_REQUEST_CODE);
                } catch (Exception e) {
                    filePathCallback = null;
                    return false;
                }
                return true;
            }
        });
    }

    // ───────────────────────────── Offline mode ─────────────────────────────

    /** Called from the saved-page scripts (runs on a background thread). */
    private class OfflineBridge {
        @JavascriptInterface
        public void save(String url, String html) {
            if (!isAppUrl(url) || isAuthPage(url)) return;
            if (offlineCache.save(url, html)) {
                prefs.edit().putString(KEY_LAST_PAGE, OfflineCache.normalize(url)).apply();
            }
        }

        @JavascriptInterface
        public void retry() {
            runOnUiThread(MainActivity.this::refreshCurrent);
        }

        /** The page tells the app which theme it is showing ("dark" / "light") so the bars match. */
        @JavascriptInterface
        public void theme(String mode) {
            final boolean dark = "dark".equals(mode);
            runOnUiThread(() -> applyTheme(dark));
        }

        /** Tiny tick on taps (tabs, save...). */
        @JavascriptInterface
        public void haptic() {
            runOnUiThread(() -> {
                if (webView != null) webView.performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY);
            });
        }
    }

    private boolean isOnline() {
        try {
            ConnectivityManager cm = getSystemService(ConnectivityManager.class);
            if (cm == null) return true;
            Network n = cm.getActiveNetwork();
            if (n == null) return false;
            NetworkCapabilities caps = cm.getNetworkCapabilities(n);
            return caps != null && caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET);
        } catch (Exception e) {
            return true;   // if we can't tell, just try the network and let errors be handled
        }
    }

    private boolean isAppUrl(String url) {
        return url != null && (url.equals(appBase) || url.startsWith(appBase + "/"));
    }

    private static boolean isDataUrl(String url) {
        return url != null && url.startsWith("data:");
    }

    private static String pathOf(String url) {
        try {
            String p = Uri.parse(url).getPath();
            return p == null ? "" : p.toLowerCase(Locale.ROOT);
        } catch (Exception e) {
            return "";
        }
    }

    /** Pages that start or end a session (their data must never be kept for the next person). */
    private static boolean isAuthPage(String url) {
        String p = pathOf(url);
        return p.endsWith("/login.php") || p.endsWith("/admin_login.php") || p.endsWith("/logout.php");
    }

    /** The board itself (index.php) — also what the app opens first. */
    private static boolean isStartPage(String url) {
        String p = pathOf(url);
        return p.isEmpty() || p.equals("/") || p.endsWith("/index.php");
    }

    private void loadApp() {
        loadUrlSmart(getString(R.string.app_url), true);
    }

    /** Pull-to-refresh / Try again / Retry bar: reload the current page (live if online, saved if not). */
    private void refreshCurrent() {
        String target = currentUrl != null ? currentUrl : getString(R.string.app_url);
        loadUrlSmart(target, target.equals(OfflineCache.normalize(getString(R.string.app_url))));
    }

    private void loadUrlSmart(String url, boolean isStartPage) {
        if (isOnline()) {
            showingSnapshot = false;
            failedUrl = null;
            offlineView.setVisibility(View.GONE);
            if (swipeRefresh.getVisibility() != View.VISIBLE) progressBar.setVisibility(View.VISIBLE);
            webView.getSettings().setCacheMode(WebSettings.LOAD_DEFAULT);
            webView.loadUrl(url);
        } else {
            showOfflineContent(url, isStartPage);
        }
    }

    /** No internet (or server unreachable): show the saved copy of this page, else the offline screen. */
    private void showOfflineContent(String url, boolean allowLastPageFallback) {
        String target = OfflineCache.normalize(url);
        String html = offlineCache.load(target);
        if (html == null && allowLastPageFallback && isStartPage(target)) {
            // App just opened with no internet: continue where the student left off
            String last = prefs.getString(KEY_LAST_PAGE, null);
            if (last != null) {
                html = offlineCache.load(last);
                if (html != null) target = last;
            }
        }
        if (html != null) {
            showSnapshot(target, html);
        } else {
            showOfflineScreen(target);
        }
    }

    private void showSnapshot(String url, String html) {
        showingSnapshot = true;
        currentUrl = url;
        progressBar.setVisibility(View.GONE);
        swipeRefresh.setRefreshing(false);
        offlineView.setVisibility(View.GONE);
        swipeRefresh.setVisibility(View.VISIBLE);
        // let pictures that were already downloaded (profile photos...) come from the cache
        webView.getSettings().setCacheMode(WebSettings.LOAD_CACHE_ELSE_NETWORK);

        String helper = "<script>" + (offlineJs == null ? "" : offlineJs.replace("__SAVED_AT__",
                String.valueOf(offlineCache.savedAt(url)))) + "</script>";
        Matcher m = Pattern.compile("<head[^>]*>", Pattern.CASE_INSENSITIVE).matcher(html);
        String page = m.find()
                ? html.substring(0, m.end()) + helper + html.substring(m.end())
                : helper + html;
        webView.loadDataWithBaseURL(url, page, "text/html", "UTF-8", url);
    }

    private void showOfflineScreen(String url) {
        showingSnapshot = false;
        currentUrl = url;
        progressBar.setVisibility(View.GONE);
        swipeRefresh.setRefreshing(false);
        swipeRefresh.setVisibility(View.GONE);
        boolean hasSaved = lastSavedPage() != null;
        savedHint.setVisibility(hasSaved ? View.VISIBLE : View.GONE);
        openSavedButton.setVisibility(hasSaved ? View.VISIBLE : View.GONE);
        offlineView.setVisibility(View.VISIBLE);
    }

    private String lastSavedPage() {
        String last = prefs.getString(KEY_LAST_PAGE, null);
        return (last != null && offlineCache.has(last)) ? last : null;
    }

    private void openLastSaved() {
        String last = lastSavedPage();
        String html = last == null ? null : offlineCache.load(last);
        if (html != null) showSnapshot(last, html);
    }

    private void onConnectionRestored() {
        if (isFinishing() || isDestroyed()) return;
        if (offlineView.getVisibility() == View.VISIBLE) {
            refreshCurrent();   // nothing on screen yet, so just load the real page
        } else if (showingSnapshot) {
            // don't yank the page away while they're reading; the bar offers "Refresh"
            webView.evaluateJavascript("window.__stacksOnline&&window.__stacksOnline()", null);
        }
    }

    private void registerNetworkCallback() {
        if (networkCallback != null) return;
        try {
            ConnectivityManager cm = getSystemService(ConnectivityManager.class);
            if (cm == null) return;
            networkCallback = new ConnectivityManager.NetworkCallback() {
                @Override
                public void onAvailable(Network network) {
                    runOnUiThread(MainActivity.this::onConnectionRestored);
                }
            };
            cm.registerDefaultNetworkCallback(networkCallback);
        } catch (Exception e) {
            networkCallback = null;
        }
    }

    private void unregisterNetworkCallback() {
        if (networkCallback == null) return;
        try {
            ConnectivityManager cm = getSystemService(ConnectivityManager.class);
            if (cm != null) cm.unregisterNetworkCallback(networkCallback);
        } catch (Exception ignored) {
            // already unregistered
        }
        networkCallback = null;
    }

    private String readAsset(String name) {
        try (InputStream in = getAssets().open(name); ByteArrayOutputStream out = new ByteArrayOutputStream()) {
            byte[] buf = new byte[8192];
            int n;
            while ((n = in.read(buf)) > 0) out.write(buf, 0, n);
            return out.toString("UTF-8");
        } catch (IOException e) {
            return null;
        }
    }

    // ───────────────────────────── Lifecycle / rotation ─────────────────────────────

    @Override
    protected void onStart() {
        super.onStart();
        registerNetworkCallback();
    }

    @Override
    protected void onStop() {
        super.onStop();
        unregisterNetworkCallback();
    }

    @Override
    protected void onDestroy() {
        mainHandler.removeCallbacksAndMessages(null);
        super.onDestroy();
    }

    @Override
    protected void onSaveInstanceState(Bundle outState) {
        super.onSaveInstanceState(outState);
        if (currentUrl != null) outState.putString(STATE_URL, currentUrl);
    }

    /**
     * Rotation is handled here instead of restarting the activity (see configChanges in the
     * manifest), so the page keeps its place and scroll position and nothing reloads.
     */
    @Override
    public void onConfigurationChanged(Configuration newConfig) {
        super.onConfigurationChanged(newConfig);
        webView.requestLayout();
    }

    // ───────────────────────────── Theme + downloads ─────────────────────────────

    private boolean isNightMode() {
        int mode = getResources().getConfiguration().uiMode & Configuration.UI_MODE_NIGHT_MASK;
        return mode == Configuration.UI_MODE_NIGHT_YES;
    }

    /** Colors the status bar, navigation bar and the area behind the page to match the page's theme. */
    private void applyTheme(boolean dark) {
        int bg = ContextCompat.getColor(this, dark ? R.color.paperDark : R.color.paperLight);
        getWindow().setStatusBarColor(bg);
        getWindow().setNavigationBarColor(bg);
        WindowInsetsControllerCompat c = WindowCompat.getInsetsController(getWindow(), getWindow().getDecorView());
        if (c != null) {
            c.setAppearanceLightStatusBars(!dark);
            c.setAppearanceLightNavigationBars(!dark);
        }
        findViewById(android.R.id.content).setBackgroundColor(bg);
        if (webView != null) webView.setBackgroundColor(bg);
    }

    /** Hands a download link (APK / shared file) to Android's DownloadManager. */
    private void startDownload(String url, String userAgent, String contentDisposition, String mimeType) {
        if (!isOnline()) {
            Toast.makeText(this, R.string.download_offline, Toast.LENGTH_LONG).show();
            return;
        }
        try {
            String name = URLUtil.guessFileName(url, contentDisposition, mimeType);
            DownloadManager.Request req = new DownloadManager.Request(Uri.parse(url));
            String cookie = CookieManager.getInstance().getCookie(url);   // keeps the member signed in
            if (cookie != null) req.addRequestHeader("Cookie", cookie);
            if (userAgent != null) req.addRequestHeader("User-Agent", userAgent);
            if (name.toLowerCase(Locale.ROOT).endsWith(".apk")) {
                req.setMimeType("application/vnd.android.package-archive");   // tap to install
            }
            req.setTitle(name);
            req.setDescription(getString(R.string.app_name));
            req.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
            if (Build.VERSION.SDK_INT >= 29) {
                req.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, name);
            } else {
                req.setDestinationInExternalFilesDir(this, Environment.DIRECTORY_DOWNLOADS, name);
            }
            DownloadManager dm = (DownloadManager) getSystemService(Context.DOWNLOAD_SERVICE);
            dm.enqueue(req);
            Toast.makeText(this, getString(R.string.downloading, name), Toast.LENGTH_SHORT).show();
        } catch (Exception e) {
            Toast.makeText(this, R.string.download_failed, Toast.LENGTH_LONG).show();
        }
    }

    // ───────────────────────────── Camera / file chooser (unchanged) ─────────────────────────────

    /** Grants the page's camera request, asking Android for CAMERA permission first if needed. */
    private void handleWebPermission(PermissionRequest request) {
        // Only trust our own site (the host from strings.xml -> app_url)
        String host = Uri.parse(getString(R.string.app_url)).getHost();
        if (host == null || request.getOrigin() == null
                || !host.equalsIgnoreCase(request.getOrigin().getHost())) {
            request.deny();
            return;
        }

        boolean wantsCamera = false;
        for (String r : request.getResources()) {
            if (PermissionRequest.RESOURCE_VIDEO_CAPTURE.equals(r)) wantsCamera = true;
        }
        if (!wantsCamera) {
            request.deny();   // microphone etc. are not needed by this app
            return;
        }

        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA)
                == PackageManager.PERMISSION_GRANTED) {
            request.grant(new String[]{PermissionRequest.RESOURCE_VIDEO_CAPTURE});
        } else {
            if (pendingWebPermission != null) pendingWebPermission.deny();
            pendingWebPermission = request;
            ActivityCompat.requestPermissions(this,
                    new String[]{Manifest.permission.CAMERA}, CAMERA_PERMISSION_REQUEST_CODE);
        }
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode == CAMERA_PERMISSION_REQUEST_CODE && pendingWebPermission != null) {
            if (grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
                pendingWebPermission.grant(new String[]{PermissionRequest.RESOURCE_VIDEO_CAPTURE});
            } else {
                pendingWebPermission.deny();
            }
            pendingWebPermission = null;
        }
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        if (requestCode == FILE_CHOOSER_REQUEST_CODE) {
            if (filePathCallback == null) {
                super.onActivityResult(requestCode, resultCode, data);
                return;
            }
            Uri[] results = null;
            if (resultCode == RESULT_OK && data != null) {
                String dataString = data.getDataString();
                if (dataString != null) {
                    results = new Uri[]{Uri.parse(dataString)};
                }
            }
            filePathCallback.onReceiveValue(results);
            filePathCallback = null;
        } else {
            super.onActivityResult(requestCode, resultCode, data);
        }
    }
}
