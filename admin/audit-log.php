<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';
require_once APP_PATH . '/auth.php';

setSecurityHeaders();
Auth::requireCan('audit_log');

// ============================================================
// Display helpers
// ============================================================

// action => [label, icon, colour]
const AUDIT_ACTIONS = [
    'admin_login'          => ['Logged in',                    'sign-in-alt',    'success'],
    'admin_login_failed'   => ['Failed login attempt',         'user-lock',      'danger'],
    'admin_logout'         => ['Logged out',                   'sign-out-alt',   'secondary'],
    'manual_registration'  => ['Added participant manually',   'user-plus',      'success'],
    'update_participant'   => ['Edited participant details',   'user-edit',      'primary'],
    'update_race_details'  => ['Edited race details',          'running',        'primary'],
    'update_status'        => ['Changed registration status',  'exchange-alt',   'warning'],
    'update_receipt'       => ['Changed payment receipt',      'receipt',        'primary'],
    'delete_participant'   => ['Deleted participant',          'user-times',     'danger'],
    'export_csv'           => ['Exported participant list',    'file-csv',       'info'],
    'export_shirts'        => ['Exported shirt summary',       'tshirt',         'info'],
    'update_settings'      => ['Changed event settings',       'cog',            'warning'],
    'update_category'      => ['Changed event category',       'flag-checkered', 'warning'],
    'update_permissions'   => ['Changed role permissions',     'shield-alt',     'warning'],
    'reset_permissions'    => ['Reset permissions to default', 'undo',           'danger'],
    'create_admin_user'    => ['Created admin user',           'user-shield',    'success'],
    'edit_admin_user'      => ['Edited admin user',            'user-cog',       'primary'],
    'change_role'          => ['Changed admin role',           'user-tag',       'warning'],
    'toggle_user_active'   => ['Activated / deactivated user', 'toggle-on',      'warning'],
    'reset_password'       => ['Reset a user\'s password',     'key',            'warning'],
    'delete_admin_user'    => ['Deleted admin user',           'user-slash',     'danger'],
    'update_own_profile'   => ['Updated own profile',          'id-card',        'primary'],
    'change_own_password'  => ['Changed own password',         'key',            'secondary'],
    'change_password'      => ['Changed own password',         'key',            'secondary'],
];

const AUDIT_FIELD_LABELS = [
    'reference_number' => 'Reference no.',  'reference'        => 'Reference no.',
    'full_name'        => 'Full name',      'name'             => 'Name',
    'ic_number'        => 'IC number',      'contact_number'   => 'Phone',
    'email'            => 'Email',          'gender'           => 'Gender',
    'date_of_birth'    => 'Date of birth',  'age'              => 'Age',
    'address'          => 'Address',
    'emergency_contact_name'   => 'Emergency contact',
    'emergency_contact_number' => 'Emergency contact phone',
    'category'         => 'Event category', 'category_name'    => 'Event category',
    'category_id'      => 'Event category', 'distance'         => 'Distance',
    'race_category'    => 'Runner category',
    'event_shirt_size' => 'Event shirt size', 'finisher_shirt_size' => 'Finisher shirt size',
    'status'           => 'Registration status', 'registration_status' => 'Registration status',
    'payment_receipt'  => 'Payment receipt',
    'created_at'       => 'Created on',     'last_login'       => 'Last login',
    'username'         => 'Username',       'role'             => 'Role',
    'is_active'        => 'Active',         'username_entered' => 'Username entered',
    'description'      => 'Description',    'fee'              => 'Fee (RM)',
    'max_participants' => 'Max participants (0 = unlimited)',
    'rows'             => 'Rows exported',  'event_rows'       => 'Event shirt rows',
    'finisher_rows'    => 'Finisher shirt rows',
    'event_name'       => 'Event name',     'event_theme'      => 'Event theme',
    'event_date'       => 'Event date',     'event_start_time' => 'Start time',
    'event_venue'      => 'Venue',          'contact_name'     => 'Contact person',
    'contact_email'    => 'Contact email',  'contact_phone'    => 'Contact phone',
    'shirt_price'      => 'Shirt price (RM)',
    'shirt_extra_size_fee_1' => 'Extra fee 3XL–5XL (RM)',
    'shirt_extra_size_fee_2' => 'Extra fee 6XL–7XL (RM)',
    'registration_open'      => 'Registration open',
];

function auditActionMeta(string $action): array
{
    return AUDIT_ACTIONS[$action] ?? [ucfirst(str_replace('_', ' ', $action)), 'circle', 'secondary'];
}

function auditFieldLabel(string $key): string
{
    return AUDIT_FIELD_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
}

// Human-readable value as safe HTML
function auditFormatValue(string $key, mixed $value, array $categoryNames): string
{
    if ($value === null || $value === '') {
        return '<span class="text-muted fst-italic">(empty)</span>';
    }
    if (is_array($value)) {
        return e(json_encode($value, JSON_UNESCAPED_UNICODE));
    }
    $v = (string) $value;
    switch ($key) {
        case 'is_active':
        case 'registration_open':
            return $v === '1' ? 'Yes' : ($v === '0' ? 'No' : e($v));
        case 'category_id':
            return e($categoryNames[(int) $v] ?? '#' . $v);
        case 'role':
            return e(ucwords(str_replace('_', ' ', $v)));
        case 'payment_receipt':
            return '<a href="' . e(APP_URL . '/assets/uploads/receipts/' . rawurlencode($v)) . '" target="_blank" rel="noopener">'
                 . e($v) . ' <i class="fas fa-external-link-alt" style="font-size:9px"></i></a>';
        case 'last_login':
        case 'created_at':
            $ts = strtotime($v);
            return $ts ? e(date('d M Y, h:i A', $ts)) : e($v);
    }
    return nl2br(e($v));
}

// "Chrome on Android" from a user-agent string
function auditDevice(?string $ua): array
{
    $ua = (string) $ua;
    if ($ua === '') return ['Unknown device', 'question-circle'];

    $os = match (true) {
        str_contains($ua, 'Android')                                => 'Android',
        str_contains($ua, 'iPhone')                                 => 'iPhone',
        str_contains($ua, 'iPad')                                   => 'iPad',
        str_contains($ua, 'Windows')                                => 'Windows',
        str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'Mac',
        str_contains($ua, 'CrOS')                                   => 'Chromebook',
        str_contains($ua, 'Linux')                                  => 'Linux',
        default                                                     => 'Unknown OS',
    };
    $browser = match (true) {
        str_contains($ua, 'FBAN') || str_contains($ua, 'FBAV') => 'Facebook app',
        str_contains($ua, 'Instagram')                         => 'Instagram app',
        str_contains($ua, 'WhatsApp')                          => 'WhatsApp',
        str_contains($ua, 'Edg/') || str_contains($ua, 'EdgA/') || str_contains($ua, 'EdgiOS') => 'Edge',
        str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
        str_contains($ua, 'SamsungBrowser')                    => 'Samsung Internet',
        str_contains($ua, 'Firefox') || str_contains($ua, 'FxiOS') => 'Firefox',
        str_contains($ua, 'CriOS') || str_contains($ua, 'Chrome') => 'Chrome',
        str_contains($ua, 'Safari')                            => 'Safari',
        default                                                => 'Browser',
    };
    $icon = in_array($os, ['Android', 'iPhone'], true) ? 'mobile-alt' : ($os === 'iPad' ? 'tablet-alt' : 'desktop');
    return ["$browser on $os", $icon];
}

// ============================================================
// Filters & query
// ============================================================

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

$filterAction = sanitize($_GET['action_filter'] ?? '');
$filterAdmin  = (int)($_GET['admin_filter'] ?? 0);
$filterFrom   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from'] ?? '') ? $_GET['date_from'] : '';
$filterTo     = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to']   ?? '') ? $_GET['date_to']   : '';
$filterQ      = trim(sanitize($_GET['q'] ?? ''));

$where  = ['1=1'];
$params = [];

if ($filterAction !== '') {
    $where[]  = 'al.action = ?';
    $params[] = $filterAction;
}
if ($filterAdmin > 0) {
    $where[]  = 'al.admin_id = ?';
    $params[] = $filterAdmin;
}
if ($filterFrom !== '') {
    $where[]  = 'al.created_at >= ?';
    $params[] = $filterFrom . ' 00:00:00';
}
if ($filterTo !== '') {
    $where[]  = 'al.created_at <= ?';
    $params[] = $filterTo . ' 23:59:59';
}
if ($filterQ !== '') {
    $like     = '%' . $filterQ . '%';
    $where[]  = '(p.full_name LIKE ? OR p.reference_number LIKE ? OR tu.username LIKE ? OR al.ip_address LIKE ?
                  OR al.old_values LIKE ? OR al.new_values LIKE ?)';
    array_push($params, $like, $like, $like, $like, $like, $like);
}

$whereClause = implode(' AND ', $where);

// Joins that resolve the affected record to a readable name
$joins = "LEFT JOIN admin_users au      ON au.id = al.admin_id
          LEFT JOIN participants p      ON al.table_name = 'participants'     AND p.id  = al.record_id
          LEFT JOIN admin_users tu      ON al.table_name = 'admin_users'      AND tu.id = al.record_id
          LEFT JOIN event_categories ec ON al.table_name = 'event_categories' AND ec.id = al.record_id";

$totalRows = (int)(Database::fetchOne(
    "SELECT COUNT(*) AS c FROM audit_logs al {$joins} WHERE {$whereClause}",
    $params
)['c'] ?? 0);

$pager = paginate($totalRows, $perPage, $page);

$logs = Database::fetchAll(
    "SELECT al.*,
            au.full_name AS admin_name, au.username AS admin_username,
            p.full_name AS p_name, p.reference_number AS p_ref,
            tu.username AS target_username, tu.full_name AS target_name,
            ec.name AS cat_name
     FROM audit_logs al
     {$joins}
     WHERE {$whereClause}
     ORDER BY al.created_at DESC, al.id DESC
     LIMIT {$pager['per_page']} OFFSET {$pager['offset']}",
    $params
);

$distinctActions = Database::fetchAll("SELECT DISTINCT action FROM audit_logs ORDER BY action");
$adminUsers      = Database::fetchAll("SELECT id, full_name, username FROM admin_users ORDER BY full_name");
$categoryNames   = array_column(Database::fetchAll('SELECT id, name FROM event_categories'), 'name', 'id');

// Failed logins in the last 7 days — the main reason to keep an eye on IPs
$failedRecent = Database::fetchOne(
    "SELECT COUNT(*) AS n, COUNT(DISTINCT ip_address) AS ips
     FROM audit_logs WHERE action = 'admin_login_failed' AND created_at >= NOW() - INTERVAL 7 DAY"
);

$filterQuery = fn(array $extra = []) => http_build_query(array_filter([
    'action_filter' => $filterAction, 'admin_filter' => $filterAdmin ?: '',
    'date_from' => $filterFrom, 'date_to' => $filterTo, 'q' => $filterQ,
] + $extra, fn($v) => $v !== '' && $v !== null));

$pageTitle = 'Audit Log';
include PUBLIC_PATH . '/admin/includes/header.php';
?>

<style>
    .audit-action { display:inline-flex; align-items:center; gap:6px; font-weight:600; font-size:13px; }
    .audit-action i { width:26px; height:26px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:12px; flex-shrink:0; }
    .audit-target { font-size:12.5px; color:#4b5563; margin-top:2px; }
    .audit-changes { width:100%; font-size:12.5px; border-collapse:collapse; margin-top:4px; }
    .audit-changes th { font-weight:600; color:#6b7280; text-align:left; padding:3px 8px 3px 0; white-space:nowrap; vertical-align:top; width:1%; }
    .audit-changes td { padding:3px 6px; vertical-align:top; word-break:break-word; }
    .audit-changes .before { background:#fef2f2; color:#991b1b; border-radius:4px; text-decoration:line-through; text-decoration-color:rgba(153,27,27,.35); }
    .audit-changes .after  { background:#f0fdf4; color:#166534; border-radius:4px; }
    .audit-changes .arrow  { color:#9ca3af; padding:3px 2px; width:1%; }
    .audit-ip a { font-size:11px; }
    .admin-table .table td.audit-cell-changes { min-width:320px; }
</style>

<?php include PUBLIC_PATH . '/admin/includes/sidebar.php'; ?>

<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h1 class="admin-page-title">Audit Log</h1>
        </div>
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#auditHelp">
            <i class="fas fa-question-circle me-1"></i> How to read this log
        </button>
    </div>

    <div class="admin-content">
        <?= renderFlash() ?>

        <!-- Explanation -->
        <div class="collapse mb-4" id="auditHelp">
            <div class="admin-search-bar small">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <h6 class="fw-700 mb-2"><i class="fas fa-clipboard-list text-primary me-1"></i> What is recorded</h6>
                        <ul class="mb-2 ps-3">
                            <li>Every admin <strong>login, logout and failed login attempt</strong>.</li>
                            <li>Every change to <strong>participants</strong> (details, race info, extra shirts, status, receipts), <strong>event settings</strong>, <strong>categories</strong>, <strong>admin users</strong> and <strong>permissions</strong>.</li>
                            <li>Deleted participants and users — their details are kept here so you can still see what was removed.</li>
                            <li>Every <strong>data export</strong> (who downloaded participant data, and when).</li>
                        </ul>
                        <p class="mb-0">For edits, only the fields that actually changed are shown:
                            <span class="audit-changes"><span class="before px-1">old value</span></span> →
                            <span class="audit-changes"><span class="after px-1">new value</span></span>.
                            All times are <strong>Malaysia time (MYT)</strong>.</p>
                    </div>
                    <div class="col-lg-6">
                        <h6 class="fw-700 mb-2"><i class="fas fa-network-wired text-primary me-1"></i> What is the IP address for?</h6>
                        <p class="mb-2">The IP address is the internet address of the device/network the admin was using. Together with the device (e.g. <em>Chrome on Android</em>) it helps you:</p>
                        <ul class="mb-2 ps-3">
                            <li><strong>Spot suspicious logins</strong> — a login from an unfamiliar IP or location (e.g. another country) may mean a password was stolen.</li>
                            <li><strong>Detect password guessing</strong> — many <em>Failed login attempts</em> from the same IP means someone is trying to break in.</li>
                            <li><strong>Trace who did what</strong> — confirm that an action came from the same person/device as a login.</li>
                        </ul>
                        <p class="mb-0 text-muted">Mobile data and home Wi-Fi change IP addresses often, so a new IP on its own is normal. Click <em>lookup</em> to see the approximate location and internet provider.</p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ((int)($failedRecent['n'] ?? 0) > 0): ?>
            <div class="alert alert-warning d-flex align-items-center gap-2 py-2 small">
                <i class="fas fa-user-lock"></i>
                <div>
                    <strong><?= (int)$failedRecent['n'] ?> failed login attempt<?= (int)$failedRecent['n'] === 1 ? '' : 's' ?></strong>
                    in the last 7 days from <?= (int)$failedRecent['ips'] ?> IP address<?= (int)$failedRecent['ips'] === 1 ? '' : 'es' ?>.
                    <a href="?action_filter=admin_login_failed" class="alert-link">Review</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="admin-search-bar mb-4">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-600">Search</label>
                    <input type="text" name="q" class="form-control form-control-sm" value="<?= e($filterQ) ?>"
                           placeholder="Name, reference, IP, value…">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-600">Action</label>
                    <select name="action_filter" class="form-select form-select-sm">
                        <option value="">All actions</option>
                        <?php foreach ($distinctActions as $a): ?>
                            <option value="<?= e($a['action']) ?>" <?= $filterAction === $a['action'] ? 'selected' : '' ?>>
                                <?= e(auditActionMeta($a['action'])[0]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small fw-600">Admin user</label>
                    <select name="admin_filter" class="form-select form-select-sm">
                        <option value="">All users</option>
                        <?php foreach ($adminUsers as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $filterAdmin === (int)$u['id'] ? 'selected' : '' ?>>
                                <?= e($u['full_name']) ?> (<?= e($u['username']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <label class="form-label small fw-600">From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filterFrom) ?>">
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <label class="form-label small fw-600">To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filterTo) ?>">
                </div>
                <div class="col-lg-1 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill" title="Filter">
                        <i class="fas fa-search"></i>
                    </button>
                    <a href="audit-log.php" class="btn btn-outline-secondary btn-sm" title="Clear filters">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-muted small">
                <?php if ($totalRows > 0): ?>
                    Showing <?= number_format($pager['offset'] + 1) ?>–<?= number_format(min($pager['offset'] + $perPage, $totalRows)) ?>
                    of <?= number_format($totalRows) ?> entries
                <?php else: ?>
                    No entries found
                <?php endif; ?>
            </span>
        </div>

        <div class="admin-table">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>When (MYT)</th>
                            <th>Who</th>
                            <th>What happened</th>
                            <th>Details / changes</th>
                            <th>Device &amp; IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log):
                            [$label, $icon, $colour] = auditActionMeta($log['action']);
                            $old = $log['old_values'] ? (json_decode($log['old_values'], true) ?: []) : [];
                            $new = $log['new_values'] ? (json_decode($log['new_values'], true) ?: []) : [];
                            // Legacy entries stored the raw form, including the CSRF token
                            $old = auditStripNoise($old) ?? [];
                            $new = auditStripNoise($new) ?? [];

                            // Readable name of the affected record
                            $target = '';
                            switch ($log['table_name']) {
                                case 'participants':
                                    $pName = $log['p_name'] ?? ($old['full_name'] ?? $new['full_name'] ?? $new['name'] ?? null);
                                    $pRef  = $log['p_ref']  ?? ($old['reference_number'] ?? $new['reference_number'] ?? $new['reference'] ?? null);
                                    if ($log['record_id']) {
                                        $text = $pName ? e($pName) . ($pRef ? ' <span class="text-muted">· ' . e($pRef) . '</span>' : '') : 'Participant #' . (int)$log['record_id'];
                                        $target = $log['p_name']
                                            ? '<a href="participant-view.php?id=' . (int)$log['record_id'] . '">' . $text . '</a>'
                                            : $text . ' <span class="badge bg-light text-muted border">deleted</span>';
                                        $target = '<i class="fas fa-user me-1 text-muted"></i>' . $target;
                                    }
                                    break;
                                case 'admin_users':
                                    $uName = $log['target_username'] ?? ($old['username'] ?? $new['username'] ?? null);
                                    if ($log['record_id'] && !in_array($log['action'], ['admin_login', 'admin_logout'], true)) {
                                        $target = '<i class="fas fa-user-shield me-1 text-muted"></i>'
                                                . ($uName ? e($uName) : 'User #' . (int)$log['record_id'])
                                                . ($log['target_username'] ? '' : ' <span class="badge bg-light text-muted border">deleted</span>');
                                    }
                                    break;
                                case 'event_categories':
                                    $target = '<i class="fas fa-flag-checkered me-1 text-muted"></i>' . e($log['cat_name'] ?? ('Category #' . (int)$log['record_id']));
                                    break;
                            }

                            $isUpdate = $old && $new;
                            $noChange = false;
                            if ($isUpdate) {
                                // Older entries sometimes logged fields that did not actually change
                                [$old, $new] = auditDiff($old, $new);
                                $noChange    = !$new;
                            }
                            $fields = array_unique(array_merge(array_keys($old), array_keys($new)));
                            [$deviceText, $deviceIcon] = auditDevice($log['user_agent']);
                        ?>
                            <tr>
                                <td class="small text-nowrap">
                                    <div class="fw-600"><?= date('d M Y', strtotime($log['created_at'])) ?></div>
                                    <div class="text-muted"><?= date('h:i:s A', strtotime($log['created_at'])) ?></div>
                                </td>
                                <td>
                                    <?php if ($log['admin_name']): ?>
                                        <div class="fw-600 small"><?= e($log['admin_name']) ?></div>
                                        <div class="text-muted" style="font-size:11px;">@<?= e($log['admin_username']) ?></div>
                                    <?php elseif ($log['action'] === 'admin_login_failed'): ?>
                                        <span class="text-danger small"><i class="fas fa-user-secret me-1"></i>Unknown</span>
                                    <?php elseif ($log['record_id'] && in_array($log['action'], ['admin_login', 'admin_logout'], true)): ?>
                                        <span class="text-muted small">Deleted user #<?= (int)$log['record_id'] ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">System</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="audit-action text-<?= $colour ?>">
                                        <i class="fas fa-<?= $icon ?> bg-<?= $colour ?> bg-opacity-10"></i>
                                        <span class="text-dark"><?= e($label) ?></span>
                                    </span>
                                    <?php if ($target): ?><div class="audit-target"><?= $target ?></div><?php endif; ?>
                                </td>
                                <td class="audit-cell-changes">
                                    <?php if ($noChange): ?>
                                        <span class="text-muted small fst-italic">Saved without changing anything</span>
                                    <?php elseif (!$fields): ?>
                                        <span class="text-muted small">—</span>
                                    <?php else:
                                        $collapse = count($fields) > 5;
                                        $boxId    = 'changes-' . (int)$log['id'];
                                        $summary  = $isUpdate
                                            ? count($fields) . ' field' . (count($fields) === 1 ? '' : 's') . ' changed'
                                            : ($old ? 'Details of the removed record' : 'Details');
                                    ?>
                                        <?php if ($collapse): ?>
                                            <button class="btn btn-link btn-sm p-0 text-primary small" data-bs-toggle="collapse" data-bs-target="#<?= $boxId ?>">
                                                <i class="fas fa-list me-1"></i><?= e($summary) ?> — view
                                            </button>
                                        <?php elseif (!$isUpdate): ?>
                                            <div class="text-muted" style="font-size:11px;"><?= e($summary) ?></div>
                                        <?php endif; ?>
                                        <div class="<?= $collapse ? 'collapse' : '' ?>" id="<?= $boxId ?>">
                                            <table class="audit-changes">
                                                <?php foreach ($fields as $f): ?>
                                                    <tr>
                                                        <th><?= e(auditFieldLabel((string)$f)) ?></th>
                                                        <?php if ($isUpdate): ?>
                                                            <td class="before"><?= auditFormatValue((string)$f, $old[$f] ?? null, $categoryNames) ?></td>
                                                            <td class="arrow"><i class="fas fa-arrow-right" style="font-size:10px"></i></td>
                                                            <td class="after"><?= auditFormatValue((string)$f, $new[$f] ?? null, $categoryNames) ?></td>
                                                        <?php else: ?>
                                                            <td><?= auditFormatValue((string)$f, $old[$f] ?? $new[$f] ?? null, $categoryNames) ?></td>
                                                        <?php endif; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="small audit-ip text-nowrap">
                                    <div title="<?= e($log['user_agent'] ?? '') ?>">
                                        <i class="fas fa-<?= $deviceIcon ?> text-muted me-1"></i><?= e($deviceText) ?>
                                    </div>
                                    <?php if (!empty($log['ip_address'])): ?>
                                        <div class="text-muted">
                                            <code class="text-muted"><?= e($log['ip_address']) ?></code>
                                            <a href="https://ipinfo.io/<?= rawurlencode($log['ip_address']) ?>" target="_blank" rel="noopener noreferrer"
                                               title="Look up the approximate location and internet provider of this IP">lookup</a>
                                            · <a href="?<?= e($filterQuery(['q' => $log['ip_address']])) ?>" title="Show all activity from this IP">all</a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-5">No audit log entries found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($pager['total_pages'] > 1):
            $last  = $pager['total_pages'];
            $pages = array_unique(array_filter(
                array_merge([1], range(max(1, $page - 2), min($last, $page + 2)), [$last]),
                fn($p) => $p >= 1 && $p <= $last
            ));
            sort($pages);
        ?>
            <nav class="mt-4">
                <ul class="pagination pagination-sm justify-content-center flex-wrap">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= e($filterQuery(['page' => max(1, $page - 1)])) ?>">&laquo;</a>
                    </li>
                    <?php $prev = 0; foreach ($pages as $p): ?>
                        <?php if ($p - $prev > 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= e($filterQuery(['page' => $p])) ?>"><?= $p ?></a>
                        </li>
                    <?php $prev = $p; endforeach; ?>
                    <li class="page-item <?= $page >= $last ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= e($filterQuery(['page' => min($last, $page + 1)])) ?>">&raquo;</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
