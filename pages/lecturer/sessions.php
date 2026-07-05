<?php
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::startSession();
Auth::requireLecturer();
$activePage = 'sessions';
$pageTitle  = 'Lecture Sessions';
$isAdmin = Auth::isAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sessions — IT Attendance System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/app.css">
</head>
<body>
<script>window.BASE_PATH='<?= addslashes(BASE_PATH) ?>';window.USER_ROLE='<?= $isAdmin?"super_admin":"lecturer" ?>';</script>
<div class="app-wrapper">
<?php include __DIR__ . '/../partials/sidebar.php'; ?>
<div class="main-content">
<?php include __DIR__ . '/../partials/topbar.php'; ?>
<div class="page-content">
  <div class="page-header">
    <div><h2>Lecture Sessions</h2><div class="breadcrumb">All sessions you have conducted.</div></div>
    <a href="<?= BASE_PATH ?>/pages/lecturer/attendance.php" class="btn btn-primary">
      <i class="fas fa-plus"></i> New Session
    </a>
  </div>
  <div class="card">
    <div class="card-body" style="padding:0">
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Date</th><th>Course</th><th>Topic</th><th>Venue</th><th>Present</th><th>Absent</th><th>Total</th></tr></thead>
          <tbody id="sessionsBody"><tr><td colspan="7" class="text-center text-muted" style="padding:2rem">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>
    <div class="card-footer"><div class="pagination" id="pagination"></div></div>
  </div>
</div></div></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/app.js"></script>
<script>
async function loadSessions(page) {
  page = page || 1;
  const res = await API.get('/api/attendance.php', { page });
  if (!res.success) { Toast.error(res.message); return; }
  document.getElementById('sessionsBody').innerHTML = res.data.sessions.length
    ? res.data.sessions.map(s => `<tr>
        <td>${fmtDate(s.session_date)}<br><span class="fs-sm text-muted">${s.start_time.slice(0,5)}–${s.end_time.slice(0,5)}</span></td>
        <td><strong>${s.course_code}</strong><br><span class="fs-sm text-muted">${s.course_name}</span></td>
        <td>${s.topic || '—'}</td>
        <td>${s.venue || '—'}</td>
        <td><span class="text-success fw-700">${s.present_count}</span></td>
        <td><span class="text-danger fw-700">${s.absent_count}</span></td>
        <td>${s.marked_count}</td>
      </tr>`).join('')
    : '<tr><td colspan="7" class="text-center text-muted" style="padding:2rem">No sessions recorded yet.</td></tr>';
  renderPagination('pagination', res.data.pagination, loadSessions);
}
loadSessions();
</script>
</body></html>
