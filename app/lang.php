<?php
/**
 * Language / i18n loader.
 *
 * Sets the active language from ?lang= query param (persisted in session),
 * then loads the translation array and exposes the __() helper globally.
 *
 * Supported: 'en' (English) | 'ms' (Bahasa Malaysia)
 * Default  : 'en'
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Honour an explicit ?lang= switch and strip it from the URL
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ms'], true)) {
    $_SESSION['lang'] = $_GET['lang'];

    // Redirect to the same URL without the ?lang= param so it doesn't linger
    $params = $_GET;
    unset($params['lang']);
    $base = strtok($_SERVER['REQUEST_URI'], '?');
    $qs   = $params ? '?' . http_build_query($params) : '';
    header('Location: ' . $base . $qs, true, 302);
    exit;
}

$GLOBALS['CURRENT_LANG'] = $_SESSION['lang'] ?? 'en';

// Load the translation array
$GLOBALS['LANG'] = require __DIR__ . '/lang/' . $GLOBALS['CURRENT_LANG'] . '.php';

/**
 * Translate a key, with optional sprintf placeholders.
 *
 * Usage:
 *   __('nav.about')              → "About"
 *   __('cat.slots', 42)          → "42 slots remaining"
 *   __('reg.s4.sub', '35.00')   → "Optional — RM 35.00 per unit"
 */
function __($key, ...$args): string
{
    $str = $GLOBALS['LANG'][$key] ?? $key;
    return $args ? sprintf($str, ...$args) : $str;
}

/**
 * Returns the current language code ('en' or 'ms').
 */
function currentLang(): string
{
    return $GLOBALS['CURRENT_LANG'];
}

/**
 * Build a URL that switches to the given language, preserving all other
 * query parameters on the current page.
 */
function langUrl(string $lang): string
{
    $params         = $_GET;
    $params['lang'] = $lang;
    $base           = strtok($_SERVER['REQUEST_URI'], '?');
    return $base . '?' . http_build_query($params);
}
