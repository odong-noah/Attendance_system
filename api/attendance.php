<?php
// api/attendance.php — Session creation + attendance recording
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
    $action = $_GET['action'] ?? 'sessions';

    if ($action === 'sheet') {
        $sessionId = (int)($_GET['session_id'] ?? 0);
        if (!$sessionId) jsonResponse(false, 'session_id required.');

        $sql = "SELECT ls.*, cu.name AS course_name, cu.code AS course_code,
                       p.name AS program_name
                FROM lecture_sessions ls
                JOIN course_units cu ON ls.course_unit_id = cu.id
                JOIN programs p ON cu.program_id = p.id
                WHERE ls.id = ?";
        $params = [$sessionId];
        if (!$isAdmin) { $sql .= " AND ls.lecturer_id = ?"; $params[] = $user['id']; }
        $session = DB::run($sql, $params)->fetch();
        if (!$session) jsonResponse(false, 'Session not found.', null, 404);

        $students = DB::run(
            "SELECT s.id AS student_id, s.student_number,
                    s.first_name, s.last_name, s.is_flagged,
                    COALESCE(ar.status, 'not_marked') AS status,
                    COALESCE(ar.remarks, '') AS remarks,
                    ar.id AS record_id
             FROM student_course_enrollments sce
             JOIN students s ON sce.student_id = s.id
             LEFT JOIN attendance_records ar
               ON ar.student_id = s.id AND ar.session_id = ?
             WHERE sce.course_unit_id = ? AND sce.academic_year = ?
               AND sce.semester = ? AND s.is_active = 1
             ORDER BY s.last_name, s.first_name",
            [$sessionId, $session['course_unit_id'], $session['academic_year'], $session['semester']]
        )->fetchAll();

        jsonResponse(true, '', ['session' => $session, 'students' => $students]);
    }

    // List sessions
    $courseId = (int)($_GET['course_unit_id'] ?? 0);
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $limit    = (int)($_GET['limit'] ?? DEFAULT_PAGE_LIMIT);
    $offset   = ($page - 1) * $limit;

    $where = ['1=1']; $params = [];
    if (!$isAdmin) { $where[] = 'ls.lecturer_id=?'; $params[] = $user['id']; }
    if ($courseId) { $where[] = 'ls.course_unit_id=?'; $params[] = $courseId; }
    $w = implode(' AND ', $where);

    $total = (int)DB::run("SELECT COUNT(*) FROM lecture_sessions ls WHERE $w", $params)->fetchColumn();
    $rows  = DB::run(
        "SELECT ls.id, ls.session_date, ls.start_time, ls.end_time,
                ls.topic, ls.venue, ls.academic_year, ls.semester,
                cu.code AS course_code, cu.name AS course_name,
                p.name AS program_name,
                CONCAT(u.first_name,' ',u.last_name) AS lecturer_name,
                (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id=ls.id) AS marked_count,
                (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id=ls.id AND ar.status='present') AS present_count,
                (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id=ls.id AND ar.status='absent') AS absent_count
         FROM lecture_sessions ls
         JOIN course_units cu ON ls.course_unit_id=cu.id
         JOIN programs p ON cu.program_id=p.id
         LEFT JOIN users u ON ls.lecturer_id=u.id
         WHERE $w ORDER BY ls.session_date DESC, ls.start_time DESC
         LIMIT $limit OFFSET $offset",
        $params
    )->fetchAll();
    jsonResponse(true, '', ['sessions' => $rows, 'pagination' => paginate($total, $page, $limit)]);
}

// ── POST ──────────────────────────────────────────────────────
if ($method === 'POST') {
    $body   = requireJson();
    $action = $body['action'] ?? 'create_session';

    if ($action === 'create_session') {
        foreach (['course_unit_id','session_date','start_time','end_time','academic_year','semester'] as $f)
            if (empty($body[$f])) jsonResponse(false, "Field '$f' required.");

        $cuId = (int)$body['course_unit_id'];
        if (!$isAdmin) {
            $ok = DB::run(
                "SELECT id FROM lecturer_course_assignments
                 WHERE lecturer_id=? AND course_unit_id=?",
                [$user['id'], $cuId]
            )->fetch();
            if (!$ok) jsonResponse(false, 'You are not assigned to this course unit.', null, 403);
        }

        DB::run(
            "INSERT INTO lecture_sessions
               (course_unit_id, lecturer_id, session_date, start_time, end_time,
                topic, venue, academic_year, semester, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?)",
            [
                $cuId, $user['id'],
                sanitize($body['session_date']), sanitize($body['start_time']),
                sanitize($body['end_time']),     sanitize($body['topic']    ?? ''),
                sanitize($body['venue']    ?? ''), sanitize($body['academic_year']),
                sanitize($body['semester']),      sanitize($body['notes']   ?? '')
            ]
        );
        $sessionId = (int)DB::conn()->lastInsertId();
        Auth::logAudit($user['id'], 'CREATE_SESSION', 'lecture_sessions', $sessionId);
        jsonResponse(true, 'Session created successfully.', ['session_id' => $sessionId]);
    }

    if ($action === 'mark_attendance') {
        $sessionId = (int)($body['session_id'] ?? 0);
        $records   = $body['records'] ?? [];
        if (!$sessionId || !is_array($records) || empty($records))
            jsonResponse(false, 'session_id and records are required.');

        $sql = "SELECT id FROM lecture_sessions WHERE id=?";
        $p   = [$sessionId];
        if (!$isAdmin) { $sql .= " AND lecturer_id=?"; $p[] = $user['id']; }
        if (!DB::run($sql, $p)->fetch()) jsonResponse(false, 'Session not found.', null, 404);

        $valid = ['present','absent','late','excused'];
        DB::conn()->beginTransaction();
        foreach ($records as $rec) {
            $stuId  = (int)($rec['student_id'] ?? 0);
            $status = in_array($rec['status'] ?? '', $valid) ? $rec['status'] : 'absent';
            if (!$stuId) continue;
            DB::run(
                "INSERT INTO attendance_records (session_id, student_id, status, remarks, marked_by)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE status=VALUES(status), remarks=VALUES(remarks), marked_by=VALUES(marked_by)",
                [$sessionId, $stuId, $status, sanitize($rec['remarks'] ?? ''), $user['id']]
            );
        }
        DB::conn()->commit();
        Auth::logAudit($user['id'], 'MARK_ATTENDANCE', 'lecture_sessions', $sessionId);
        jsonResponse(true, 'Attendance saved successfully.');
    }

    jsonResponse(false, 'Unknown action.', null, 400);
}

jsonResponse(false, 'Bad request.', null, 400);

} catch (Throwable $e) {
    error_log('attendance.php error: ' . $e->getMessage());
    if (DB::conn()->inTransaction()) DB::conn()->rollBack();
    jsonResponse(false, 'Server error: ' . $e->getMessage(), null, 500);
}
