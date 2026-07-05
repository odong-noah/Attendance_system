<?php
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

Auth::startSession();
Auth::requireAdmin();
$activePage = 'lecturers';
$pageTitle  = 'Lecturer Management';
$programs = DB::run("SELECT id, code, name FROM programs WHERE is_active=1 ORDER BY name")->fetchAll();
$courses  = DB::run("SELECT id, code, name, program_id FROM course_units WHERE is_active=1 ORDER BY code")->fetchAll();
$ay = currentAcademicYear();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Lecturers — IT Attendance System</title>
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
    <div><h2>Lecturer Accounts</h2>
      <div class="breadcrumb">Create and manage lecturer accounts and course unit assignments.</div></div>
    <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> Add Lecturer</button>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="d-flex gap-2 align-center" style="flex-wrap:wrap">
        <input type="text" class="form-control" id="searchInput" placeholder="Search by name, email, ID..." style="width:240px">
        <select class="form-control" id="programFilter" style="width:200px">
          <option value="">All Programs</option>
          <?php foreach($programs as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-sm btn-outline" onclick="exportTableCSV('lecturersTable','lecturers.csv')"><i class="fas fa-download"></i> Export CSV</button>
    </div>
    <div class="card-body" style="padding:0">
      <div class="table-responsive">
        <table class="data-table" id="lecturersTable">
          <thead><tr><th>Employee ID</th><th>Name</th><th>Email</th><th>Program</th><th>Courses</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
          <tbody id="lecturersBody"><tr><td colspan="8" class="text-center text-muted" style="padding:2rem"><span class="loader" style="border-color:#ddd;border-top-color:#2563eb"></span></td></tr></tbody>
        </table>
      </div>
    </div>
    <div class="card-footer"><div class="pagination" id="pagination"></div></div>
  </div>
</div></div></div>

<!-- Lecturer Modal -->
<div class="modal-overlay" id="lecturerModal">
  <div class="modal-box modal-lg">
    <div class="modal-header">
      <h4 id="modalTitle"><i class="fas fa-user-plus"></i> Add New Lecturer</h4>
      <button class="modal-close">&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="lecturerId">
      <div class="row g-3">
        <div class="col-md-6 form-group"><label class="form-label">First Name *</label><input type="text" class="form-control" id="firstName"></div>
        <div class="col-md-6 form-group"><label class="form-label">Last Name *</label><input type="text" class="form-control" id="lastName"></div>
        <div class="col-md-6 form-group"><label class="form-label">Email *</label><input type="email" class="form-control" id="email"></div>
        <div class="col-md-6 form-group"><label class="form-label">Phone</label><input type="text" class="form-control" id="phone"></div>
        <div class="col-md-6 form-group"><label class="form-label">Program *</label>
          <select class="form-control" id="programId">
            <option value="">Select Program</option>
            <?php foreach($programs as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6 form-group"><label class="form-label" id="pwLabel">Password *</label>
          <input type="password" class="form-control" id="password" placeholder="Min 8 chars, uppercase, lowercase, number">
        </div>
        <div class="col-md-6 form-group"><label class="form-label">Academic Year</label>
          <input type="text" class="form-control" id="academicYear" value="<?= htmlspecialchars($ay) ?>">
        </div>
        <div class="col-md-6 form-group"><label class="form-label">Semester</label>
          <select class="form-control" id="semester"><option value="I">Semester I</option><option value="II">Semester II</option></select>
        </div>
        <div class="col-12 form-group">
          <label class="form-label">Assign Course Units</label>
          <div id="courseChecklist" style="max-height:200px;overflow-y:auto;border:1.5px solid var(--border);border-radius:.5rem;padding:.75rem">
            <p class="text-muted fs-sm">Select a program first to see course units.</p>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline modal-close">Cancel</button>
      <button class="btn btn-primary" id="saveBtn" onclick="saveLecturer()"><i class="fas fa-save"></i> Save</button>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/app.js"></script>
<script>
const ALL_COURSES = <?= json_encode($courses) ?>;
let currentPage = 1, isEdit = false;

async function loadLecturers(page) {
  page = page || 1; currentPage = page;
  const res = await API.get('/api/lecturers.php', { search: document.getElementById('searchInput').value, program_id: document.getElementById('programFilter').value, page });
  if (!res.success) { Toast.error(res.message); return; }
  const d = res.data;
  document.getElementById('lecturersBody').innerHTML = d.lecturers.length
    ? d.lecturers.map(l => `<tr>
        <td><strong>${l.employee_id}</strong></td>
        <td>${l.first_name} ${l.last_name}</td>
        <td>${l.email}</td>
        <td>${l.program_name || '—'}</td>
        <td><span class="badge badge-lecturer">${l.course_count} course(s)</span></td>
        <td>${statusBadge(l.is_active == 1 ? 'active' : 'inactive')}</td>
        <td class="fs-sm text-muted">${l.last_login ? fmtDateTime(l.last_login) : 'Never'}</td>
        <td>
          <button class="btn btn-sm btn-outline" onclick="editLecturer(${l.id})" title="Edit"><i class="fas fa-edit"></i></button>
          <button class="btn btn-sm btn-danger"  onclick="deactivate(${l.id})"   title="Deactivate"><i class="fas fa-user-slash"></i></button>
        </td></tr>`).join('')
    : '<tr><td colspan="8" class="text-center text-muted" style="padding:2rem">No lecturers found.</td></tr>';
  renderPagination('pagination', d.pagination, loadLecturers);
}

function renderCourseList(progId, selected) {
  selected = selected || [];
  const courses = ALL_COURSES.filter(c => c.program_id == progId);
  document.getElementById('courseChecklist').innerHTML = courses.length
    ? courses.map(c => `<label class="d-flex align-center gap-1" style="padding:.3rem 0;font-size:.85rem;cursor:pointer">
        <input type="checkbox" value="${c.id}" class="course-check" ${selected.includes(+c.id) ? 'checked' : ''}> ${c.code} — ${c.name}</label>`).join('')
    : '<p class="text-muted fs-sm">No course units for this program.</p>';
}

document.getElementById('programId').addEventListener('change', e => renderCourseList(e.target.value));

function openModal() {
  isEdit = false;
  ['lecturerId','firstName','lastName','email','phone','password'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('programId').value = '';
  document.getElementById('academicYear').value = <?= json_encode($ay) ?>;
  document.getElementById('courseChecklist').innerHTML = '<p class="text-muted fs-sm">Select a program first.</p>';
  document.getElementById('pwLabel').textContent = 'Password *';
  document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus"></i> Add New Lecturer';
  Modal.open('lecturerModal');
}

async function editLecturer(id) {
  const res = await API.get('/api/lecturers.php', { id });
  if (!res.success) { Toast.error(res.message); return; }
  const l = res.data;
  isEdit = true;
  document.getElementById('lecturerId').value    = l.id;
  document.getElementById('firstName').value     = l.first_name;
  document.getElementById('lastName').value      = l.last_name;
  document.getElementById('email').value         = l.email;
  document.getElementById('phone').value         = l.phone || '';
  document.getElementById('programId').value     = l.program_id;
  document.getElementById('password').value      = '';
  document.getElementById('pwLabel').textContent = 'Password (leave blank to keep)';
  document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Lecturer';
  const sel = l.course_assignments.map(c => +c.id);
  renderCourseList(l.program_id, sel);
  if (l.course_assignments.length) {
    document.getElementById('academicYear').value = l.course_assignments[0].academic_year;
    document.getElementById('semester').value     = l.course_assignments[0].semester;
  }
  Modal.open('lecturerModal');
}

async function saveLecturer() {
  const id = document.getElementById('lecturerId').value;
  const courseIds = Array.from(document.querySelectorAll('.course-check:checked')).map(c => +c.value);
  const payload = {
    first_name: document.getElementById('firstName').value.trim(),
    last_name:  document.getElementById('lastName').value.trim(),
    email:      document.getElementById('email').value.trim(),
    phone:      document.getElementById('phone').value.trim(),
    program_id: document.getElementById('programId').value,
    academic_year: document.getElementById('academicYear').value.trim(),
    semester:   document.getElementById('semester').value,
    course_unit_ids: courseIds
  };
  const pw = document.getElementById('password').value;
  if (pw) payload.password = pw;
  if (!payload.first_name || !payload.last_name || !payload.email || !payload.program_id) {
    Toast.warning('Please fill in all required fields.'); return;
  }
  const btn = document.getElementById('saveBtn');
  btn.disabled = true; btn.innerHTML = '<span class="loader"></span> Saving...';
  const res = isEdit
    ? await API.put('/api/lecturers.php', { id, ...payload })
    : await API.post('/api/lecturers.php', payload);
  btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save';
  if (res.success) { Toast.success(res.message); Modal.closeAll(); loadLecturers(currentPage); }
  else Toast.error(res.message);
}

async function deactivate(id) {
  confirmAction('Deactivate this lecturer? They will no longer be able to log in.', async () => {
    const res = await API.delete('/api/lecturers.php', { id });
    if (res.success) { Toast.success(res.message); loadLecturers(currentPage); }
    else Toast.error(res.message);
  });
}

document.getElementById('searchInput').addEventListener('input', () => { clearTimeout(window._lt); window._lt = setTimeout(() => loadLecturers(1), 350); });
document.getElementById('programFilter').addEventListener('change', () => loadLecturers(1));
loadLecturers();
</script>
</body></html>
