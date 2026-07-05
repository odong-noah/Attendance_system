<?php
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::startSession();
Auth::requireAdmin();
$activePage = 'flagged';
$pageTitle  = 'Flagged Students';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Flagged Students — IT Attendance System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/app.css">
</head>
<body>
<script>window.BASE_PATH='<?= addslashes(BASE_PATH) ?>';window.USER_ROLE='super_admin';</script>
<div class="app-wrapper">
<?php include __DIR__ . '/../partials/sidebar.php'; ?>
<div class="main-content">
<?php include __DIR__ . '/../partials/topbar.php'; ?>
<div class="page-content">
  <div class="page-header">
    <div><h2><i class="fas fa-flag text-danger"></i> Flagged Students</h2>
      <div class="breadcrumb">Students flagged for missing <?= ABSENCE_FLAG_THRESHOLD ?>+ lectures (auto-flagged by database trigger).</div></div>
    <a href="<?= BASE_PATH ?>/api/reports.php?type=flagged&format=pdf" target="_blank" class="btn btn-danger">
      <i class="fas fa-file-pdf"></i> Download PDF
    </a>
  </div>
  <div class="alert alert-warning mb-3">
    <i class="fas fa-robot"></i> Students are <strong>automatically flagged</strong> by the database when absences reach <strong><?= ABSENCE_FLAG_THRESHOLD ?></strong> in any single course unit. This list helps identify students needing academic intervention.
  </div>
  <div class="card">
    <div class="card-body" style="padding:0">
      <table class="data-table">
        <thead><tr><th>Student No.</th><th>Name</th><th>Program</th><th>Year</th><th>Absences</th><th>Reason</th></tr></thead>
        <tbody id="flaggedBody"><tr><td colspan="6" class="text-center text-muted" style="padding:2rem">Loading...</td></tr></tbody>
      </table>
    </div>
  </div>
</div></div></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/app.js"></script>
<script>
async function load() {
  const res = await API.get('/api/students.php', { flagged:1, limit:100 });
  if (!res.success) { Toast.error(res.message); return; }
  document.getElementById('flaggedBody').innerHTML = res.data.students.length
    ? res.data.students.map(s=>`<tr class="row-flagged">
        <td><strong>${s.student_number}</strong></td>
        <td>${s.first_name} ${s.last_name}</td>
        <td>${s.program_name}</td>
        <td>Y${s.year_of_study}</td>
        <td><span class="badge badge-flagged"><i class="fas fa-flag"></i> ${s.flag_reason?.match(/\d+/)?.[0]||'4+'}</span></td>
        <td class="fs-sm text-muted">${s.flag_reason||'—'}</td>
      </tr>`).join('')
    : '<tr><td colspan="6" class="text-center" style="padding:3rem"><i class="fas fa-check-circle fa-2x text-success"></i><br><br><strong>No flagged students</strong> — great attendance across all programs 🎉</td></tr>';
}
load();
</script>
</body></html>
