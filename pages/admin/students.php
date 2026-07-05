<?php
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::startSession();
Auth::requireAdmin();
$activePage = 'students';
$pageTitle  = 'All Students';
$programs = DB::run("SELECT id, name FROM programs WHERE is_active=1 ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>All Students — IT Attendance System</title>
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
    <div><h2>All Students</h2><div class="breadcrumb">Cross-program view of every student in the IT Faculty.</div></div>
  </div>
  <div class="card">
    <div class="card-header">
      <div class="d-flex gap-2" style="flex-wrap:wrap">
        <input type="text" class="form-control" id="searchInput" placeholder="Search student..." style="width:220px">
        <select class="form-control" id="programFilter" style="width:200px">
          <option value="">All Programs</option>
          <?php foreach($programs as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?>
        </select>
        <select class="form-control" id="flagFilter" style="width:160px">
          <option value="">All Students</option><option value="1">Flagged Only</option><option value="0">Not Flagged</option>
        </select>
      </div>
      <button class="btn btn-sm btn-outline" onclick="exportTableCSV('studentsTable','students.csv')"><i class="fas fa-download"></i> Export CSV</button>
    </div>
    <div class="card-body" style="padding:0">
      <div class="table-responsive">
        <table class="data-table" id="studentsTable">
          <thead><tr><th>Student No.</th><th>Name</th><th>Program</th><th>Year</th><th>Email</th><th>Status</th><th>Flag</th></tr></thead>
          <tbody id="studentsBody"><tr><td colspan="7" class="text-center text-muted" style="padding:2rem">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>
    <div class="card-footer"><div class="pagination" id="pagination"></div></div>
  </div>
</div></div></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/app.js"></script>
<script>
async function load(page) {
  page = page||1;
  const res = await API.get('/api/students.php', {
    search:     document.getElementById('searchInput').value,
    program_id: document.getElementById('programFilter').value,
    flagged:    document.getElementById('flagFilter').value,
    page
  });
  if (!res.success) { Toast.error(res.message); return; }
  document.getElementById('studentsBody').innerHTML = res.data.students.length
    ? res.data.students.map(s=>`<tr class="${s.is_flagged==1?'row-flagged':''}">
        <td><strong>${s.student_number}</strong></td>
        <td>${s.first_name} ${s.last_name}</td>
        <td>${s.program_name}</td>
        <td>Y${s.year_of_study} S${s.semester}</td>
        <td class="fs-sm text-muted">${s.email||'—'}</td>
        <td>${statusBadge(s.is_active==1?'active':'inactive')}</td>
        <td>${s.is_flagged==1?'<span class="badge badge-flagged"><i class="fas fa-flag"></i> Flagged</span>':'—'}</td>
      </tr>`).join('')
    : '<tr><td colspan="7" class="text-center text-muted" style="padding:2rem">No students found.</td></tr>';
  renderPagination('pagination', res.data.pagination, load);
}
['searchInput','programFilter','flagFilter'].forEach(id=>{
  const method = id==='searchInput'?'input':'change';
  document.getElementById(id).addEventListener(method, ()=>{ clearTimeout(window._st); window._st=setTimeout(()=>load(1),350); });
});
load();
</script>
</body></html>
