<?php
declare(strict_types=1);

/**
 * Module registry and default role permissions.
 * super_admin always bypasses all checks in code — never stored here.
 */
class Permissions
{
    // Each module: label shown in matrix, FA icon, sidebar section, which actions apply.
    const MODULES = [
        'dashboard'       => ['label' => 'Dashboard',       'icon' => 'tachometer-alt', 'section' => 'Main',          'actions' => ['view']],
        'registrations'   => ['label' => 'Registrations',   'icon' => 'users',           'section' => 'Participants',  'actions' => ['view', 'edit', 'delete']],
        'participant_add' => ['label' => 'Add Participant',  'icon' => 'user-plus',       'section' => 'Participants',  'actions' => ['view']],
        'payments'        => ['label' => 'Receipt Tracker',  'icon' => 'university',      'section' => 'Bank Transfer', 'actions' => ['view', 'edit']],
        'reports'         => ['label' => 'Event Report',     'icon' => 'chart-bar',       'section' => 'Reports',      'actions' => ['view']],
        'export'          => ['label' => 'Export Data',      'icon' => 'file-export',     'section' => 'Reports',      'actions' => ['view']],
        'audit_log'       => ['label' => 'Audit Log',        'icon' => 'history',         'section' => 'System',       'actions' => ['view']],
        'settings'        => ['label' => 'System Settings',  'icon' => 'cog',             'section' => 'System',       'actions' => ['view', 'edit']],
        'users'           => ['label' => 'Admin Users',      'icon' => 'user-shield',     'section' => 'System',       'actions' => ['view', 'edit', 'delete']],
        'permissions'     => ['label' => 'Role Permissions', 'icon' => 'shield-alt',      'section' => 'System',       'actions' => ['view', 'edit']],
    ];

    // Sidebar section display order
    const SECTIONS = ['Main', 'Participants', 'Bank Transfer', 'Reports', 'System'];

    // Hardcoded defaults — used as fallback when the DB table is empty or unavailable.
    const DEFAULTS = [
        'admin' => [
            'dashboard'       => ['view' => 1, 'edit' => 0, 'delete' => 0],
            'registrations'   => ['view' => 1, 'edit' => 1, 'delete' => 0],
            'participant_add' => ['view' => 1, 'edit' => 1, 'delete' => 0],
            'payments'        => ['view' => 1, 'edit' => 1, 'delete' => 0],
            'reports'         => ['view' => 1, 'edit' => 0, 'delete' => 0],
            'export'          => ['view' => 1, 'edit' => 0, 'delete' => 0],
            'audit_log'       => ['view' => 0, 'edit' => 0, 'delete' => 0],
            'settings'        => ['view' => 0, 'edit' => 0, 'delete' => 0],
            'users'           => ['view' => 0, 'edit' => 0, 'delete' => 0],
            'permissions'     => ['view' => 0, 'edit' => 0, 'delete' => 0],
        ],
        'viewer' => [
            'dashboard'       => ['view' => 1, 'edit' => 0, 'delete' => 0],
            'registrations'   => ['view' => 1, 'edit' => 0, 'delete' => 0],
            'participant_add' => ['view' => 0, 'edit' => 0, 'delete' => 0],
            'payments'        => ['view' => 1, 'edit' => 0, 'delete' => 0],
            'reports'         => ['view' => 0, 'edit' => 0, 'delete' => 0],
            'export'          => ['view' => 0, 'edit' => 0, 'delete' => 0],
            'audit_log'       => ['view' => 0, 'edit' => 0, 'delete' => 0],
            'settings'        => ['view' => 0, 'edit' => 0, 'delete' => 0],
            'users'           => ['view' => 0, 'edit' => 0, 'delete' => 0],
            'permissions'     => ['view' => 0, 'edit' => 0, 'delete' => 0],
        ],
        'data_entry' => [
            'dashboard'       => ['view' => 1, 'edit' => 0, 'delete' => 0],
            'registrations'   => ['view' => 1, 'edit' => 1, 'delete' => 0],
            'participant_add' => ['view' => 1, 'edit' => 0, 'delete' => 0],
            'payments'        => ['view' => 1, 'edit' => 0, 'delete' => 0],
            'reports'         => ['view' => 0, 'edit' => 0, 'delete' => 0],
            'export'          => ['view' => 0, 'edit' => 0, 'delete' => 0],
            'audit_log'       => ['view' => 0, 'edit' => 0, 'delete' => 0],
            'settings'        => ['view' => 0, 'edit' => 0, 'delete' => 0],
            'users'           => ['view' => 0, 'edit' => 0, 'delete' => 0],
            'permissions'     => ['view' => 0, 'edit' => 0, 'delete' => 0],
        ],
    ];
}
