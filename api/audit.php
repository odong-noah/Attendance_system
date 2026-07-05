<?php
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');
Auth::startSession();
Auth::requireAdmin();

try {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = (int)($_GET['limit'] ?? 30);
    $offset = ($page - 1) * $limit;
    $action = sanitize($_GET['action'] ?? '');

    $where = ['1=1']; $params = [];
    if ($action) { $where[] = 'al.action LIKE ?'; $params[] = "%$action%"; }
    $w = implode(' AND ', $where);

    $total = (int)DB::run("SELECT COUNT(*) FROM audit_logs al WHERE $w", $params)->fetchColumn();
    $rows  = DB::run(
        "SELECT al.id, al.action, al.entity_type, al.entity_id, al.ip_address, al.created_at,
                COALESCE(CONCAT(u.first_name,' ',u.last_name), 'System') AS performed_by, u.role
         FROM audit_logs al LEFT JOIN users u ON al.user_id=u.id
         WHERE $w ORDER BY al.created_at DESC LIMIT $limit OFFSET $offset",
        $params
    )->fetchAll();
    jsonResponse(true, '', ['logs' => $rows, 'pagination' => paginate($total, $page, $limit)]);
} catch (Throwable $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
