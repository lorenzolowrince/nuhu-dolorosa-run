<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$adminRole   = Auth::role();
$roleLabels  = ['super_admin' => 'Super Admin', 'admin' => 'Admin', 'viewer' => 'Viewer', 'data_entry' => 'Data Entry'];
$roleColors  = ['super_admin' => '#7C3AED', 'admin' => '#2563eb', 'viewer' => '#6b7280', 'data_entry' => '#059669'];
$roleBg      = ['super_admin' => '#ede9fe', 'admin' => '#dbeafe', 'viewer' => '#f3f4f6', 'data_entry' => '#d1fae5'];
$adminUser   = Auth::user();
?>
<!-- Admin Sidebar -->
<aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-sidebar-brand">
        <div class="d-flex align-items-center gap-2">
            <div style="width:46px;height:46px;border-radius:50%;background:#fff;flex-shrink:0;
                         display:flex;align-items:center;justify-content:center;
                         box-shadow:0 0 0 3px rgba(255,255,255,0.25);">
                <img src="<?= APP_URL ?>/assets/images/nddk-logo.png" alt="NDDK"
                     style="width:34px;height:34px;object-fit:contain;">
            </div>
            <div>
                <div class="brand-name" style="font-size:13px;">Nuhu Dolorosa</div>
                <div class="brand-sub">Admin Panel</div>
            </div>
        </div>
        <!-- Role badge -->
        <div class="mt-2 px-1">
            <span style="font-size:11px;padding:2px 8px;border-radius:10px;font-weight:700;
                         background:<?= $roleBg[$adminRole] ?? '#f3f4f6' ?>;
                         color:<?= $roleColors[$adminRole] ?? '#6b7280' ?>;">
                <i class="fas fa-shield-alt me-1"></i><?= $roleLabels[$adminRole] ?? 'Admin' ?>
            </span>
            <div class="text-white opacity-75 small mt-1" style="font-size:11px;"><?= e($adminUser['full_name']) ?></div>
        </div>
    </div>

    <nav class="mt-2">
        <div class="admin-nav-label">Main</div>
        <a href="<?= APP_URL ?>/admin/dashboard.php" class="admin-nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>

        <div class="admin-nav-label">Participants</div>
        <a href="<?= APP_URL ?>/admin/registrations.php" class="admin-nav-link <?= $currentPage === 'registrations.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i> All Registrations
        </a>
        <a href="<?= APP_URL ?>/admin/registrations.php?status=Confirmed" class="admin-nav-link <?= ($currentPage === 'registrations.php' && ($_GET['status'] ?? '') === 'Confirmed') ? 'active' : '' ?>">
            <i class="fas fa-check-circle"></i> Confirmed
        </a>
        <a href="<?= APP_URL ?>/admin/registrations.php?status=Pending" class="admin-nav-link <?= ($currentPage === 'registrations.php' && ($_GET['status'] ?? '') === 'Pending') ? 'active' : '' ?>">
            <i class="fas fa-clock"></i> Pending
        </a>
        <a href="<?= APP_URL ?>/admin/registrations.php?status=PendingWithReceipt" class="admin-nav-link <?= ($currentPage === 'registrations.php' && ($_GET['status'] ?? '') === 'PendingWithReceipt') ? 'active' : '' ?>">
            <i class="fas fa-receipt"></i> Pending + Receipt
        </a>
        <?php if (Auth::can('participant_add')): ?>
        <a href="<?= APP_URL ?>/admin/participant-add.php" class="admin-nav-link <?= $currentPage === 'participant-add.php' ? 'active' : '' ?>">
            <i class="fas fa-user-plus"></i> Add Manual Entry
        </a>
        <?php endif; ?>

        <div class="admin-nav-label">Bank Transfer</div>
        <a href="<?= APP_URL ?>/admin/payments.php" class="admin-nav-link <?= $currentPage === 'payments.php' ? 'active' : '' ?>">
            <i class="fas fa-university"></i> Receipt Tracker
        </a>

        <?php if (Auth::can('reports') || Auth::can('export')): ?>
        <div class="admin-nav-label">Reports</div>
        <?php if (Auth::can('reports')): ?>
        <a href="<?= APP_URL ?>/admin/report.php" class="admin-nav-link <?= $currentPage === 'report.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-bar"></i> Event Report
        </a>
        <?php endif; ?>
        <?php if (Auth::can('export')): ?>
        <a href="<?= APP_URL ?>/admin/export.php?type=csv" class="admin-nav-link">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
        <a href="<?= APP_URL ?>/admin/export.php?type=shirts" class="admin-nav-link">
            <i class="fas fa-tshirt"></i> Shirt Summary
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (Auth::can('users') || Auth::can('audit_log') || Auth::can('settings') || Auth::can('permissions')): ?>
        <div class="admin-nav-label">System</div>
        <?php if (Auth::can('users')): ?>
        <a href="<?= APP_URL ?>/admin/users.php" class="admin-nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>">
            <i class="fas fa-user-shield"></i> Manage Users
        </a>
        <?php endif; ?>
        <?php if (Auth::can('permissions')): ?>
        <a href="<?= APP_URL ?>/admin/permissions.php" class="admin-nav-link <?= $currentPage === 'permissions.php' ? 'active' : '' ?>">
            <i class="fas fa-shield-alt"></i> Role Permissions
        </a>
        <?php endif; ?>
        <?php if (Auth::can('audit_log')): ?>
        <a href="<?= APP_URL ?>/admin/audit-log.php" class="admin-nav-link <?= $currentPage === 'audit-log.php' ? 'active' : '' ?>">
            <i class="fas fa-history"></i> Audit Log
        </a>
        <?php endif; ?>
        <?php if (Auth::can('settings')): ?>
        <a href="<?= APP_URL ?>/admin/settings.php" class="admin-nav-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
            <i class="fas fa-cog"></i> Settings
        </a>
        <?php endif; ?>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/admin/profile.php" class="admin-nav-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user-circle"></i> My Profile
        </a>
        <a href="<?= APP_URL ?>/" target="_blank" class="admin-nav-link">
            <i class="fas fa-external-link-alt"></i> View Website
        </a>
        <a href="<?= APP_URL ?>/admin/logout.php" class="admin-nav-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</aside>
