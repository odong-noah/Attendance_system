<?php
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::startSession();
Auth::requireLecturer();
$activePage = 'courses';
$pageTitle  = 'My Course Units';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Courses — IT Attendance System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/app.css">
</head>
<body>
<script>window.BASE_PATH='<?= addslashes(BASE_PATH) ?>';window.USER_ROLE='lecturer';</script>
<div class="app-wrapper">
<?php include __DIR__ . '/../partials/sidebar.php'; ?>
<div class="main-content">
<?php include __DIR__ . '/../partials/topbar.php'; ?>
<div class="page-content">
  <div class="page-header">
    <div><h2>My Course Units</h2>
      <div class="breadcrumb">Course units assigned to you by the Faculty Dean.</div></div>
  </div>
  <div id="coursesGrid" class="stats-grid"></div>
</div></div></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/app.js"></script>
<script>
async function loadCourses() {
  const res = await API.get('/api/dashboard.php');
  if (!res.success) { Toast.error(res.message); return; }
  const grid = document.getElementById('coursesGrid');
  const courses = res.data.courses || [];
  const base = window.BASE_PATH || '';
  const rBase = base + '/api/reports.php';

  if (!courses.length) {
    grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1"><div class="icon"><i class="fas fa-book"></i></div><h4>No course units assigned yet</h4><p>Contact the Faculty Dean to be assigned course units.</p></div>';
    return;
  }
  grid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.25rem';
  grid.innerHTML = courses.map(c => `
    <div class="card">
      <div class="card-body">
        <div class="d-flex" style="justify-content:space-between;align-items:flex-start;margin-bottom:.75rem">
          <div>
            <h4 style="font-size:1rem;font-weight:700">${c.code}</h4>
            <p class="text-muted fs-sm" style="margin-top:.2rem">${c.name}</p>
          </div>
          <span class="badge badge-lecturer">Sem ${c.semester}</span>
        </div>
        <div class="d-flex gap-1" style="align-items:center;margin-bottom:.75rem">
          ${buildProgressBar(+c.pct || 0)}
        </div>
        <div class="d-flex gap-2 fs-sm text-muted" style="margin-bottom:1rem">
          <span><i class="fas fa-users"></i> ${c.enrolled_students} students</span>
          <span><i class="fas fa-calendar"></i> ${c.sessions} sessions</span>
          <span><i class="fas fa-check text-success"></i> ${c.present} present</span>
        </div>
        <div class="d-flex gap-1">
          <a href="${base}/pages/lecturer/attendance.php?course_unit_id=${c.id}"
             class="btn btn-sm btn-primary" style="flex:1;justify-content:center">
            <i class="fas fa-clipboard-check"></i> Take Attendance
          </a>
          <a href="${rBase}?type=course&format=pdf&course_unit_id=${c.id}"
             target="_blank" class="btn btn-sm btn-outline" title="Download PDF Report">
            <i class="fas fa-file-pdf"></i>
          </a>
        </div>
      </div>
    </div>`).join('');
}
loadCourses();
</script>
</body></html>
