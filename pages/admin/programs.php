<?php
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::startSession();
Auth::requireAdmin();
$activePage = 'programs';
$pageTitle  = 'Programs & Course Units';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Programs & Courses — IT Attendance System</title>
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
    <div><h2>Programs &amp; Course Units</h2>
      <div class="breadcrumb">Manage the four IT Faculty programs and their course offerings.</div></div>
  </div>

  <div class="stats-grid" id="programCards">
    <div class="text-muted fs-sm">Loading programs...</div>
  </div>

  <div class="card mt-3">
    <div class="card-header">
      <h3><i class="fas fa-book"></i> Course Units</h3>
      <div class="d-flex gap-1">
        <select class="form-control" id="programFilter" style="width:200px">
          <option value="">All Programs</option>
        </select>
        <button class="btn btn-primary btn-sm" onclick="openCourseModal()"><i class="fas fa-plus"></i> Add Course Unit</button>
      </div>
    </div>
    <div class="card-body" style="padding:0">
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Code</th><th>Name</th><th>Program</th><th>Credits</th><th>Year</th><th>Semester</th><th>Enrolled</th><th>Actions</th></tr></thead>
          <tbody id="coursesBody"><tr><td colspan="8" class="text-center text-muted" style="padding:2rem">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>
</div></div></div>

<!-- Course Modal -->
<div class="modal-overlay" id="courseModal">
  <div class="modal-box">
    <div class="modal-header"><h4 id="courseModalTitle"><i class="fas fa-book"></i> Add Course Unit</h4><button class="modal-close">&times;</button></div>
    <div class="modal-body">
      <input type="hidden" id="courseId">
      <div class="row g-3">
        <div class="col-md-6 form-group"><label class="form-label">Course Code *</label><input type="text" class="form-control" id="code" placeholder="e.g. IT2201"></div>
        <div class="col-md-6 form-group"><label class="form-label">Credit Units</label><input type="number" class="form-control" id="creditUnits" value="3" min="1" max="6"></div>
        <div class="col-12 form-group"><label class="form-label">Course Name *</label><input type="text" class="form-control" id="courseName"></div>
        <div class="col-md-6 form-group"><label class="form-label">Program *</label>
          <select class="form-control" id="courseProgramId"><option value="">Select Program</option></select>
        </div>
        <div class="col-md-3 form-group"><label class="form-label">Year of Study</label>
          <select class="form-control" id="yearOfStudy"><option>1</option><option>2</option><option>3</option><option>4</option></select>
        </div>
        <div class="col-md-3 form-group"><label class="form-label">Semester</label>
          <select class="form-control" id="courseSemester"><option value="I">I</option><option value="II">II</option></select>
        </div>
        <div class="col-12 form-group"><label class="form-label">Description</label><textarea class="form-control" id="description" rows="2"></textarea></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline modal-close">Cancel</button>
      <button class="btn btn-primary" onclick="saveCourse()"><i class="fas fa-save"></i> Save</button>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/app.js"></script>
<script>
let PROGRAMS = [], isEdit = false;

async function loadPrograms() {
  const res = await API.get('/api/programs.php', { entity:'programs' });
  if (!res.success) { Toast.error(res.message); return; }
  PROGRAMS = res.data;

  document.getElementById('programCards').innerHTML = PROGRAMS.map(p => `
    <div class="stat-card">
      <div class="stat-icon blue"><i class="fas fa-sitemap"></i></div>
      <div>
        <div class="stat-value" style="font-size:1.1rem">${p.code}</div>
        <div class="stat-label">${p.name}</div>
        <div class="fs-sm text-muted mt-1">${p.course_count} courses &bull; ${p.student_count} students &bull; ${p.lecturer_count} lecturers</div>
      </div>
    </div>`).join('');

  const opts = PROGRAMS.map(p=>`<option value="${p.id}">${p.name}</option>`).join('');
  document.getElementById('programFilter').innerHTML = '<option value="">All Programs</option>' + opts;
  document.getElementById('courseProgramId').innerHTML = '<option value="">Select Program</option>' + opts;
}

async function loadCourses() {
  const pid = document.getElementById('programFilter').value;
  const res = await API.get('/api/programs.php', { entity:'courses', program_id: pid });
  if (!res.success) { Toast.error(res.message); return; }
  document.getElementById('coursesBody').innerHTML = res.data.length
    ? res.data.map(c=>`<tr>
        <td><strong>${c.code}</strong></td><td>${c.name}</td><td>${c.program_name}</td>
        <td>${c.credit_units}</td><td>Y${c.year_of_study}</td><td>Sem ${c.semester}</td>
        <td><span class="badge badge-lecturer">${c.enrolled_count}</span></td>
        <td><button class="btn btn-sm btn-outline" onclick='editCourse(${JSON.stringify(c)})' title="Edit"><i class="fas fa-edit"></i></button></td>
      </tr>`).join('')
    : '<tr><td colspan="8" class="text-center text-muted" style="padding:2rem">No course units found.</td></tr>';
}

function openCourseModal() {
  isEdit=false;
  ['courseId','code','courseName','description'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('creditUnits').value=3; document.getElementById('yearOfStudy').value=1;
  document.getElementById('courseSemester').value='I'; document.getElementById('courseProgramId').value='';
  document.getElementById('courseModalTitle').innerHTML='<i class="fas fa-book"></i> Add Course Unit';
  Modal.open('courseModal');
}

function editCourse(c) {
  isEdit=true;
  document.getElementById('courseId').value=c.id;
  document.getElementById('code').value=c.code;
  document.getElementById('courseName').value=c.name;
  document.getElementById('creditUnits').value=c.credit_units;
  document.getElementById('courseProgramId').value=c.program_id;
  document.getElementById('yearOfStudy').value=c.year_of_study;
  document.getElementById('courseSemester').value=c.semester;
  document.getElementById('description').value=c.description||'';
  document.getElementById('courseModalTitle').innerHTML='<i class="fas fa-edit"></i> Edit Course Unit';
  Modal.open('courseModal');
}

async function saveCourse() {
  const id=document.getElementById('courseId').value;
  const payload={
    code: document.getElementById('code').value.trim(),
    name: document.getElementById('courseName').value.trim(),
    credit_units: document.getElementById('creditUnits').value,
    program_id: document.getElementById('courseProgramId').value,
    year_of_study: document.getElementById('yearOfStudy').value,
    semester: document.getElementById('courseSemester').value,
    description: document.getElementById('description').value.trim()
  };
  if (!payload.code||!payload.name||!payload.program_id){ Toast.warning('Please fill in required fields.'); return; }
  const res = isEdit
    ? await API.put('/api/programs.php?entity=courses', {id,...payload})
    : await API.post('/api/programs.php?entity=courses', payload);
  if (res.success){ Toast.success(res.message); Modal.closeAll(); loadCourses(); loadPrograms(); }
  else Toast.error(res.message);
}

document.getElementById('programFilter').addEventListener('change', loadCourses);
(async()=>{ await loadPrograms(); await loadCourses(); })();
</script>
</body></html>
