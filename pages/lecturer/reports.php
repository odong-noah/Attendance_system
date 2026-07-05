<?php
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

Auth::startSession();
Auth::requireLecturer();
$activePage = 'reports';
$pageTitle  = 'Reports';
$user    = Auth::user();
$isAdmin = Auth::isAdmin();

$sql = $isAdmin
    ? "SELECT id, code, name FROM course_units WHERE is_active=1 ORDER BY code"
    : "SELECT DISTINCT cu.id, cu.code, cu.name FROM lecturer_course_assignments lca
       JOIN course_units cu ON lca.course_unit_id=cu.id WHERE lca.lecturer_id=? ORDER BY cu.code";
$myCourses = $isAdmin ? DB::run($sql)->fetchAll() : DB::run($sql, [$user['id']])->fetchAll();
$rBase = BASE_PATH . '/api/reports.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports — IT Faculty Attendance System</title>
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
        <div><h2>Reports</h2>
          <div class="breadcrumb">Generate PDF reports. Each report opens in a new tab — use Print → Save as PDF.</div></div>
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-body text-center">
              <div class="stat-icon green mx-auto mb-3" style="width:60px;height:60px;font-size:1.5rem"><i class="fas fa-book"></i></div>
              <h4>Course Unit Report</h4>
              <p class="text-muted fs-sm mt-2">Per-student attendance breakdown for one of your course units.</p>
              <div class="form-group text-start mt-3">
                <label class="form-label">Select Course Unit</label>
                <select class="form-control" id="courseSelect">
                  <option value="">— Choose a course unit —</option>
                  <?php foreach ($myCourses as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['code'] . ' — ' . $c['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <a href="#" id="courseLink" class="btn btn-success mt-3" style="width:100%;justify-content:center" target="_blank">
                <i class="fas fa-file-pdf"></i> Generate PDF
              </a>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-body text-center">
              <div class="stat-icon red mx-auto mb-3" style="width:60px;height:60px;font-size:1.5rem"><i class="fas fa-flag"></i></div>
              <h4>Flagged Students</h4>
              <p class="text-muted fs-sm mt-2">Students in your program flagged for excessive absences (4+ missed lectures).</p>
              <a href="<?= htmlspecialchars($rBase) ?>?type=flagged&format=pdf" target="_blank"
                 class="btn btn-danger mt-3" style="width:100%;justify-content:center;margin-top:5rem!important">
                <i class="fas fa-file-pdf"></i> Generate PDF
              </a>
            </div>
          </div>
        </div>
      </div>

      <div class="card mt-3">
        <div class="card-header"><h3><i class="fas fa-user"></i> Individual Student Report</h3></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Search Student</label>
              <input type="text" class="form-control" id="studentSearch" placeholder="Type student name or number...">
            </div>
          </div>
          <div id="studentResults" class="mt-2 d-flex flex-wrap gap-2"></div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/app.js"></script>
<script>
const RBASE = <?= json_encode($rBase) ?>;

document.getElementById('courseSelect').addEventListener('change', function() {
  const cid = this.value;
  document.getElementById('courseLink').href = cid ? `${RBASE}?type=course&format=pdf&course_unit_id=${cid}` : '#';
});
document.getElementById('courseLink').addEventListener('click', function(e) {
  if (this.getAttribute('href') === '#') { e.preventDefault(); Toast.warning('Please select a course unit first.'); }
});

let t;
document.getElementById('studentSearch').addEventListener('input', function() {
  clearTimeout(t);
  const q = this.value.trim();
  if (q.length < 2) { document.getElementById('studentResults').innerHTML=''; return; }
  t = setTimeout(async () => {
    const res = await API.get('/api/search.php', { q, type: 'students' });
    if (!res.success || !res.data.students.length) {
      document.getElementById('studentResults').innerHTML='<span class="text-muted fs-sm">No students found.</span>'; return;
    }
    document.getElementById('studentResults').innerHTML = res.data.students.map(s =>
      `<a href="${RBASE}?type=student&format=pdf&student_id=${s.id}" target="_blank" class="btn btn-sm btn-outline">
         <i class="fas fa-file-pdf"></i> ${s.name} (${s.student_number})</a>`
    ).join('');
  }, 300);
});
</script>
</body>
</html>
