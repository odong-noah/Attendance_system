<?php
// api/programs.php — Programs & Course Units
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
$entity  = sanitize($_GET['entity'] ?? 'programs');

try {

if ($method === 'GET') {
    if ($entity === 'courses') {
        $progId = (int)($_GET['program_id'] ?? 0);
        $where  = ['cu.is_active=1']; $params = [];
        if ($progId) { $where[] = 'cu.program_id=?'; $params[] = $progId; }
        elseif (!$isAdmin) { $where[] = 'cu.program_id=?'; $params[] = $user['program_id']; }
        $w = implode(' AND ', $where);
        $rows = DB::run(
            "SELECT cu.*, p.name AS program_name, p.code AS program_code,
                    (SELECT COUNT(*) FROM student_course_enrollments sce
                     WHERE sce.course_unit_id=cu.id) AS enrolled_count
             FROM course_units cu JOIN programs p ON cu.program_id=p.id
             WHERE $w ORDER BY cu.code", $params
        )->fetchAll();
        jsonResponse(true, '', $rows);
    }
    $rows = DB::run(
        "SELECT p.*,
                (SELECT COUNT(*) FROM course_units cu WHERE cu.program_id=p.id AND cu.is_active=1) AS course_count,
                (SELECT COUNT(*) FROM students s WHERE s.program_id=p.id AND s.is_active=1) AS student_count,
                (SELECT COUNT(*) FROM users u WHERE u.program_id=p.id AND u.role='lecturer' AND u.is_active=1) AS lecturer_count
         FROM programs p WHERE p.is_active=1 ORDER BY p.name"
    )->fetchAll();
    jsonResponse(true, '', $rows);
}

if (!$isAdmin) jsonResponse(false, 'Only the Dean can modify programs.', null, 403);

if ($method === 'POST') {
    $body = requireJson();
    if ($entity === 'courses') {
        foreach (['code','name','program_id'] as $f) if (empty($body[$f])) jsonResponse(false, "Field '$f' required.");
        DB::run(
            "INSERT INTO course_units (code, name, credit_units, program_id, semester, year_of_study, description) VALUES (?,?,?,?,?,?,?)",
            [strtoupper(sanitize($body['code'])), sanitize($body['name']), (int)($body['credit_units']??3),
             (int)$body['program_id'], in_array($body['semester'] ?? '', ['I','II'])?$body['semester']:'I',
             (int)($body['year_of_study']??1), sanitize($body['description']??'')]
        );
        Auth::logAudit($user['id'], 'CREATE_COURSE', 'course_units', (int)DB::conn()->lastInsertId());
        jsonResponse(true, 'Course unit created successfully.');
    }
    foreach (['code','name'] as $f) if (empty($body[$f])) jsonResponse(false, "Field '$f' required.");
    DB::run("INSERT INTO programs (code, name, description) VALUES (?,?,?)",
            [strtoupper(sanitize($body['code'])), sanitize($body['name']), sanitize($body['description']??'')]);
    Auth::logAudit($user['id'], 'CREATE_PROGRAM', 'programs', (int)DB::conn()->lastInsertId());
    jsonResponse(true, 'Program created successfully.');
}

if ($method === 'PUT') {
    $body = requireJson();
    $id   = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(false, 'ID required.');
    $table   = $entity === 'courses' ? 'course_units' : 'programs';
    $allowed = $entity === 'courses'
        ? ['code','name','credit_units','program_id','semester','year_of_study','description','is_active']
        : ['code','name','description','is_active'];
    $fields = []; $params = [];
    foreach ($allowed as $f) if (array_key_exists($f, $body)) { $fields[] = "$f=?"; $params[] = sanitize((string)$body[$f]); }
    if (!$fields) jsonResponse(false, 'No fields to update.');
    $params[] = $id;
    DB::run("UPDATE $table SET " . implode(',', $fields) . " WHERE id=?", $params);
    Auth::logAudit($user['id'], 'UPDATE_' . strtoupper($entity), $table, $id);
    jsonResponse(true, ucfirst($entity) . ' updated successfully.');
}

if ($method === 'DELETE') {
    $id    = (int)($_GET['id'] ?? 0);
    $table = $entity === 'courses' ? 'course_units' : 'programs';
    if (!$id) jsonResponse(false, 'ID required.');
    DB::run("UPDATE $table SET is_active=0 WHERE id=?", [$id]);
    Auth::logAudit($user['id'], 'DEACTIVATE_' . strtoupper($entity), $table, $id);
    jsonResponse(true, 'Deactivated successfully.');
}

jsonResponse(false, 'Bad request.', null, 400);

} catch (Throwable $e) {
    error_log('programs.php error: ' . $e->getMessage());
    jsonResponse(false, 'Server error: ' . $e->getMessage(), null, 500);
}
