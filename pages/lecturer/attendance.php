<?php
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

Auth::startSession();
Auth::requireLecturer();
$activePage = 'attendance';
$pageTitle  = 'Take Attendance';
$user    = Auth::user();
$isAdmin = Auth::isAdmin();
$ay      = currentAcademicYear();

$sql = $isAdmin
    ? "SELECT id, code, name FROM course_units WHERE is_active=1 ORDER BY code"
    : "SELECT DISTINCT cu.id, cu.code, cu.name FROM lecturer_course_assignments lca
       JOIN course_units cu ON lca.course_unit_id=cu.id WHERE lca.lecturer_id=? ORDER BY cu.code";
$myCourses = $isAdmin ? DB::run($sql)->fetchAll() : DB::run($sql, [$user['id']])->fetchAll();
$preselect = (int)($_GET['course_unit_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Take Attendance — IT Attendance System</title>
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
    <div><h2><i class="fas fa-clipboard-check"></i> Take Attendance</h2>
      <div class="breadcrumb">Create a lecture session then mark attendance for each student.</div></div>
  </div>

  <?php if (empty($myCourses)): ?>
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> No course units assigned. Contact the Faculty Dean.</div>
  <?php else: ?>

  <div class="card" id="sessionCard">
    <div class="card-header"><h3><i class="fas fa-calendar-plus"></i> Step 1 — Session Details</h3></div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4 form-group"><label class="form-label">Course Unit *</label>
          <select class="form-control" id="courseUnitId">
            <option value="">Select course unit</option>
            <?php foreach($myCourses as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $preselect==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['code'].' — '.$c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 form-group"><label class="form-label">Session Date *</label>
          <input type="date" class="form-control" id="sessionDate" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-md-2 form-group"><label class="form-label">Start Time *</label>
          <input type="time" class="form-control" id="startTime" value="<?= date('H:i') ?>">
        </div>
        <div class="col-md-2 form-group"><label class="form-label">End Time *</label>
          <input type="time" class="form-control" id="endTime" value="<?= date('H:i', strtotime('+2 hours')) ?>">
        </div>
        <div class="col-md-4 form-group"><label class="form-label">Topic</label>
          <input type="text" class="form-control" id="topic" placeholder="e.g. Introduction to Loops">
        </div>
        <div class="col-md-4 form-group"><label class="form-label">Venue</label>
          <input type="text" class="form-control" id="venue" placeholder="e.g. Lab 2">
        </div>
        <div class="col-md-2 form-group"><label class="form-label">Academic Year</label>
          <input type="text" class="form-control" id="academicYear" value="<?= htmlspecialchars($ay) ?>">
        </div>
        <div class="col-md-2 form-group"><label class="form-label">Semester</label>
          <select class="form-control" id="semester"><option value="I">I</option><option value="II">II</option></select>
        </div>
      </div>
      <button class="btn btn-primary mt-2" id="startBtn" onclick="startSession()">
        <i class="fas fa-play"></i> Load Attendance Sheet
      </button>
    </div>
  </div>

  <div class="card mt-3" id="sheetCard" style="display:none">
    <div class="card-header">
      <h3><i class="fas fa-users"></i> Step 2 — Mark Attendance: <span id="sheetLabel"></span></h3>
      <div class="d-flex gap-1">
        <button class="btn btn-sm btn-success" onclick="markAll('present')"><i class="fas fa-check"></i> All Present</button>
        <button class="btn btn-sm btn-danger"  onclick="markAll('absent')"><i class="fas fa-times"></i> All Absent</button>
      </div>
    </div>
    <div class="card-body" style="padding:0">
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>#</th><th>Student No.</th><th>Name</th><th>Status</th><th>Remarks</th></tr></thead>
          <tbody id="sheetBody"></tbody>
        </table>
      </div>
    </div>
    <div class="card-footer d-flex" style="justify-content:space-between;align-items:center">
      <span id="sheetSummary" class="fs-sm text-muted"></span>
      <button class="btn btn-success" id="saveBtn" onclick="saveAttendance()"><i class="fas fa-save"></i> Save Attendance</button>
    </div>
  </div>
  <?php endif; ?>
</div></div></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/app.js"></script>
<script>
let sessionId = null, students = [];

async function startSession() {
  const cuId = document.getElementById('courseUnitId').value;
  const date  = document.getElementById('sessionDate').value;
  const start = document.getElementById('startTime').value;
  const end   = document.getElementById('endTime').value;
  if (!cuId || !date || !start || !end) { Toast.warning('Please fill in course unit, date, and times.'); return; }

  const btn = document.getElementById('startBtn');
  btn.disabled=true; btn.innerHTML='<span class="loader"></span> Creating session...';

  const res = await API.post('/api/attendance.php', {
    action: 'create_session',
    course_unit_id: cuId,
    session_date:   date,
    start_time:     start,
    end_time:       end,
    topic:          document.getElementById('topic').value,
    venue:          document.getElementById('venue').value,
    academic_year:  document.getElementById('academicYear').value,
    semester:       document.getElementById('semester').value
  });

  btn.disabled=false; btn.innerHTML='<i class="fas fa-play"></i> Load Attendance Sheet';
  if (!res.success) { Toast.error(res.message); return; }

  Toast.success('Session created! Now mark attendance below.');
  sessionId = res.data.session_id;
  await loadSheet(sessionId);
}

async function loadSheet(sid) {
  const res = await API.get('/api/attendance.php', { action: 'sheet', session_id: sid });
  if (!res.success) { Toast.error(res.message); return; }
  students = res.data.students;
  document.getElementById('sheetLabel').textContent = res.data.session.course_code + ' — ' + res.data.session.course_name;
  renderSheet();
  document.getElementById('sheetCard').style.display = '';
  document.getElementById('sheetCard').scrollIntoView({ behavior: 'smooth' });
}

function renderSheet() {
  if (!students.length) {
    document.getElementById('sheetBody').innerHTML =
      '<tr><td colspan="5" class="text-center text-muted" style="padding:2rem">No students enrolled in this course unit. <a href="students.php">Add students →</a></td></tr>';
    updateSummary(); return;
  }
  document.getElementById('sheetBody').innerHTML = students.map((s, i) => `
    <tr class="${s.is_flagged?'row-flagged':''}" data-sid="${s.student_id}">
      <td>${i+1}</td>
      <td>${s.student_number}</td>
      <td>${s.first_name} ${s.last_name} ${s.is_flagged?'<i class="fas fa-flag flag-icon" title="Flagged"></i>':''}</td>
      <td>
        <div class="att-btn-group">
          ${['present','absent','late','excused'].map(st =>
            `<button class="att-btn ${s.status===st?'active-'+st:''}"
              onclick="setStatus(${s.student_id},'${st}',this)">${st.charAt(0).toUpperCase()+st.slice(1)}</button>`
          ).join('')}
        </div>
      </td>
      <td><input type="text" class="form-control form-control-sm remarks-inp" data-sid="${s.student_id}" value="${s.remarks||''}" placeholder="Optional" style="font-size:.8rem;padding:.3rem .6rem"></td>
    </tr>`).join('');
  updateSummary();
}

function setStatus(sid, status, btn) {
  const s = students.find(x => x.student_id===sid);
  s.status = status;
  const row = btn.closest('tr');
  row.querySelectorAll('.att-btn').forEach(b => b.className='att-btn');
  btn.classList.add('active-'+status);
  row.classList.toggle('row-absent', status==='absent');
  updateSummary();
}

function markAll(status) { students.forEach(s => s.status=status); renderSheet(); Toast.info('All students marked as '+status+'.'); }

function updateSummary() {
  const p=students.filter(s=>s.status==='present').length;
  const a=students.filter(s=>s.status==='absent').length;
  const n=students.filter(s=>!s.status||s.status==='not_marked').length;
  document.getElementById('sheetSummary').innerHTML =
    `<span class="text-success">${p} present</span> &bull; <span class="text-danger">${a} absent</span> &bull; ${n} not marked &bull; ${students.length} total`;
}

async function saveAttendance() {
  if (!sessionId) return;
  document.querySelectorAll('.remarks-inp').forEach(inp => {
    const s=students.find(x=>x.student_id==inp.dataset.sid); if(s) s.remarks=inp.value;
  });
  const records = students.filter(s=>s.status&&s.status!=='not_marked').map(s=>({ student_id:s.student_id, status:s.status, remarks:s.remarks||'' }));
  if (!records.length) { Toast.warning('Please mark at least one student before saving.'); return; }
  const btn=document.getElementById('saveBtn');
  btn.disabled=true; btn.innerHTML='<span class="loader"></span> Saving...';
  const res = await API.post('/api/attendance.php', { action:'mark_attendance', session_id:sessionId, records });
  btn.disabled=false; btn.innerHTML='<i class="fas fa-save"></i> Save Attendance';
  if (res.success) Toast.success('Attendance saved successfully! (' + records.length + ' records)');
  else Toast.error(res.message);
}
</script>
</body></html>
