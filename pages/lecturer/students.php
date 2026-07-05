<?php
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

Auth::startSession();
Auth::requireLecturer();
$activePage = 'students';
$pageTitle  = 'Student Management';
$user    = Auth::user();
$isAdmin = Auth::isAdmin();
$ay      = currentAcademicYear();

$sql = $isAdmin
    ? "SELECT id, code, name FROM course_units WHERE is_active=1 ORDER BY code"
    : "SELECT DISTINCT cu.id, cu.code, cu.name FROM lecturer_course_assignments lca
       JOIN course_units cu ON lca.course_unit_id=cu.id WHERE lca.lecturer_id=? ORDER BY cu.code";
$myCourses = $isAdmin ? DB::run($sql)->fetchAll() : DB::run($sql, [$user['id']])->fetchAll();
$progId    = $user['program_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Students — IT Attendance System</title>
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
    <div><h2>Student Management</h2>
      <div class="breadcrumb">Add, edit, and manage students in your program.</div></div>
    <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-user-plus"></i> Add Student</button>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="d-flex gap-2" style="flex-wrap:wrap">
        <input type="text" class="form-control" id="searchInput" placeholder="Search name or number..." style="width:220px">
        <select class="form-control" id="flagFilter" style="width:160px">
          <option value="">All Students</option>
          <option value="1">Flagged Only</option>
          <option value="0">Not Flagged</option>
        </select>
      </div>
      <button class="btn btn-sm btn-outline" onclick="exportTableCSV('studentsTable','students.csv')"><i class="fas fa-download"></i> CSV</button>
    </div>
    <div class="card-body" style="padding:0">
      <div class="table-responsive">
        <table class="data-table" id="studentsTable">
          <thead><tr><th>Student No.</th><th>Name</th><th>Year/Sem</th><th>Email</th><th>Status</th><th>Flag</th><th>Actions</th></tr></thead>
          <tbody id="studentsBody"><tr><td colspan="7" class="text-center text-muted" style="padding:2rem"><span class="loader" style="border-color:#ddd;border-top-color:#2563eb"></span></td></tr></tbody>
        </table>
      </div>
    </div>
    <div class="card-footer"><div class="pagination" id="pagination"></div></div>
  </div>
</div></div></div>

<!-- Student Modal -->
<div class="modal-overlay" id="studentModal">
  <div class="modal-box modal-lg">
    <div class="modal-header">
      <h4 id="modalTitle"><i class="fas fa-user-plus"></i> Add Student</h4>
      <button class="modal-close">&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="studentId">
      <div class="row g-3">
        <div class="col-md-6 form-group"><label class="form-label">First Name *</label><input type="text" class="form-control" id="firstName"></div>
        <div class="col-md-6 form-group"><label class="form-label">Last Name *</label><input type="text" class="form-control" id="lastName"></div>
        <div class="col-md-6 form-group"><label class="form-label">Student Number <span class="text-muted fs-sm">(auto if blank)</span></label><input type="text" class="form-control" id="studentNumber"></div>
        <div class="col-md-6 form-group"><label class="form-label">Email</label><input type="email" class="form-control" id="email"></div>
        <div class="col-md-6 form-group"><label class="form-label">Phone</label><input type="text" class="form-control" id="phone"></div>
        <div class="col-md-6 form-group"><label class="form-label">Gender</label>
          <select class="form-control" id="gender"><option value="">Select</option><option>Male</option><option>Female</option><option>Other</option></select>
        </div>
        <div class="col-md-3 form-group"><label class="form-label">Year of Study</label>
          <select class="form-control" id="yearOfStudy"><option>1</option><option>2</option><option>3</option><option>4</option></select>
        </div>
        <div class="col-md-3 form-group"><label class="form-label">Semester</label>
          <select class="form-control" id="semester"><option value="I">I</option><option value="II">II</option></select>
        </div>
        <div class="col-md-6 form-group"><label class="form-label">Academic Year</label>
          <input type="text" class="form-control" id="academicYear" value="<?= htmlspecialchars($ay) ?>">
        </div>
        <div class="col-12 form-group">
          <label class="form-label">Enroll in Course Units</label>
          <div style="max-height:160px;overflow-y:auto;border:1.5px solid var(--border);border-radius:.5rem;padding:.75rem">
            <?php if ($myCourses): foreach ($myCourses as $c): ?>
              <label class="d-flex align-center gap-1" style="padding:.3rem 0;font-size:.85rem;cursor:pointer">
                <input type="checkbox" value="<?= $c['id'] ?>" class="course-check"> <?= htmlspecialchars($c['code'] . ' — ' . $c['name']) ?>
              </label>
            <?php endforeach; else: ?>
              <p class="text-muted fs-sm">No course units assigned to you.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline modal-close">Cancel</button>
      <button class="btn btn-primary" id="saveBtn" onclick="saveStudent()"><i class="fas fa-save"></i> Save Student</button>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/app.js"></script>
<script>
let currentPage = 1, isEdit = false;
const PROG_ID = <?= (int)$progId ?>;

async function loadStudents(page) {
  page = page || 1; currentPage = page;
  const res = await API.get('/api/students.php', { search: document.getElementById('searchInput').value, flagged: document.getElementById('flagFilter').value, page });
  if (!res.success) { Toast.error(res.message); return; }
  document.getElementById('studentsBody').innerHTML = res.data.students.length
    ? res.data.students.map(s => `<tr class="${s.is_flagged==1?'row-flagged':''}">
        <td><strong>${s.student_number}</strong></td>
        <td>${s.first_name} ${s.last_name}</td>
        <td>Y${s.year_of_study} Sem ${s.semester}</td>
        <td class="fs-sm text-muted">${s.email || '—'}</td>
        <td>${statusBadge(s.is_active==1?'active':'inactive')}</td>
        <td>${s.is_flagged==1?'<span class="badge badge-flagged"><i class="fas fa-flag"></i> Flagged</span>':'—'}</td>
        <td>
          <button class="btn btn-sm btn-outline" onclick="editStudent(${s.id})" title="Edit"><i class="fas fa-edit"></i></button>
          <button class="btn btn-sm btn-danger"  onclick="removeStudent(${s.id})" title="Remove"><i class="fas fa-trash"></i></button>
        </td></tr>`).join('')
    : '<tr><td colspan="7" class="text-center text-muted" style="padding:2rem">No students found. Click "Add Student" to get started.</td></tr>';
  renderPagination('pagination', res.data.pagination, loadStudents);
}

function openModal() {
  isEdit = false;
  ['studentId','firstName','lastName','studentNumber','email','phone'].forEach(id => document.getElementById(id).value='');
  document.getElementById('gender').value=''; document.getElementById('yearOfStudy').value=1;
  document.getElementById('semester').value='I';
  document.getElementById('academicYear').value=<?= json_encode($ay) ?>;
  document.querySelectorAll('.course-check').forEach(c=>c.checked=false);
  document.getElementById('modalTitle').innerHTML='<i class="fas fa-user-plus"></i> Add Student';
  Modal.open('studentModal');
}

async function editStudent(id) {
  const res = await API.get('/api/students.php', { id });
  if (!res.success) { Toast.error(res.message); return; }
  const s = res.data; isEdit = true;
  document.getElementById('studentId').value    = s.id;
  document.getElementById('firstName').value    = s.first_name;
  document.getElementById('lastName').value     = s.last_name;
  document.getElementById('studentNumber').value= s.student_number;
  document.getElementById('email').value        = s.email || '';
  document.getElementById('phone').value        = s.phone || '';
  document.getElementById('gender').value       = s.gender || '';
  document.getElementById('yearOfStudy').value  = s.year_of_study;
  document.getElementById('semester').value     = s.semester;
  document.getElementById('academicYear').value = s.academic_year;
  document.getElementById('modalTitle').innerHTML='<i class="fas fa-edit"></i> Edit Student';
  Modal.open('studentModal');
}

async function saveStudent() {
  const id = document.getElementById('studentId').value;
  const courseIds = Array.from(document.querySelectorAll('.course-check:checked')).map(c=>+c.value);
  const payload = {
    first_name:     document.getElementById('firstName').value.trim(),
    last_name:      document.getElementById('lastName').value.trim(),
    student_number: document.getElementById('studentNumber').value.trim(),
    email:          document.getElementById('email').value.trim(),
    phone:          document.getElementById('phone').value.trim(),
    gender:         document.getElementById('gender').value,
    year_of_study:  document.getElementById('yearOfStudy').value,
    semester:       document.getElementById('semester').value,
    academic_year:  document.getElementById('academicYear').value.trim(),
    program_id:     PROG_ID || undefined,
    course_unit_ids: courseIds
  };
  if (!payload.first_name || !payload.last_name) { Toast.warning('First and last name are required.'); return; }
  const btn = document.getElementById('saveBtn');
  btn.disabled=true; btn.innerHTML='<span class="loader"></span> Saving...';
  const res = isEdit
    ? await API.put('/api/students.php', { id, ...payload })
    : await API.post('/api/students.php', payload);
  btn.disabled=false; btn.innerHTML='<i class="fas fa-save"></i> Save Student';
  if (res.success) { Toast.success(res.message); Modal.closeAll(); loadStudents(currentPage); }
  else Toast.error(res.message);
}

function removeStudent(id) {
  confirmAction('Remove this student? They will be deactivated.', async () => {
    const res = await API.delete('/api/students.php', { id });
    if (res.success) { Toast.success(res.message); loadStudents(currentPage); }
    else Toast.error(res.message);
  });
}

document.getElementById('searchInput').addEventListener('input', ()=>{ clearTimeout(window._st); window._st=setTimeout(()=>loadStudents(1),350); });
document.getElementById('flagFilter').addEventListener('change', ()=>loadStudents(1));
loadStudents();
</script>
</body></html>
