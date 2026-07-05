<?php
// api/search.php — Global search
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');
Auth::startSession();
Auth::requireLecturer();

$q    = sanitize(trim($_GET['q'] ?? ''));
$type = sanitize($_GET['type'] ?? 'all');
$user = Auth::user();
$isAdmin = Auth::isAdmin();

if (strlen($q) < 2) jsonResponse(true, '', ['students'=>[],'sessions'=>[],'courses'=>[]]);

try {
    $results = ['students'=>[],'sessions'=>[],'courses'=>[]];
    $s = "%$q%";

    if (in_array($type, ['all','students'])) {
        $pf = $isAdmin ? '' : 'AND s.program_id=' . (int)$user['program_id'];
        $results['students'] = DB::run(
            "SELECT s.id, s.student_number, CONCAT(s.first_name,' ',s.last_name) AS name,
                    s.email, s.is_flagged, p.name AS program, p.code AS program_code, s.year_of_study
             FROM students s JOIN programs p ON s.program_id=p.id
             WHERE s.is_active=1 $pf
               AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_number LIKE ? OR s.email LIKE ?)
             LIMIT 15",
            [$s,$s,$s,$s]
        )->fetchAll();
    }

    if (in_array($type, ['all','sessions'])) {
        $lf = $isAdmin ? '' : 'AND ls.lecturer_id=' . (int)$user['id'];
        $results['sessions'] = DB::run(
            "SELECT ls.id, ls.session_date, ls.topic, ls.venue, cu.code, cu.name AS course_name
             FROM lecture_sessions ls JOIN course_units cu ON ls.course_unit_id=cu.id
             WHERE 1=1 $lf AND (ls.topic LIKE ? OR cu.name LIKE ? OR cu.code LIKE ?)
             ORDER BY ls.session_date DESC LIMIT 10",
            [$s,$s,$s]
        )->fetchAll();
    }

    if (in_array($type, ['all','courses'])) {
        $pf = $isAdmin ? '' : 'AND cu.program_id=' . (int)$user['program_id'];
        $results['courses'] = DB::run(
            "SELECT cu.id, cu.code, cu.name, p.name AS program_name
             FROM course_units cu JOIN programs p ON cu.program_id=p.id
             WHERE cu.is_active=1 $pf AND (cu.name LIKE ? OR cu.code LIKE ?)
             LIMIT 10",
            [$s,$s]
        )->fetchAll();
    }

    jsonResponse(true, '', $results);
} catch (Throwable $e) {
    jsonResponse(false, 'Search error: ' . $e->getMessage(), null, 500);
}
