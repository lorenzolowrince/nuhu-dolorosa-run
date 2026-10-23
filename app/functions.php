<?php
declare(strict_types=1);

// ============================================================
// Global Helper Functions
// ============================================================

// --- Output escaping ---
function e(string|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// --- Generate unique reference number ---
function generateReference(string $prefix = 'NDR'): string
{
    return strtoupper($prefix) . date('Ymd') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

// --- Get client IP address ---
function getClientIP(): string
{
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = trim(explode(',', $_SERVER[$header])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// --- Sanitize string input ---
function sanitize(string $value): string
{
    return trim(strip_tags($value));
}

// --- Format currency in MYR ---
function formatMYR(float $amount): string
{
    return 'RM ' . number_format($amount, 2);
}

// --- Redirect helper ---
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// --- Set flash message ---
function setFlash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

// --- Get and clear flash message ---
function getFlash(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// --- Render flash alert HTML ---
function renderFlash(): string
{
    $flash = getFlash();
    if (!$flash) return '';

    $map = [
        'success' => 'success',
        'error'   => 'danger',
        'warning' => 'warning',
        'info'    => 'info',
    ];
    $bsClass = $map[$flash['type']] ?? 'info';

    return '<div class="alert alert-' . $bsClass . ' alert-dismissible fade show" role="alert">'
         . e($flash['message'])
         . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
         . '</div>';
}

// --- Load a system setting from DB ---
function getSetting(string $key, string $default = ''): string
{
    static $cache = [];
    if (!isset($cache[$key])) {
        $row = Database::fetchOne(
            'SELECT setting_value FROM system_settings WHERE setting_key = ?',
            [$key]
        );
        $cache[$key] = $row ? $row['setting_value'] : $default;
    }
    return $cache[$key];
}

// --- Extra charge for oversized t-shirts (3XL-5XL / 6XL-7XL) ---
function shirtSizeSurcharge(string $size): float
{
    if (in_array($size, ['3XL', '4XL', '5XL'], true)) {
        return (float) getSetting('shirt_extra_size_fee_1', '12');
    }
    if (in_array($size, ['6XL', '7XL'], true)) {
        return (float) getSetting('shirt_extra_size_fee_2', '14');
    }
    return 0.0;
}

// --- Load all event categories ---
function getActiveCategories(): array
{
    return Database::fetchAll(
        'SELECT * FROM event_categories WHERE is_active = 1 ORDER BY fee ASC'
    );
}

// --- Countdown seconds to event date ---
function eventCountdownSeconds(): int
{
    $eventDate = getSetting('event_date', '2025-06-28');
    $target    = new DateTimeImmutable($eventDate . ' 05:30:00', new DateTimeZone('Asia/Kuala_Lumpur'));
    $now       = new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur'));
    $diff      = $target->getTimestamp() - $now->getTimestamp();
    return max(0, $diff);
}

// --- Audit helpers ---

// Form/security keys that must never be stored in the audit log
function auditStripNoise(?array $values): ?array
{
    if ($values === null) return null;
    $values = array_filter(
        $values,
        fn($k) => !in_array($k, ['_csrf_token', 'csrf_token', 'action'], true) && stripos((string) $k, 'password') === false,
        ARRAY_FILTER_USE_KEY
    );
    return $values ?: null;
}

// Keep only the fields whose value actually changed. Returns [$oldChanged, $newChanged].
function auditDiff(array $old, array $new): array
{
    $oldChanged = $newChanged = [];
    foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $k) {
        $o = $old[$k] ?? null;
        $n = $new[$k] ?? null;
        if ((string) $o !== (string) $n) {
            $oldChanged[$k] = $o;
            $newChanged[$k] = $n;
        }
    }
    return [$oldChanged, $newChanged];
}

// Key details of a participant, kept in the log when a record is deleted
function participantAuditSnapshot(array $p): array
{
    $keys = ['reference_number', 'full_name', 'email', 'contact_number', 'category_name', 'distance',
             'race_category', 'registration_status', 'event_shirt_size', 'finisher_shirt_size', 'created_at'];
    return array_intersect_key($p, array_flip($keys));
}

// Extra shirt orders of a participant as "Extra shirt #id" => "Event · M × 2"
function shirtOrdersAuditMap(int $participantId): array
{
    $map = [];
    foreach (Database::fetchAll('SELECT id, shirt_type, size, quantity FROM shirt_orders WHERE participant_id = ? ORDER BY id', [$participantId]) as $s) {
        $type = $s['shirt_type'] ? ucfirst((string) $s['shirt_type']) . ' · ' : '';
        $map['Extra shirt #' . $s['id']] = $type . $s['size'] . ' × ' . $s['quantity'];
    }
    return $map;
}

// --- Write audit log entry ---
function auditLog(
    string  $action,
    ?string $tableName  = null,
    ?int    $recordId   = null,
    ?array  $oldValues  = null,
    ?array  $newValues  = null,
    ?int    $adminId    = null
): void {
    $oldValues = auditStripNoise($oldValues);
    $newValues = auditStripNoise($newValues);
    try {
        Database::query(
            'INSERT INTO audit_logs
                (admin_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $adminId,
                $action,
                $tableName,
                $recordId,
                $oldValues  ? json_encode($oldValues, JSON_UNESCAPED_UNICODE)  : null,
                $newValues  ? json_encode($newValues, JSON_UNESCAPED_UNICODE)  : null,
                getClientIP(),
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300),
            ]
        );
    } catch (Throwable) {
        // Audit log failure must not break the main flow
    }
}

// --- Set secure response headers ---
function setSecurityHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; "
         . "script-src 'self' 'unsafe-inline' https://js.stripe.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
         . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; "
         . "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; "
         . "img-src 'self' data: https:; "
         . "frame-src https://js.stripe.com; "
         . "connect-src 'self' https://api.stripe.com;");
}

// --- Paginate results ---
function paginate(int $total, int $perPage, int $currentPage): array
{
    $totalPages = (int) ceil($total / $perPage);
    $offset     = ($currentPage - 1) * $perPage;
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $currentPage,
        'total_pages' => $totalPages,
        'offset'      => max(0, $offset),
    ];
}
