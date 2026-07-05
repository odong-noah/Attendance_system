<?php
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

Auth::startSession();
Auth::requireAdmin();
$activePage = 'reports';
$pageTitle  = 'Reports';
$programs = DB::run("SELECT id, name FROM programs WHERE is_active=1 ORDER BY name")->fetchAll();
$courses  = DB::run("SELECT id, code, name FROM course_units WHERE is_active=1 ORDER BY code")->fetchAll();
$ay = currentAcademicYear();
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
<script>window.BASE_PATH='<?= addslashes(BASE_PATH) ?>';window.USER_ROLE='super_admin';</script>
<div class="app-wrapper">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <div class="main-content">
    <?php include __DIR__ . '/../partials/topbar.php'; ?>
    <div class="page-content">
      <div class="page-header">
        <div><h2>Attendance Reports</h2>
          <div class="breadcrumb">Generate downloadable PDF reports. Each report opens in a new tab — use Print → Save as PDF.</div>
        </div>
      </div>

      <div class="row g-3">
        <!-- Faculty Summary -->
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-body text-center">
              <div class="stat-icon blue mx-auto mb-3" style="width:60px;height:60px;font-size:1.5rem"><i class="fas fa-chart-bar"></i></div>
              <h4>Faculty Summary</h4>
              <p class="text-muted fs-sm mt-2">Overview of all courses, sessions, and attendance percentages faculty-wide.</p>
              <div class="form-group text-start mt-3">
                <label class="form-label">Academic Year</label>
                <input type="text" class="form-control" id="ay1" value="<?= htmlspecialchars($ay) ?>">
              </div>
              <div class="form-group text-start">
                <label class="form-label">Semester</label>
                <select class="form-control" id="sem1">
                  <option value="I">Semester I</option>
                  <option value="II">Semester II</option>
                </select>
              </div>
              <a href="#" id="summaryLink" class="btn btn-primary mt-3" style="width:100%;justify-content:center" target="_blank">
                <i class="fas fa-file-pdf"></i> Generate PDF
              </a>
            </div>
          </div>
        </div>

        <!-- Flagged Students -->
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-body text-center">
              <div class="stat-icon red mx-auto mb-3" style="width:60px;height:60px;font-size:1.5rem"><i class="fas fa-flag"></i></div>
              <h4>Flagged Students</h4>
              <p class="text-muted fs-sm mt-2">All students flagged for missing 4+ lectures — for academic intervention.</p>
              <div class="form-group text-start mt-3">
                <label class="form-label">Filter by Program</label>
                <select class="form-control" id="flagProgram">
                  <option value="">All Programs</option>
                  <?php foreach ($programs as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <a href="#" id="flaggedLink" class="btn btn-danger mt-3" style="width:100%;justify-content:center;margin-top:3.6rem!important" target="_blank">
                <i class="fas fa-file-pdf"></i> Generate PDF
              </a>
            </div>
          </div>
        </div>

        <!-- Course Unit -->
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-body text-center">
              <div class="stat-icon green mx-auto mb-3" style="width:60px;height:60px;font-size:1.5rem"><i class="fas fa-book"></i></div>
              <h4>Course Unit Report</h4>
              <p class="text-muted fs-sm mt-2">Detailed per-student attendance for a specific course unit.</p>
              <div class="form-group text-start mt-3">
                <label class="form-label">Select Course Unit</label>
                <select class="form-control" id="courseSelect">
                  <option value="">— Choose a course unit —</option>
                  <?php foreach ($courses as $c): ?>
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
      </div>

      <!-- Student Report -->
      <div class="card mt-3">
        <div class="card-header"><h3><i class="fas fa-user-graduate"></i> Individual Student Report</h3></div>
        <div class="card-body">
          <div class="row g-3 align-items-end">
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
const RBASE = <?= json_encode(BASE_PATH . '/api/reports.php') ?>;

function updateLinks() {
  const ay  = document.getElementById('ay1').value;
  const sem = document.getElementById('sem1').value;
  const pid = document.getElementById('flagProgram').value;
  const cid = document.getElementById('courseSelect').value;
  document.getElementById('summaryLink').href = `${RBASE}?type=summary&format=pdf&academic_year=${encodeURIComponent(ay)}&semester=${sem}`;
  document.getElementById('flaggedLink').href = `${RBASE}?type=flagged&format=pdf&program_id=${pid}`;
  document.getElementById('courseLink').href  = cid ? `${RBASE}?type=course&format=pdf&course_unit_id=${cid}` : '#';
}

['ay1','sem1','flagProgram','courseSelect'].forEach(id => document.getElementById(id).addEventListener('change', updateLinks));
document.getElementById('courseLink').addEventListener('click', function(e) {
  if (this.getAttribute('href') === '#') { e.preventDefault(); Toast.warning('Please select a course unit first.'); }
});
updateLinks();

let searchTimer;
document.getElementById('studentSearch').addEventListener('input', function() {
  clearTimeout(searchTimer);
  const q = this.value.trim();
  if (q.length < 2) { document.getElementById('studentResults').innerHTML = ''; return; }
  searchTimer = setTimeout(async () => {
    const res = await API.get('/api/search.php', { q, type: 'students' });
    if (!res.success || !res.data.students.length) {
      document.getElementById('studentResults').innerHTML = '<span class="text-muted fs-sm">No students found.</span>';
      return;
    }
    document.getElementById('studentResults').innerHTML = res.data.students.map(s => `
      <a href="${RBASE}?type=student&format=pdf&student_id=${s.id}" target="_blank" class="btn btn-sm btn-outline">
        <i class="fas fa-file-pdf"></i> ${s.name} (${s.student_number})
      </a>`).join('');
  }, 300);
});
</script>
</body>
</html>
