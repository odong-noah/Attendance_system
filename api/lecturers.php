<?php
// api/lecturers.php — Lecturer CRUD (super_admin only)
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');
Auth::startSession();
Auth::requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$admin  = Auth::user();

try {

// ── GET ───────────────────────────────────────────────────────
if ($method === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $row = DB::run(
            "SELECT u.id, u.employee_id, u.first_name, u.last_name,
                    u.email, u.phone, u.role, u.program_id, u.is_active,
                    u.last_login, u.created_at, p.name AS program_name
             FROM users u LEFT JOIN programs p ON u.program_id = p.id
             WHERE u.id = ? AND u.role = 'lecturer'", [$id]
        )->fetch();
        if (!$row) jsonResponse(false, 'Lecturer not found.', null, 404);

        $courses = DB::run(
            "SELECT lca.id AS assignment_id, cu.id, cu.code, cu.name,
                    cu.credit_units, lca.academic_year, lca.semester
             FROM lecturer_course_assignments lca
             JOIN course_units cu ON lca.course_unit_id = cu.id
             WHERE lca.lecturer_id = ? ORDER BY cu.code", [$id]
        )->fetchAll();
        $row['course_assignments'] = $courses;
        jsonResponse(true, '', $row);
    }

    $search  = sanitize($_GET['search']  ?? '');
    $progId  = (int)($_GET['program_id'] ?? 0);
    $page    = max(1, (int)($_GET['page']  ?? 1));
    $limit   = (int)($_GET['limit'] ?? DEFAULT_PAGE_LIMIT);
    $offset  = ($page - 1) * $limit;

    $where = ["u.role='lecturer'"]; $params = [];
    if ($search) {
        $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.employee_id LIKE ?)";
        $s = "%{$search}%"; array_push($params, $s, $s, $s, $s);
    }
    if ($progId) { $where[] = 'u.program_id=?'; $params[] = $progId; }

    $w     = implode(' AND ', $where);
    $total = (int)DB::run("SELECT COUNT(*) FROM users u WHERE $w", $params)->fetchColumn();
    $rows  = DB::run(
        "SELECT u.id, u.employee_id, u.first_name, u.last_name,
                u.email, u.phone, u.is_active, u.last_login, u.created_at,
                p.name AS program_name, p.code AS program_code,
                (SELECT COUNT(*) FROM lecturer_course_assignments lca
                 WHERE lca.lecturer_id = u.id) AS course_count
         FROM users u LEFT JOIN programs p ON u.program_id = p.id
         WHERE $w ORDER BY u.first_name LIMIT $limit OFFSET $offset",
        $params
    )->fetchAll();
    jsonResponse(true, '', ['lecturers' => $rows, 'pagination' => paginate($total, $page, $limit)]);
}

// ── POST: create ──────────────────────────────────────────────
if ($method === 'POST') {
    $body = requireJson();
    foreach (['first_name','last_name','email','password','program_id'] as $f)
        if (empty($body[$f])) jsonResponse(false, "Field '$f' is required.");

    $email = strtolower(trim($body['email']));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'Invalid email address.');
    if (DB::run("SELECT id FROM users WHERE email=?", [$email])->fetch())
        jsonResponse(false, 'A user with this email already exists.');
    if (!validatePassword($body['password']))
        jsonResponse(false, 'Password must be at least 8 characters with uppercase, lowercase and a number.');

    $year  = date('Y');
    $count = (int)DB::run("SELECT COUNT(*) FROM users WHERE role='lecturer'")->fetchColumn() + 1;
    $empId = "LEC-{$year}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    $hash  = password_hash($body['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

    DB::conn()->beginTransaction();
    DB::run(
        "INSERT INTO users (employee_id, first_name, last_name, email, phone,
                            password_hash, role, program_id, created_by)
         VALUES (?,?,?,?,?,?,?,?,?)",
        [
            $empId, sanitize($body['first_name']), sanitize($body['last_name']),
            $email, sanitize($body['phone'] ?? ''), $hash, 'lecturer',
            (int)$body['program_id'], $admin['id']
        ]
    );
    $newId = (int)DB::conn()->lastInsertId();

    if (!empty($body['course_unit_ids']) && is_array($body['course_unit_ids'])) {
        $ay  = sanitize($body['academic_year'] ?? currentAcademicYear());
        $sem = in_array($body['semester'] ?? '', ['I','II']) ? $body['semester'] : 'I';
        foreach ($body['course_unit_ids'] as $cuId) {
            DB::run(
                "INSERT IGNORE INTO lecturer_course_assignments
                   (lecturer_id, course_unit_id, academic_year, semester, assigned_by)
                 VALUES (?,?,?,?,?)",
                [$newId, (int)$cuId, $ay, $sem, $admin['id']]
            );
        }
    }

    DB::conn()->commit();
    Auth::logAudit($admin['id'], 'CREATE_LECTURER', 'users', $newId);
    jsonResponse(true, "Lecturer account created. ID: $empId", ['id' => $newId, 'employee_id' => $empId]);
}

// ── PUT: update ───────────────────────────────────────────────
if ($method === 'PUT') {
    $body = requireJson();
    $id   = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(false, 'Lecturer ID required.');
    $old = DB::run("SELECT * FROM users WHERE id=? AND role='lecturer'", [$id])->fetch();
    if (!$old) jsonResponse(false, 'Lecturer not found.', null, 404);

    $fields = []; $params = [];
    foreach (['first_name','last_name','phone','program_id','is_active'] as $f) {
        if (array_key_exists($f, $body)) { $fields[] = "$f=?"; $params[] = sanitize((string)$body[$f]); }
    }
    if (!empty($body['email'])) {
        $e = strtolower(trim($body['email']));
        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'Invalid email.');
        if (DB::run("SELECT id FROM users WHERE email=? AND id!=?", [$e, $id])->fetch())
            jsonResponse(false, 'Email already used by another account.');
        $fields[] = 'email=?'; $params[] = $e;
    }
    if (!empty($body['password'])) {
        if (!validatePassword($body['password'])) jsonResponse(false, 'Password too weak.');
        $fields[] = 'password_hash=?';
        $params[] = password_hash($body['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    }
    if ($fields) {
        $params[] = $id;
        DB::run("UPDATE users SET " . implode(',', $fields) . " WHERE id=?", $params);
    }

    if (isset($body['course_unit_ids']) && is_array($body['course_unit_ids'])) {
        $ay  = sanitize($body['academic_year'] ?? currentAcademicYear());
        $sem = in_array($body['semester'] ?? '', ['I','II']) ? $body['semester'] : 'I';
        DB::run("DELETE FROM lecturer_course_assignments WHERE lecturer_id=? AND academic_year=? AND semester=?",
                [$id, $ay, $sem]);
        foreach ($body['course_unit_ids'] as $cuId) {
            DB::run(
                "INSERT IGNORE INTO lecturer_course_assignments
                   (lecturer_id, course_unit_id, academic_year, semester, assigned_by)
                 VALUES (?,?,?,?,?)",
                [$id, (int)$cuId, $ay, $sem, $admin['id']]
            );
        }
    }
    Auth::logAudit($admin['id'], 'UPDATE_LECTURER', 'users', $id);
    jsonResponse(true, 'Lecturer updated successfully.');
}

// ── DELETE ────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(false, 'Lecturer ID required.');
    if (!DB::run("SELECT id FROM users WHERE id=? AND role='lecturer'", [$id])->fetch())
        jsonResponse(false, 'Lecturer not found.', null, 404);
    DB::run("UPDATE users SET is_active=0 WHERE id=?", [$id]);
    Auth::logAudit($admin['id'], 'DEACTIVATE_LECTURER', 'users', $id);
    jsonResponse(true, 'Lecturer account deactivated.');
}

jsonResponse(false, 'Bad request.', null, 400);

} catch (Throwable $e) {
    error_log('lecturers.php error: ' . $e->getMessage());
    if (DB::conn()->inTransaction()) DB::conn()->rollBack();
    jsonResponse(false, 'Server error: ' . $e->getMessage(), null, 500);
}
