<?php
// api/reports.php — Attendance reports with printable HTML output
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::startSession();
Auth::requireLecturer();

$format = sanitize($_GET['format'] ?? 'html');
$type   = sanitize($_GET['type']   ?? 'summary');
$user   = Auth::user();
$isAdmin = Auth::isAdmin();

try {
    $reportData = [];
    $title      = 'Attendance Report';
    $subtitle   = 'Generated: ' . date('D, d M Y H:i');

    switch ($type) {

        case 'summary':
            $title    = 'Faculty Attendance Summary';
            $subtitle .= ' | Academic Year: ' . sanitize($_GET['academic_year'] ?? 'All');
            $rows = DB::run(
                "SELECT cu.code AS course_code, cu.name AS course_name,
                        p.name AS program_name,
                        CONCAT(u.first_name,' ',u.last_name) AS lecturer,
                        COUNT(DISTINCT ls.id) AS sessions,
                        COUNT(DISTINCT sce.student_id) AS enrolled,
                        COALESCE(SUM(ar.status='present'),0) AS present,
                        COALESCE(SUM(ar.status='absent'),0)  AS absent,
                        COALESCE(SUM(ar.status='late'),0)    AS late,
                        ROUND(COALESCE(SUM(ar.status IN ('present','late')),0)
                          / NULLIF(COUNT(ar.id),0)*100,1) AS pct
                 FROM course_units cu
                 JOIN programs p ON cu.program_id = p.id
                 LEFT JOIN lecture_sessions ls ON ls.course_unit_id = cu.id
                 LEFT JOIN users u ON ls.lecturer_id = u.id
                 LEFT JOIN attendance_records ar ON ar.session_id = ls.id
                 LEFT JOIN student_course_enrollments sce ON sce.course_unit_id = cu.id
                 WHERE cu.is_active = 1
                 GROUP BY cu.id, cu.code, cu.name, p.name, lecturer
                 ORDER BY p.name, cu.code"
            )->fetchAll();
            $columns = ['Course Code','Course Name','Program','Lecturer','Sessions','Enrolled','Present','Absent','Late','Attendance %'];
            $keys    = ['course_code','course_name','program_name','lecturer','sessions','enrolled','present','absent','late','pct'];
            $reportData = $rows;
            break;

        case 'flagged':
            $title    = 'Flagged Students Report';
            $progId   = (int)($_GET['program_id'] ?? 0);
            $where    = ['s.is_flagged = 1'];
            $params   = [];
            if (!$isAdmin) { $where[] = 's.program_id = ?'; $params[] = $user['program_id']; }
            elseif ($progId) { $where[] = 's.program_id = ?'; $params[] = $progId; }
            $w = implode(' AND ', $where);
            $rows = DB::run(
                "SELECT s.student_number, CONCAT(s.first_name,' ',s.last_name) AS student_name,
                        p.name AS program, s.year_of_study AS year, s.semester,
                        s.flag_reason,
                        (SELECT COUNT(*) FROM attendance_records ar2
                         JOIN lecture_sessions ls2 ON ar2.session_id=ls2.id
                         WHERE ar2.student_id=s.id AND ar2.status='absent') AS total_absences
                 FROM students s JOIN programs p ON s.program_id=p.id
                 WHERE $w ORDER BY total_absences DESC", $params
            )->fetchAll();
            $columns = ['Student No.','Student Name','Program','Year','Semester','Flag Reason','Absences'];
            $keys    = ['student_number','student_name','program','year','semester','flag_reason','total_absences'];
            $reportData = $rows;
            break;

        case 'course':
            $cuId = (int)($_GET['course_unit_id'] ?? 0);
            if (!$cuId) { header('Content-Type: application/json'); jsonResponse(false, 'course_unit_id required.'); }
            $course = DB::run("SELECT * FROM course_units WHERE id=?", [$cuId])->fetch();
            if (!$course) { header('Content-Type: application/json'); jsonResponse(false, 'Course not found.', null, 404); }
            $title    = "Course Report: {$course['code']} — {$course['name']}";
            $rows = DB::run(
                "SELECT s.student_number, CONCAT(s.first_name,' ',s.last_name) AS student_name,
                        COUNT(ar.id) AS sessions,
                        COALESCE(SUM(ar.status='present'),0) AS present,
                        COALESCE(SUM(ar.status='absent'),0)  AS absent,
                        COALESCE(SUM(ar.status='late'),0)    AS late,
                        COALESCE(SUM(ar.status='excused'),0) AS excused,
                        ROUND(COALESCE(SUM(ar.status IN ('present','late')),0)
                          /NULLIF(COUNT(ar.id),0)*100,1) AS pct,
                        IF(s.is_flagged=1,'YES','') AS flagged
                 FROM attendance_records ar
                 JOIN students s ON ar.student_id = s.id
                 JOIN lecture_sessions ls ON ar.session_id = ls.id
                 WHERE ls.course_unit_id = ?
                 GROUP BY s.id, s.student_number, student_name
                 ORDER BY pct ASC", [$cuId]
            )->fetchAll();
            $columns = ['Student No.','Student Name','Sessions','Present','Absent','Late','Excused','Attendance %','Flagged'];
            $keys    = ['student_number','student_name','sessions','present','absent','late','excused','pct','flagged'];
            $reportData = $rows;
            break;

        case 'student':
            $stuId = (int)($_GET['student_id'] ?? 0);
            if (!$stuId) { header('Content-Type: application/json'); jsonResponse(false, 'student_id required.'); }
            $student = DB::run(
                "SELECT s.*, p.name AS program_name FROM students s JOIN programs p ON s.program_id=p.id WHERE s.id=?",
                [$stuId]
            )->fetch();
            if (!$student) { header('Content-Type: application/json'); jsonResponse(false, 'Student not found.', null, 404); }
            $title = "Student Report: {$student['first_name']} {$student['last_name']} ({$student['student_number']})";
            $rows = DB::run(
                "SELECT cu.code AS course_code, cu.name AS course_name,
                        ls.session_date, ls.start_time, ls.topic,
                        ar.status, COALESCE(ar.remarks,'') AS remarks
                 FROM attendance_records ar
                 JOIN lecture_sessions ls ON ar.session_id = ls.id
                 JOIN course_units cu ON ls.course_unit_id = cu.id
                 WHERE ar.student_id = ?
                 ORDER BY cu.code, ls.session_date DESC", [$stuId]
            )->fetchAll();
            $columns = ['Course Code','Course Name','Date','Time','Topic','Status','Remarks'];
            $keys    = ['course_code','course_name','session_date','start_time','topic','status','remarks'];
            $reportData = ['student' => $student, 'records' => $rows];
            break;

        default:
            header('Content-Type: application/json');
            jsonResponse(false, 'Unknown report type.');
    }

    // ── JSON output ───────────────────────────────────────────
    if ($format === 'json') {
        header('Content-Type: application/json');
        jsonResponse(true, '', ['title' => $title, 'data' => $reportData]);
    }

    // ── HTML/PDF print output ─────────────────────────────────
    Auth::logAudit($user['id'], 'GENERATE_REPORT', 'reports', null, [], ['type' => $type]);

    $tableRows = ($type === 'student') ? ($reportData['records'] ?? []) : $reportData;
    $studentInfo = ($type === 'student') ? ($reportData['student'] ?? null) : null;

    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?></title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 13px; color: #0f172a; background: #fff; padding: 20px; }
  .report-header { text-align:center; border-bottom: 3px solid #2563eb; padding-bottom: 16px; margin-bottom: 20px; }
  .report-header .institution { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 6px; }
  .report-header h1 { font-size: 20px; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
  .report-header .subtitle { font-size: 11px; color: #64748b; }
  .report-meta { display:flex; gap: 24px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; flex-wrap: wrap; }
  .report-meta .meta-item { font-size: 11px; }
  .report-meta .meta-item strong { display:block; color: #64748b; text-transform: uppercase; font-size: 10px; letter-spacing: 0.08em; }
  .report-meta .meta-item span { font-weight: 700; color: #0f172a; }
  .student-info { background: #eff6ff; border: 1px solid #bfdbfe; border-radius:6px; padding: 12px 16px; margin-bottom: 16px; display:grid; grid-template-columns: repeat(3,1fr); gap:8px; }
  .student-info .si-item strong { font-size:10px; color:#3b82f6; text-transform:uppercase; display:block; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }
  thead tr { background: #2563eb; color: #fff; }
  thead th { padding: 9px 10px; text-align: left; font-size: 11px; font-weight: 700; white-space: nowrap; }
  tbody tr:nth-child(even) { background: #f8fafc; }
  tbody tr:hover { background: #eff6ff; }
  tbody td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
  tbody tr.flagged-row td { background: #fff5f5 !important; }
  tbody tr.flagged-row td:first-child { border-left: 3px solid #dc2626; }
  .badge { display:inline-block; padding: 2px 8px; border-radius: 999px; font-size: 10px; font-weight: 700; }
  .badge-present { background:#dcfce7; color:#15803d; }
  .badge-absent  { background:#fee2e2; color:#dc2626; }
  .badge-late    { background:#fef3c7; color:#b45309; }
  .badge-excused { background:#e0f2fe; color:#0369a1; }
  .badge-flagged { background:#fde8e8; color:#b91c1c; }
  .pct-high   { color:#16a34a; font-weight:700; }
  .pct-medium { color:#d97706; font-weight:700; }
  .pct-low    { color:#dc2626; font-weight:700; }
  .no-data { text-align:center; padding: 40px; color: #64748b; font-style: italic; }
  .print-btn { position:fixed; top:16px; right:16px; background:#2563eb; color:#fff; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; box-shadow: 0 2px 8px rgba(37,99,235,.4); z-index:100; }
  .print-btn:hover { background:#1d4ed8; }
  .footer { margin-top: 30px; padding-top: 12px; border-top: 1px solid #e2e8f0; display:flex; justify-content:space-between; font-size: 10px; color: #94a3b8; }
  @media print {
    .print-btn { display:none; }
    body { padding: 10px; font-size: 11px; }
    thead tr { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    @page { margin: 1.5cm; }
  }
</style>
</head>
<body>

<button class="print-btn" onclick="window.print()">🖨 Print / Save as PDF</button>

<div class="report-header">
  <div class="institution">IT Department — Faculty of Computing &amp; Technology</div>
  <h1><?= htmlspecialchars($title) ?></h1>
  <div class="subtitle"><?= htmlspecialchars($subtitle) ?></div>
</div>

<div class="report-meta">
  <div class="meta-item"><strong>Report Type</strong><span><?= htmlspecialchars(strtoupper($type)) ?></span></div>
  <div class="meta-item"><strong>Generated By</strong><span><?= htmlspecialchars($user['full_name']) ?></span></div>
  <div class="meta-item"><strong>Date &amp; Time</strong><span><?= date('d M Y H:i:s') ?></span></div>
  <div class="meta-item"><strong>Total Records</strong><span><?= count($tableRows) ?></span></div>
</div>

<?php if ($studentInfo): ?>
<div class="student-info">
  <div class="si-item"><strong>Student Number</strong><?= htmlspecialchars($studentInfo['student_number']) ?></div>
  <div class="si-item"><strong>Full Name</strong><?= htmlspecialchars($studentInfo['first_name'].' '.$studentInfo['last_name']) ?></div>
  <div class="si-item"><strong>Program</strong><?= htmlspecialchars($studentInfo['program_name']) ?></div>
  <div class="si-item"><strong>Year of Study</strong>Year <?= htmlspecialchars($studentInfo['year_of_study']) ?></div>
  <div class="si-item"><strong>Semester</strong><?= htmlspecialchars($studentInfo['semester']) ?></div>
  <div class="si-item"><strong>Status</strong><?= $studentInfo['is_flagged'] ? '<span class="badge badge-flagged">⚑ FLAGGED</span>' : '<span class="badge badge-present">Active</span>' ?></div>
</div>
<?php endif; ?>

<?php if (empty($tableRows)): ?>
  <div class="no-data">No records found for this report.</div>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>#</th>
      <?php foreach ($columns as $col): ?>
        <th><?= htmlspecialchars($col) ?></th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($tableRows as $i => $row): ?>
      <?php
        $isFlagged = isset($row['flagged']) && $row['flagged'] === 'YES';
        $pct = isset($row['pct']) ? (float)$row['pct'] : null;
      ?>
      <tr class="<?= $isFlagged ? 'flagged-row' : '' ?>">
        <td><?= $i + 1 ?></td>
        <?php foreach ($keys as $key): ?>
          <td>
            <?php
              $val = $row[$key] ?? '';
              if ($key === 'status') {
                  echo '<span class="badge badge-' . htmlspecialchars($val) . '">' . strtoupper(htmlspecialchars($val)) . '</span>';
              } elseif ($key === 'pct') {
                  $cls = $val >= 75 ? 'pct-high' : ($val >= 50 ? 'pct-medium' : 'pct-low');
                  echo '<span class="' . $cls . '">' . htmlspecialchars($val) . '%</span>';
              } elseif ($key === 'flagged' && $val === 'YES') {
                  echo '<span class="badge badge-flagged">⚑ FLAGGED</span>';
              } elseif ($key === 'flag_reason' && $val) {
                  echo '<span style="font-size:10px;color:#64748b">' . htmlspecialchars($val) . '</span>';
              } else {
                  echo htmlspecialchars((string)$val);
              }
            ?>
          </td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php if ($type === 'course' && !empty($tableRows)): ?>
  <?php
    $totPresent = array_sum(array_column($tableRows, 'present'));
    $totAbsent  = array_sum(array_column($tableRows, 'absent'));
    $totLate    = array_sum(array_column($tableRows, 'late'));
    $totAll     = $totPresent + $totAbsent + $totLate;
    $avgPct     = $totAll > 0 ? round(($totPresent + $totLate) / $totAll * 100, 1) : 0;
  ?>
  <table style="width:300px;margin-left:auto">
    <thead><tr><th colspan="2">Summary</th></tr></thead>
    <tbody>
      <tr><td>Total Present</td><td><span class="badge badge-present"><?= $totPresent ?></span></td></tr>
      <tr><td>Total Absent</td><td><span class="badge badge-absent"><?= $totAbsent ?></span></td></tr>
      <tr><td>Total Late</td><td><span class="badge badge-late"><?= $totLate ?></span></td></tr>
      <tr><td>Average Attendance</td>
        <td><span class="<?= $avgPct>=75?'pct-high':($avgPct>=50?'pct-medium':'pct-low') ?>"><?= $avgPct ?>%</span></td>
      </tr>
    </tbody>
  </table>
<?php endif; ?>
<?php endif; ?>

<div class="footer">
  <span>IT Faculty Attendance Management System &copy; <?= date('Y') ?></span>
  <span>Confidential — For internal use only</span>
</div>

<script>
// Auto-open print dialog on load so it behaves like a download
window.addEventListener('load', function() {
  // Small delay so the page renders before print dialog opens
  setTimeout(function() { window.print(); }, 500);
});
</script>
</body>
</html>
<?php
    exit;

} catch (Throwable $e) {
    error_log('reports.php error: ' . $e->getMessage());
    if (headers_sent()) { echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>'; exit; }
    header('Content-Type: application/json');
    jsonResponse(false, 'Report error: ' . $e->getMessage(), null, 500);
}
