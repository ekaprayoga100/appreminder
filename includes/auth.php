<?php
declare(strict_types=1);

if (defined('AUTH_PHP_LOADED')) {
    return;
}
define('AUTH_PHP_LOADED', true);

if (session_status() === PHP_SESSION_NONE || session_id() === '') {
    if (session_id() === '') {
        session_write_close();
    }

    $cookiePath = dirname($_SERVER['SCRIPT_NAME'], 2) ?: '/';
    if ($cookiePath === '\\' || $cookiePath === '/.') {
        $cookiePath = '/';
    }

    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => $cookiePath,
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (!headers_sent()) {
        @session_start();
    }

    if (session_id() === '') {
        if (!headers_sent()) {
            session_write_close();
            session_start();
        }
    }
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user']);
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin(): void
{
    requireLogin();

    if ((currentUser()['role'] ?? '') !== 'admin') {
        header('Location: dashboard.php?forbidden=1');
        exit;
    }
}

function redirectIfLoggedIn(): void
{
    if (isLoggedIn()) {
        header('Location: dashboard.php');
        exit;
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    $token = csrfToken();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function validateCsrf(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function setFlash(string $key, $value): void
{
    $_SESSION['flash'][$key] = $value;
}

function popFlash(string $key)
{
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function hasFlash(string $key): bool
{
    return isset($_SESSION['flash'][$key]);
}
