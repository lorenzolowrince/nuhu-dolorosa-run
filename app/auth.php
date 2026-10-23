<?php
declare(strict_types=1);

// ============================================================
// Authentication — Admin Login / Session Management
// ============================================================

class Auth
{
    private const SESSION_KEY      = 'admin_user';
    public  const MAX_ATTEMPTS     = 5;
    public  const LOCKOUT_MINUTES  = 15;

    // Per-request cache for the permissions version string (avoids >1 DB query per request)
    private static ?string $permVersionCache = null;

    // --- Start / Resume session ---
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // --- Attempt admin login ---
    public static function attempt(string $username, string $password): bool
    {
        self::startSession();

        $ip = getClientIP();

        // Rate-limit check
        if (self::isLockedOut($ip)) {
            return false;
        }

        // Fetch user
        $user = Database::fetchOne(
            'SELECT * FROM admin_users WHERE (username = ? OR email = ?) AND is_active = 1',
            [$username, $username]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            self::recordFailedAttempt($ip);
            auditLog('admin_login_failed', 'admin_users', $user ? (int) $user['id'] : null,
                     null, ['username_entered' => mb_substr($username, 0, 100)]);
            return false;
        }

        // Successful login — clear attempts, regenerate session
        self::clearAttempts($ip);
        session_regenerate_id(true);

        $_SESSION[self::SESSION_KEY] = [
            'id'        => $user['id'],
            'username'  => $user['username'],
            'full_name' => $user['full_name'],
            'email'     => $user['email'],
            'role'      => $user['role'] ?? 'admin',
        ];

        Database::execute(
            'UPDATE admin_users SET last_login = NOW() WHERE id = ?',
            [$user['id']]
        );

        auditLog('admin_login', 'admin_users', (int) $user['id'], null, null, (int) $user['id']);

        return true;
    }

    // --- Check whether current request is authenticated ---
    public static function check(): bool
    {
        self::startSession();
        return isset($_SESSION[self::SESSION_KEY]);
    }

    // --- Get current admin user array ---
    public static function user(): ?array
    {
        self::startSession();
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    // --- Require authentication; redirect if not logged in ---
    public static function requireAuth(string $loginUrl = '/admin/login.php'): void
    {
        if (!self::check()) {
            setFlash('error', 'Please log in to access the admin panel.');
            redirect($loginUrl);
        }
    }

    // --- Get current user's role ---
    public static function role(): string
    {
        return self::user()['role'] ?? 'viewer';
    }

    // --- Check if current user has one of the given roles ---
    public static function hasRole(string ...$roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    // --- Require specific role(s); redirect with error if not allowed ---
    public static function requireRole(string ...$roles): void
    {
        self::requireAuth(APP_URL . '/admin/login.php');
        if (!self::hasRole(...$roles)) {
            setFlash('error', 'You do not have permission to access that page.');
            redirect(APP_URL . '/admin/dashboard.php');
        }
    }

    // ============================================================
    // Module-level permission checks (role permissions matrix)
    // ============================================================

    // --- Check if current user has permission for a module + action ---
    public static function can(string $module, string $action = 'view'): bool
    {
        self::startSession();

        // super_admin bypasses all permission checks unconditionally
        if (self::role() === 'super_admin') return true;

        // Reload if session cache is stale (permissions were updated by another session)
        $dbVersion = self::getPermissionsVersion();
        if (($_SESSION['permissions_version'] ?? null) !== $dbVersion
            || !isset($_SESSION['role_permissions'])) {
            self::loadRolePermissions();
        }

        return (bool)(($_SESSION['role_permissions'][$module][$action] ?? false));
    }

    // --- Require module permission; redirect to dashboard if denied ---
    public static function requireCan(string $module, string $action = 'view'): void
    {
        self::requireAuth(APP_URL . '/admin/login.php');
        if (!self::can($module, $action)) {
            setFlash('error', 'You do not have permission to access that page.');
            redirect(APP_URL . '/admin/dashboard.php');
        }
    }

    // --- Force-reload permissions from DB into session (call after saving matrix) ---
    public static function refreshPermissions(): void
    {
        self::startSession();
        unset($_SESSION['role_permissions'], $_SESSION['permissions_version']);
        self::$permVersionCache = null;
        self::loadRolePermissions();
    }

    // --- Load role permissions from DB into session; falls back to hardcoded defaults ---
    private static function loadRolePermissions(): void
    {
        $role    = self::role();
        $version = self::getPermissionsVersion();

        try {
            $rows = Database::fetchAll(
                'SELECT module, can_view, can_edit, can_delete FROM role_permissions WHERE role = ?',
                [$role]
            );
            if (!empty($rows)) {
                $perms = [];
                foreach ($rows as $row) {
                    $perms[$row['module']] = [
                        'view'   => (bool) $row['can_view'],
                        'edit'   => (bool) $row['can_edit'],
                        'delete' => (bool) $row['can_delete'],
                    ];
                }
                $_SESSION['role_permissions']    = $perms;
                $_SESSION['permissions_version'] = $version;
                return;
            }
        } catch (\Throwable $e) {
            // Table may not exist yet — use hardcoded defaults
        }

        require_once __DIR__ . '/permissions.php';
        $_SESSION['role_permissions']    = Permissions::DEFAULTS[$role] ?? [];
        $_SESSION['permissions_version'] = $version;
    }

    // --- Get a request-scoped cached permissions version from system_settings ---
    private static function getPermissionsVersion(): string
    {
        if (self::$permVersionCache === null) {
            try {
                $row = Database::fetchOne(
                    "SELECT setting_value FROM system_settings WHERE setting_key = 'permissions_version'"
                );
                self::$permVersionCache = $row['setting_value'] ?? '0';
            } catch (\Throwable $e) {
                self::$permVersionCache = '0';
            }
        }
        return self::$permVersionCache;
    }

    // --- Refresh current user's session data from DB (call after profile update) ---
    public static function refreshSession(): void
    {
        self::startSession();
        $user = self::user();
        if (!$user) return;
        $fresh = Database::fetchOne(
            'SELECT id, username, full_name, email, role FROM admin_users WHERE id = ? AND is_active = 1',
            [(int) $user['id']]
        );
        if (!$fresh) return;
        $_SESSION[self::SESSION_KEY] = [
            'id'        => $fresh['id'],
            'username'  => $fresh['username'],
            'full_name' => $fresh['full_name'],
            'email'     => $fresh['email'],
            'role'      => $fresh['role'],
        ];
    }

    // --- Logout ---
    public static function logout(): void
    {
        self::startSession();
        $user = self::user();
        if ($user) {
            auditLog('admin_logout', 'admin_users', (int) $user['id'], null, null, (int) $user['id']);
        }
        $_SESSION = [];
        session_destroy();
        session_start();
        session_regenerate_id(true);
    }

    // --- Rate limiting helpers ---
    public static function isLockedOut(string $ip): bool
    {
        $count = Database::fetchOne(
            'SELECT COUNT(*) AS cnt FROM login_attempts
             WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [$ip, self::LOCKOUT_MINUTES]
        );
        return ($count['cnt'] ?? 0) >= self::MAX_ATTEMPTS;
    }

    private  static function recordFailedAttempt(string $ip): void
    {
        Database::execute(
            'INSERT INTO login_attempts (ip_address) VALUES (?)',
            [$ip]
        );
    }

    private static function clearAttempts(string $ip): void
    {
        Database::execute(
            'DELETE FROM login_attempts WHERE ip_address = ?',
            [$ip]
        );
    }

    public static function remainingLockoutSeconds(string $ip): int
    {
        $oldest = Database::fetchOne(
            'SELECT MIN(attempted_at) AS first_attempt FROM login_attempts
             WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [$ip, self::LOCKOUT_MINUTES]
        );
        if (!$oldest || !$oldest['first_attempt']) return 0;
        $unlockAt = strtotime($oldest['first_attempt']) + self::LOCKOUT_MINUTES * 60;
        return max(0, $unlockAt - time());
    }

    public  static function failedAttemptsCount(string $ip): int
    {
        $row = Database::fetchOne(
            'SELECT COUNT(*) AS cnt FROM login_attempts
             WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [$ip, self::LOCKOUT_MINUTES]
        );
        return (int)($row['cnt'] ?? 0);
    }
}
