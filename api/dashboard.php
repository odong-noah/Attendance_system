<?php
// api/dashboard.php — Dashboard statistics for both admin and lecturer
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');
Auth::startSession();
Auth::requireLecturer();

$user    = Auth::user();
$isAdmin = Auth::isAdmin();
$ay      = currentAcademicYear();

try {
    if ($isAdmin) {
        // ── Dean dashboard ──────────────────────────────────────────────

        $stats = DB::run(
            "SELECT
               (SELECT COUNT(*) FROM programs WHERE is_active=1)                       AS total_programs,
               (SELECT COUNT(*) FROM users WHERE role='lecturer' AND is_active=1)      AS total_lecturers,
               (SELECT COUNT(*) FROM students WHERE is_active=1)                       AS total_students,
               (SELECT COUNT(*) FROM students WHERE is_flagged=1)                      AS flagged_students,
               (SELECT COUNT(*) FROM lecture_sessions)                                 AS total_sessions,
               (SELECT ROUND(SUM(status IN ('present','late'))/NULLIF(COUNT(*),0)*100,1)
                FROM attendance_records)                                               AS overall_attendance_pct"
        )->fetch();

        // Use subqueries per program to completely avoid row multiplication
        $programStats = DB::run(
            "SELECT
               p.name AS program,
               p.code,
               (SELECT COUNT(DISTINCT s.id)
                FROM students s
                WHERE s.program_id = p.id AND s.is_active = 1)                        AS students,
               (SELECT COUNT(DISTINCT ls.id)
                FROM lecture_sessions ls
                JOIN course_units cu ON ls.course_unit_id = cu.id
                WHERE cu.program_id = p.id)                                            AS sessions,
               (SELECT COUNT(*)
                FROM attendance_records ar
                JOIN lecture_sessions ls ON ar.session_id = ls.id
                JOIN course_units cu    ON ls.course_unit_id = cu.id
                WHERE cu.program_id = p.id AND ar.status = 'present')                 AS present,
               (SELECT COUNT(*)
                FROM attendance_records ar
                JOIN lecture_sessions ls ON ar.session_id = ls.id
                JOIN course_units cu    ON ls.course_unit_id = cu.id
                WHERE cu.program_id = p.id AND ar.status = 'absent')                  AS absent
             FROM programs p
             WHERE p.is_active = 1
             ORDER BY p.name"
        )->fetchAll();

        // Trend — last 30 days; fall back to last 10 session dates if empty
        $trend = DB::run(
            "SELECT DATE(ls.session_date) AS day,
                    SUM(ar.status = 'present') AS present,
                    SUM(ar.status = 'absent')  AS absent
             FROM lecture_sessions ls
             LEFT JOIN attendance_records ar ON ar.session_id = ls.id
             WHERE ls.session_date >= CURDATE() - INTERVAL 30 DAY
             GROUP BY DATE(ls.session_date)
             ORDER BY day ASC"
        )->fetchAll();

        if (empty($trend)) {
            $trend = array_reverse(DB::run(
                "SELECT DATE(ls.session_date) AS day,
                        SUM(ar.status = 'present') AS present,
                        SUM(ar.status = 'absent')  AS absent
                 FROM lecture_sessions ls
                 LEFT JOIN attendance_records ar ON ar.session_id = ls.id
                 GROUP BY DATE(ls.session_date)
                 ORDER BY day DESC LIMIT 10"
            )->fetchAll());
        }

        $flagged = DB::run(
            "SELECT s.student_number,
                    CONCAT(s.first_name,' ',s.last_name) AS name,
                    p.name AS program,
                    s.flag_reason,
                    (SELECT COUNT(*)
                     FROM attendance_records ar2
                     JOIN lecture_sessions ls2 ON ar2.session_id = ls2.id
                     WHERE ar2.student_id = s.id AND ar2.status = 'absent') AS absences
             FROM students s
             JOIN programs p ON s.program_id = p.id
             WHERE s.is_flagged = 1
             ORDER BY absences DESC
             LIMIT 10"
        )->fetchAll();

        $recent = DB::run(
            "SELECT al.action, al.created_at,
                    COALESCE(CONCAT(u.first_name,' ',u.last_name), 'System') AS performed_by,
                    al.entity_type, al.entity_id
             FROM audit_logs al
             LEFT JOIN users u ON al.user_id = u.id
             ORDER BY al.created_at DESC
             LIMIT 10"
        )->fetchAll();

        jsonResponse(true, '', [
            'stats'         => $stats,
            'program_stats' => $programStats,
            'daily_trend'   => $trend,
            'flagged'       => $flagged,
            'recent'        => $recent,
        ]);

    } else {
        // ── Lecturer dashboard ──────────────────────────────────────────

        $lid = $user['id'];
        $pid = $user['program_id'];

        $stats = DB::run(
            "SELECT
               (SELECT COUNT(*)
                FROM lecturer_course_assignments
                WHERE lecturer_id = ?)                                                 AS my_courses,
               (SELECT COUNT(*)
                FROM students
                WHERE program_id = ? AND is_active = 1)                               AS program_students,
               (SELECT COUNT(*)
                FROM lecture_sessions
                WHERE lecturer_id = ?)                                                 AS my_sessions,
               (SELECT COUNT(*)
                FROM students
                WHERE program_id = ? AND is_flagged = 1)                              AS flagged_in_program,
               (SELECT ROUND(
                  SUM(ar.status IN ('present','late')) / NULLIF(COUNT(ar.id),0)*100, 1)
                FROM attendance_records ar
                JOIN lecture_sessions ls ON ar.session_id = ls.id
                WHERE ls.lecturer_id = ?)                                             AS my_attendance_pct",
            [$lid, $pid, $lid, $pid, $lid]
        )->fetch();

        // ── Per-course attendance using subqueries — NO JOIN multiplication ──
        // First get the list of assigned course units
        $assignedCourses = DB::run(
            "SELECT cu.id, cu.code, cu.name, lca.academic_year, lca.semester
             FROM lecturer_course_assignments lca
             JOIN course_units cu ON lca.course_unit_id = cu.id
             WHERE lca.lecturer_id = ?
             ORDER BY cu.code",
            [$lid]
        )->fetchAll();

        $courses = [];
        foreach ($assignedCourses as $cu) {
            $cuId = (int)$cu['id'];

            // Each count is an independent subquery — zero cross-multiplication
            $counts = DB::run(
                "SELECT
                   (SELECT COUNT(DISTINCT id)
                    FROM lecture_sessions
                    WHERE course_unit_id = ? AND lecturer_id = ?)              AS sessions,
                   (SELECT COUNT(DISTINCT student_id)
                    FROM student_course_enrollments
                    WHERE course_unit_id = ? AND academic_year = ?)           AS enrolled_students,
                   (SELECT COUNT(*)
                    FROM attendance_records ar
                    JOIN lecture_sessions ls ON ar.session_id = ls.id
                    WHERE ls.course_unit_id = ? AND ls.lecturer_id = ?
                      AND ar.status = 'present')                               AS present,
                   (SELECT COUNT(*)
                    FROM attendance_records ar
                    JOIN lecture_sessions ls ON ar.session_id = ls.id
                    WHERE ls.course_unit_id = ? AND ls.lecturer_id = ?
                      AND ar.status = 'absent')                                AS absent",
                [
                    $cuId, $lid,
                    $cuId, $cu['academic_year'],
                    $cuId, $lid,
                    $cuId, $lid,
                ]
            )->fetch();

            $totalAtt = (int)$counts['present'] + (int)$counts['absent'];
            $pct      = $totalAtt > 0
                ? round((int)$counts['present'] / $totalAtt * 100, 1)
                : 0;

            $courses[] = [
                'id'                => $cuId,
                'code'              => $cu['code'],
                'name'              => $cu['name'],
                'academic_year'     => $cu['academic_year'],
                'semester'          => $cu['semester'],
                'sessions'          => (int)$counts['sessions'],
                'enrolled_students' => (int)$counts['enrolled_students'],
                'present'           => (int)$counts['present'],
                'absent'            => (int)$counts['absent'],
                'pct'               => $pct,
            ];
        }

        // Trend — last 30 days; fall back to all-time if empty
        $trend = DB::run(
            "SELECT DATE(ls.session_date) AS day,
                    SUM(ar.status = 'present') AS present,
                    SUM(ar.status = 'absent')  AS absent
             FROM lecture_sessions ls
             LEFT JOIN attendance_records ar ON ar.session_id = ls.id
             WHERE ls.lecturer_id = ?
               AND ls.session_date >= CURDATE() - INTERVAL 30 DAY
             GROUP BY DATE(ls.session_date)
             ORDER BY day ASC",
            [$lid]
        )->fetchAll();

        if (empty($trend)) {
            $trend = array_reverse(DB::run(
                "SELECT DATE(ls.session_date) AS day,
                        SUM(ar.status = 'present') AS present,
                        SUM(ar.status = 'absent')  AS absent
                 FROM lecture_sessions ls
                 LEFT JOIN attendance_records ar ON ar.session_id = ls.id
                 WHERE ls.lecturer_id = ?
                 GROUP BY DATE(ls.session_date)
                 ORDER BY day DESC LIMIT 10",
                [$lid]
            )->fetchAll());
        }

        $flagged = DB::run(
            "SELECT s.student_number,
                    CONCAT(s.first_name,' ',s.last_name) AS name,
                    s.flag_reason,
                    (SELECT COUNT(*)
                     FROM attendance_records ar2
                     JOIN lecture_sessions ls2 ON ar2.session_id = ls2.id
                     WHERE ar2.student_id = s.id AND ar2.status = 'absent') AS absences
             FROM students s
             WHERE s.program_id = ? AND s.is_flagged = 1
             ORDER BY absences DESC
             LIMIT 10",
            [$pid]
        )->fetchAll();

        $recentSessions = DB::run(
            "SELECT ls.id, ls.session_date, ls.topic,
                    cu.code, cu.name AS course_name,
                    (SELECT COUNT(*)
                     FROM attendance_records ar
                     WHERE ar.session_id = ls.id AND ar.status = 'present') AS present_count,
                    (SELECT COUNT(*)
                     FROM attendance_records ar
                     WHERE ar.session_id = ls.id)                           AS total_marked
             FROM lecture_sessions ls
             JOIN course_units cu ON ls.course_unit_id = cu.id
             WHERE ls.lecturer_id = ?
             ORDER BY ls.session_date DESC, ls.start_time DESC
             LIMIT 5",
            [$lid]
        )->fetchAll();

        jsonResponse(true, '', [
            'stats'           => $stats,
            'courses'         => $courses,
            'daily_trend'     => $trend,
            'flagged'         => $flagged,
            'recent_sessions' => $recentSessions,
        ]);
    }

} catch (Throwable $e) {
    error_log('Dashboard error: ' . $e->getMessage());
    jsonResponse(false, 'Dashboard error: ' . $e->getMessage(), null, 500);
}
