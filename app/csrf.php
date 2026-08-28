<?php
declare(strict_types=1);

// ============================================================
// CSRF Protection
// ============================================================

class CSRF
{
    private const TOKEN_KEY    = '_csrf_token';
    private const TOKEN_LENGTH = 32;

    // --- Generate or retrieve current token ---
    public static function token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }

        return $_SESSION[self::TOKEN_KEY];
    }

    // --- Render a hidden input field ---
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . e(self::token()) . '">';
    }

    // --- Validate token from request ---
    public static function verify(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $submitted = $_POST[self::TOKEN_KEY]
                  ?? $_SERVER['HTTP_X_CSRF_TOKEN']
                  ?? '';

        $stored = $_SESSION[self::TOKEN_KEY] ?? '';

        if ($stored === '' || $submitted === '') {
            return false;
        }

        return hash_equals($stored, $submitted);
    }

    // --- Verify or abort with 403 ---
    public static function verifyOrFail(): void
    {
        if (!self::verify()) {
            http_response_code(403);
            die('Invalid or missing CSRF token. Please go back and try again.');
        }
    }

    // --- Rotate token (call after successful POST) ---
    public static function rotate(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(self::TOKEN_LENGTH));
    }
}
