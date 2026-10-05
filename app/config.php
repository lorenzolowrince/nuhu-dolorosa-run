<?php
declare(strict_types=1);

// ============================================================
// Application Configuration
// Loads .env and defines all constants
// ============================================================

// All dates/times are Malaysia time (UTC+8); the MySQL session is matched in database.php
date_default_timezone_set('Asia/Kuala_Lumpur');

define('BASE_PATH',   dirname(__DIR__));
define('APP_PATH',    BASE_PATH . '/app');
define('PUBLIC_PATH',
    is_dir(BASE_PATH . '/public_html') ? BASE_PATH . '/public_html' :
    (is_dir(BASE_PATH . '/public')     ? BASE_PATH . '/public'      :
    BASE_PATH));

// --- Load .env file ---
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

function env(string $key, mixed $default = null): mixed
{
    $val = $_ENV[$key] ?? getenv($key);
    if ($val === false || $val === null || $val === '') {
        return $default;
    }
    // Cast booleans
    if (strtolower((string)$val) === 'true')  return true;
    if (strtolower((string)$val) === 'false') return false;
    return $val;
}

// Application
define('PAYMENT_TEST_MODE', (bool) env('PAYMENT_TEST_MODE', false));
define('APP_NAME',  env('APP_NAME',  'Charity Run Nuhu Dolorosa'));
define('APP_URL',   env('APP_URL',   'http://localhost'));
define('APP_ENV',   env('APP_ENV',   'production'));
define('APP_DEBUG', (bool) env('APP_DEBUG', false));

// Database
define('DB_HOST',     env('DB_HOST',     'localhost'));
define('DB_PORT',     (int) env('DB_PORT', 3306));
define('DB_DATABASE', env('DB_DATABASE', 'nuhu_dolorosa_run'));
define('DB_USERNAME', env('DB_USERNAME', 'root'));
define('DB_PASSWORD', env('DB_PASSWORD', ''));

// Stripe
define('STRIPE_PUBLISHABLE_KEY', env('STRIPE_PUBLISHABLE_KEY', ''));
define('STRIPE_SECRET_KEY',      env('STRIPE_SECRET_KEY',      ''));
define('STRIPE_WEBHOOK_SECRET',  env('STRIPE_WEBHOOK_SECRET',  ''));

// Billplz FPX
define('BILLPLZ_API_KEY',         env('BILLPLZ_API_KEY',         ''));
define('BILLPLZ_COLLECTION_ID',   env('BILLPLZ_COLLECTION_ID',   ''));
define('BILLPLZ_X_SIGNATURE_KEY', env('BILLPLZ_X_SIGNATURE_KEY', ''));
define('BILLPLZ_SANDBOX',         (bool) env('BILLPLZ_SANDBOX', true));
define('BILLPLZ_BASE_URL',
    BILLPLZ_SANDBOX
        ? 'https://billplz-staging.herokuapp.com/api/v3'
        : 'https://www.billplz.com/api/v3'
);

// Mail
define('MAIL_HOST',         env('MAIL_HOST',         'smtp.gmail.com'));
define('MAIL_PORT',         (int) env('MAIL_PORT',   587));
define('MAIL_ENCRYPTION',   env('MAIL_ENCRYPTION',   'tls'));
define('MAIL_USERNAME',     env('MAIL_USERNAME',      ''));
define('MAIL_PASSWORD',     env('MAIL_PASSWORD',      ''));
define('MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS',  'noreply@nuhurun.my'));
define('MAIL_FROM_NAME',    env('MAIL_FROM_NAME',     'Nuhu Dolorosa Charity Run'));

// Secure session configuration
ini_set('session.cookie_httponly',  '1');
ini_set('session.cookie_samesite',  'Strict');
ini_set('session.use_strict_mode',  '1');
ini_set('session.gc_maxlifetime',   '7200');

if (str_starts_with((string) APP_URL, 'https://')) {
    ini_set('session.cookie_secure', '1');
}

// Language / i18n system (sets up __() helper and CURRENT_LANG global)
require_once APP_PATH . '/lang.php';

// Error display (only in development)
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
