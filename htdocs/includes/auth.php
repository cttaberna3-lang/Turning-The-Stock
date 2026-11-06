<?php
if (session_status() === PHP_SESSION_NONE) {
    // Stay signed in for 14 days. Without this the session cookie dies when the app/browser is closed,
    // so the Android app asked for the name again every time it was reopened.
    $lifetime = 60 * 60 * 24 * 14;
    ini_set('session.gc_maxlifetime', (string) $lifetime);
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
require_once __DIR__ . '/../config.php';

function current_user(): ?string {
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool {
    return !empty($_SESSION['is_admin']);
}

function require_login_page(): void {
    if (!current_user()) {
        header('Location: index.php');
        exit;
    }
}

function require_login_api(): void {
    if (!current_user()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
}

function require_admin_api(): void {
    require_login_api();
    if (!is_admin()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Admin only']);
        exit;
    }
}
