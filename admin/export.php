<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';
require_once APP_PATH . '/auth.php';

setSecurityHeaders();
Auth::requireCan('export');

$type     = sanitize($_GET['type']     ?? 'csv');
$status   = sanitize($_GET['status']   ?? '');
$category = sanitize($_GET['category'] ?? '');
$search   = sanitize($_GET['search']   ?? '');

// ---- CSV Export ----
if ($type === 'csv') {
    $where  = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[]  = '(p.full_name LIKE ? OR p.ic_number LIKE ? OR p.email LIKE ? OR p.reference_number LIKE ?)';
        $like     = '%' . $search . '%';
        $params   = array_merge($params, [$like, $like, $like, $like]);
    }
    if ($status   !== '') { $where[] = 'p.registration_status = ?'; $params[] = $status; }
    if ($category !== '') { $where[] = 'p.category_id = ?';         $params[] = $category; }

    $whereClause = implode(' AND ', $where);

    $rows = Database::fetchAll(
        "SELECT p.reference_number, p.full_name, p.ic_number, p.contact_number, p.email,
                p.gender, p.age, p.date_of_birth, p.address,
                p.emergency_contact_name, p.emergency_contact_number,
                ec.name AS category_name, ec.distance,
                so.size AS shirt_size, so.quantity AS shirt_qty,
                py.gateway, py.total_amount, py.status AS payment_status, py.payment_date,
                p.registration_status, p.created_at
         FROM participants p
         JOIN event_categories ec ON ec.id = p.category_id
         LEFT JOIN payments py ON py.participant_id = p.id
         LEFT JOIN shirt_orders so ON so.participant_id = p.id
         WHERE {$whereClause}
         ORDER BY p.created_at ASC",
        $params
    );

    $filename = 'registrations_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $fp = fopen('php://output', 'w');
    // BOM for Excel UTF-8 compatibility
    fwrite($fp, "\xEF\xBB\xBF");

    fputcsv($fp, [
        'Reference Number', 'Full Name', 'IC Number', 'Contact', 'Email',
        'Gender', 'Age', 'Date of Birth', 'Address',
        'Emergency Contact', 'Emergency Phone',
        'Category', 'Distance',
        'Shirt Size', 'Shirt Qty',
        'Payment Gateway', 'Amount (RM)', 'Payment Status', 'Payment Date',
        'Registration Status', 'Registered At',
    ]);

    foreach ($rows as $r) {
        fputcsv($fp, [
            $r['reference_number'],
            $r['full_name'],
            $r['ic_number'],
            $r['contact_number'],
            $r['email'],
            $r['gender'],
            $r['age'],
            $r['date_of_birth'],
            $r['address'],
            $r['emergency_contact_name'],
            $r['emergency_contact_number'],
            $r['category_name'],
            $r['distance'],
            shirtSizeLabel($r['shirt_size'] ?? ''),
            $r['shirt_qty']  ?? '',
            $r['gateway']    ?? '',
            $r['total_amount'] ?? '',
            $r['payment_status'] ?? '',
            $r['payment_date']   ?? '',
            $r['registration_status'],
            $r['created_at'],
        ]);
    }

    fclose($fp);
    auditLog('export_csv', 'participants', null, null, ['rows' => count($rows)], (int)(Auth::user()['id']));
    exit;
}

// ---- Shirt Summary Export ----
if ($type === 'shirts') {
    // Event T-Shirt sizes — all confirmed participants
    $eventShirts = Database::fetchAll(
        "SELECT p.event_shirt_size AS size, COUNT(*) AS total
         FROM participants p
         WHERE p.registration_status = 'Confirmed'
           AND p.event_shirt_size IS NOT NULL AND p.event_shirt_size != ''
         GROUP BY p.event_shirt_size
         ORDER BY FIELD(p.event_shirt_size,'2X','X','S','M','L','XL','2XL','3XL','4XL','5XL','6XL','7XL')"
    );

    // Finisher T-Shirt sizes — confirmed 43KM participants only
    $finisherShirts = Database::fetchAll(
        "SELECT p.finisher_shirt_size AS size, COUNT(*) AS total
         FROM participants p
         JOIN event_categories ec ON ec.id = p.category_id
         WHERE p.registration_status = 'Confirmed'
           AND ec.distance = '43KM'
           AND p.finisher_shirt_size IS NOT NULL AND p.finisher_shirt_size != ''
         GROUP BY p.finisher_shirt_size
         ORDER BY FIELD(p.finisher_shirt_size,'2X','X','S','M','L','XL','2XL','3XL','4XL','5XL','6XL','7XL')"
    );

    // Per-category event shirt breakdown
    $byCat = Database::fetchAll(
        "SELECT ec.name AS category, ec.distance, p.event_shirt_size AS size, COUNT(*) AS total
         FROM participants p
         JOIN event_categories ec ON ec.id = p.category_id
         WHERE p.registration_status = 'Confirmed'
           AND p.event_shirt_size IS NOT NULL AND p.event_shirt_size != ''
         GROUP BY ec.id, p.event_shirt_size
         ORDER BY ec.name, FIELD(p.event_shirt_size,'2X','X','S','M','L','XL','2XL','3XL','4XL','5XL','6XL','7XL')"
    );

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="shirt_summary_' . date('Ymd_His') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $fp = fopen('php://output', 'w');
    fwrite($fp, "\xEF\xBB\xBF");

    // Section 1 — Event T-Shirts
    fputcsv($fp, ['EVENT T-SHIRTS — Confirmed Participants (' . date('d M Y') . ')']);
    fputcsv($fp, ['Size', 'Count']);
    if (empty($eventShirts)) {
        fputcsv($fp, ['(no confirmed registrations)']);
    } else {
        foreach ($eventShirts as $r) {
            fputcsv($fp, [shirtSizeLabel($r['size']), $r['total']]);
        }
    }

    fputcsv($fp, []);

    // Section 2 — Finisher T-Shirts
    fputcsv($fp, ['FINISHER T-SHIRTS — Confirmed 43KM Participants']);
    fputcsv($fp, ['Size', 'Count']);
    if (empty($finisherShirts)) {
        fputcsv($fp, ['(none)']);
    } else {
        foreach ($finisherShirts as $r) {
            fputcsv($fp, [shirtSizeLabel($r['size']), $r['total']]);
        }
    }

    fputcsv($fp, []);

    // Section 3 — Per-category breakdown
    fputcsv($fp, ['PER-CATEGORY BREAKDOWN — Event T-Shirts']);
    fputcsv($fp, ['Category', 'Distance', 'Size', 'Count']);
    if (empty($byCat)) {
        fputcsv($fp, ['(no data)']);
    } else {
        foreach ($byCat as $r) {
            fputcsv($fp, [$r['category'], $r['distance'], shirtSizeLabel($r['size']), $r['total']]);
        }
    }

    fclose($fp);
    auditLog('export_shirts', 'participants', null, null,
             ['event_rows' => count($eventShirts), 'finisher_rows' => count($finisherShirts)],
             (int)(Auth::user()['id']));
    exit;
}

// Invalid type — redirect
redirect(APP_URL . '/admin/dashboard.php');
