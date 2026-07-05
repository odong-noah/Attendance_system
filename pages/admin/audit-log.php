<?php
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::startSession();
Auth::requireAdmin();
$activePage = 'audit';
$pageTitle  = 'Audit Log';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Audit Log — IT Attendance System</title>
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
    <div><h2><i class="fas fa-history"></i> System Audit Log</h2>
      <div class="breadcrumb">Complete trail of all security-relevant actions in the system.</div></div>
  </div>
  <div class="card">
    <div class="card-header">
      <input type="text" class="form-control" id="actionFilter" placeholder="Filter by action (e.g. LOGIN, CREATE_STUDENT)" style="width:320px">
    </div>
    <div class="card-body" style="padding:0">
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Action</th><th>Entity</th><th>Performed By</th><th>Role</th><th>IP</th><th>When</th></tr></thead>
          <tbody id="logsBody"><tr><td colspan="6" class="text-center text-muted" style="padding:2rem">Loading...</td></tr></tbody>
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
  page=page||1;
  const res = await API.get('/api/audit.php', { page, action: document.getElementById('actionFilter').value });
  if (!res.success){ Toast.error(res.message); return; }
  document.getElementById('logsBody').innerHTML = res.data.logs.length
    ? res.data.logs.map(l=>`<tr>
        <td><span class="badge badge-lecturer">${l.action.replace(/_/g,' ')}</span></td>
        <td class="fs-sm text-muted">${l.entity_type||'—'}${l.entity_id?' #'+l.entity_id:''}</td>
        <td>${l.performed_by||'System'}</td>
        <td>${l.role?'<span class="badge '+(l.role==='super_admin'?'badge-admin':'badge-lecturer')+'">'+l.role+'</span>':'—'}</td>
        <td class="fs-sm text-muted">${l.ip_address||'—'}</td>
        <td class="fs-sm text-muted">${fmtDateTime(l.created_at)}</td>
      </tr>`).join('')
    : '<tr><td colspan="6" class="text-center text-muted" style="padding:2rem">No log entries found.</td></tr>';
  renderPagination('pagination', res.data.pagination, load);
}
document.getElementById('actionFilter').addEventListener('input',()=>{ clearTimeout(window._at); window._at=setTimeout(()=>load(1),350); });
load();
</script>
</body></html>
