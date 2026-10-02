<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax']);
    session_start();
}
require_once __DIR__ . '/db_connect.php';

function csrf_token(): string { return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32)); }
function require_csrf(): void {
    $value = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($value) || !hash_equals($_SESSION['csrf_token'] ?? '', $value)) { http_response_code(403); exit('Invalid form submission.'); }
}
function require_user(): int { if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; } return (int) $_SESSION['user_id']; }
function require_admin(): int { if (empty($_SESSION['admin_id'])) { header('Location: admin_login.php'); exit; } return (int) $_SESSION['admin_id']; }
