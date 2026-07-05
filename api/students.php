<?php
// api/students.php — Student CRUD
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');
Auth::startSession();
Auth::requireLecturer();

$method  = $_SERVER['REQUEST_METHOD'];
$user    = Auth::user();
$isAdmin = Auth::isAdmin();

try {

// ── GET ───────────────────────────────────────────────────────
if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id) {
        $sql = "SELECT s.*, p.name AS program_name, p.code AS program_code,
                       CONCAT(u.first_name,' ',u.last_name) AS added_by
                FROM students s
                JOIN programs p ON s.program_id = p.id
                LEFT JOIN users u ON s.created_by = u.id
                WHERE s.id = ?";
        $params = [$id];
        if (!$isAdmin) { $sql .= " AND s.program_id = ?"; $params[] = $user['program_id']; }
        $student = DB::run($sql, $params)->fetch();
        if (!$student) jsonResponse(false, 'Student not found.', null, 404);

        $attendance = DB::run(
            "SELECT cu.code, cu.name AS course_name,
                    COUNT(ar.id) AS total_sessions,
                    SUM(ar.status='present') AS present,
                    SUM(ar.status='absent')  AS absent,
                    SUM(ar.status='late')    AS late,
                    SUM(ar.status='excused') AS excused,
                    ROUND(SUM(ar.status IN ('present','late'))/NULLIF(COUNT(ar.id),0)*100,2) AS pct
             FROM attendance_records ar
             JOIN lecture_sessions ls ON ar.session_id = ls.id
             JOIN course_units cu     ON ls.course_unit_id = cu.id
             WHERE ar.student_id = ?
             GROUP BY cu.id, cu.code, cu.name",
            [$id]
        )->fetchAll();
        $student['attendance_by_course'] = $attendance;
        jsonResponse(true, '', $student);
    }

    $search  = sanitize($_GET['search']  ?? '');
    $progId  = (int)($_GET['program_id'] ?? 0);
    $flagged = isset($_GET['flagged']) && $_GET['flagged'] !== '' ? (int)$_GET['flagged'] : null;
    $page    = max(1, (int)($_GET['page']  ?? 1));
    $limit   = min((int)($_GET['limit']   ?? DEFAULT_PAGE_LIMIT), 100);
    $offset  = ($page - 1) * $limit;

    $where = ['s.is_active = 1']; $params = [];
    if (!$isAdmin) {
        $where[] = 's.program_id = ?'; $params[] = $user['program_id'];
    } elseif ($progId) {
        $where[] = 's.program_id = ?'; $params[] = $progId;
    }
    if ($search) {
        $where[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_number LIKE ? OR s.email LIKE ?)";
        $s = "%{$search}%"; array_push($params, $s, $s, $s, $s);
    }
    if ($flagged !== null) { $where[] = 's.is_flagged = ?'; $params[] = $flagged; }

    $w   = implode(' AND ', $where);
    $total = (int)DB::run("SELECT COUNT(*) FROM students s WHERE $w", $params)->fetchColumn();
    $rows  = DB::run(
        "SELECT s.id, s.student_number, s.first_name, s.last_name,
                s.email, s.phone, s.gender, s.year_of_study, s.semester,
                s.academic_year, s.is_active, s.is_flagged, s.flag_reason, s.created_at,
                p.name AS program_name, p.code AS program_code
         FROM students s JOIN programs p ON s.program_id = p.id
         WHERE $w ORDER BY s.last_name, s.first_name LIMIT $limit OFFSET $offset",
        $params
    )->fetchAll();

    jsonResponse(true, '', ['students' => $rows, 'pagination' => paginate($total, $page, $limit)]);
}

// ── POST: create ──────────────────────────────────────────────
if ($method === 'POST') {
    $body = requireJson();
    $required = ['first_name','last_name','program_id','year_of_study','academic_year'];
    foreach ($required as $f) if (empty($body[$f])) jsonResponse(false, "Field '$f' is required.");

    $progId = (int)$body['program_id'];
    if (!$isAdmin && $progId !== (int)$user['program_id'])
        jsonResponse(false, 'You cannot add students to another program.', null, 403);

    // Auto-generate student number
    $yr   = substr(sanitize($body['academic_year']), 0, 4);
    $prog = DB::run("SELECT code FROM programs WHERE id=?", [$progId])->fetchColumn() ?: 'ST';
    $seq  = (int)DB::run("SELECT COUNT(*)+1 FROM students WHERE program_id=? AND academic_year=?",
                         [$progId, sanitize($body['academic_year'])])->fetchColumn();
    $stuNo = strtoupper($prog) . $yr . str_pad($seq, 4, '0', STR_PAD_LEFT);

    if (!empty($body['student_number'])) {
        $stuNo = sanitize($body['student_number']);
        if (DB::run("SELECT id FROM students WHERE student_number=?", [$stuNo])->fetch())
            jsonResponse(false, 'Student number already exists.');
    }

    $email = !empty($body['email']) ? strtolower(trim($body['email'])) : null;
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL))
        jsonResponse(false, 'Invalid email address.');

    DB::run(
        "INSERT INTO students
           (student_number, first_name, last_name, email, phone, gender,
            program_id, year_of_study, semester, academic_year, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)",
        [
            $stuNo,
            sanitize($body['first_name']), sanitize($body['last_name']),
            $email, sanitize($body['phone'] ?? ''),
            in_array($body['gender'] ?? '', ['Male','Female','Other']) ? $body['gender'] : null,
            $progId, (int)$body['year_of_study'],
            in_array($body['semester'] ?? '', ['I','II']) ? $body['semester'] : 'I',
            sanitize($body['academic_year']), $user['id']
        ]
    );
    $newId = (int)DB::conn()->lastInsertId();

    // Enroll in selected course units
    if (!empty($body['course_unit_ids']) && is_array($body['course_unit_ids'])) {
        foreach ($body['course_unit_ids'] as $cuId) {
            DB::run(
                "INSERT IGNORE INTO student_course_enrollments
                   (student_id, course_unit_id, academic_year, semester, enrolled_by)
                 VALUES (?,?,?,?,?)",
                [$newId, (int)$cuId, sanitize($body['academic_year']),
                 in_array($body['semester'] ?? '', ['I','II']) ? $body['semester'] : 'I',
                 $user['id']]
            );
        }
    }

    Auth::logAudit($user['id'], 'CREATE_STUDENT', 'students', $newId);
    jsonResponse(true, "Student {$stuNo} added successfully.", ['id' => $newId, 'student_number' => $stuNo]);
}

// ── PUT: update ───────────────────────────────────────────────
if ($method === 'PUT') {
    $body = requireJson();
    $id   = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(false, 'Student ID required.');

    $sql = "SELECT * FROM students WHERE id=?" . (!$isAdmin ? " AND program_id=?" : "");
    $old = DB::run($sql, $isAdmin ? [$id] : [$id, $user['program_id']])->fetch();
    if (!$old) jsonResponse(false, 'Student not found.', null, 404);

    $allowed = ['first_name','last_name','phone','gender','year_of_study','semester','is_active'];
    $fields = []; $params = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $body)) { $fields[] = "$f=?"; $params[] = sanitize((string)$body[$f]); }
    }
    if (array_key_exists('email', $body)) {
        $e = strtolower(trim($body['email']));
        if ($e && !filter_var($e, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'Invalid email.');
        $fields[] = 'email=?'; $params[] = $e ?: null;
    }
    if ($fields) {
        $params[] = $id;
        DB::run("UPDATE students SET " . implode(',', $fields) . " WHERE id=?", $params);
    }
    Auth::logAudit($user['id'], 'UPDATE_STUDENT', 'students', $id);
    jsonResponse(true, 'Student record updated successfully.');
}

// ── DELETE: soft-delete ───────────────────────────────────────
if ($method === 'DELETE') {
    $id  = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(false, 'Student ID required.');
    $sql = "SELECT id FROM students WHERE id=?" . (!$isAdmin ? " AND program_id=?" : "");
    $st  = DB::run($sql, $isAdmin ? [$id] : [$id, $user['program_id']])->fetch();
    if (!$st) jsonResponse(false, 'Student not found.', null, 404);
    DB::run("UPDATE students SET is_active=0 WHERE id=?", [$id]);
    Auth::logAudit($user['id'], 'DELETE_STUDENT', 'students', $id);
    jsonResponse(true, 'Student removed successfully.');
}

jsonResponse(false, 'Bad request.', null, 400);

} catch (Throwable $e) {
    error_log('students.php error: ' . $e->getMessage());
    jsonResponse(false, 'Server error: ' . $e->getMessage(), null, 500);
}
