<?php
// pages/partials/sidebar.php
// Requires: $activePage variable, Auth class loaded, BASE_PATH defined
$u = Auth::user();
$isAdmin  = Auth::isAdmin();
$initials = strtoupper(
    substr($u['full_name'], 0, 1) .
    substr(strrchr($u['full_name'], ' ') ?: '', 1, 1)
);
$adminBase    = BASE_PATH . '/pages/admin';
$lecturerBase = BASE_PATH . '/pages/lecturer';
$navBase      = $isAdmin ? $adminBase : $lecturerBase;
?>
<script>
// Inject the server-computed base path so app.js can build correct API URLs
window.BASE_PATH = <?= json_encode(BASE_PATH) ?>;
</script>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <a href="<?= $navBase ?>/dashboard.php" class="sidebar-logo">
      <div class="logo-icon"><i class="fas fa-user-graduate"></i></div>
      <div class="sidebar-logo-text">
        <h3>IT Attendance</h3>
        <span><?= $isAdmin ? 'Faculty Dean Portal' : 'Lecturer Portal' ?></span>
      </div>
    </a>
  </div>

  <nav class="sidebar-nav">
    <?php if ($isAdmin): ?>
      <div class="nav-section-label">Overview</div>
      <a href="<?= $adminBase ?>/dashboard.php"  class="nav-link <?= $activePage==='dashboard'?'active':'' ?>"><i class="fas fa-chart-pie"></i> Dashboard</a>

      <div class="nav-section-label">Management</div>
      <a href="<?= $adminBase ?>/lecturers.php"  class="nav-link <?= $activePage==='lecturers'?'active':'' ?>"><i class="fas fa-chalkboard-teacher"></i> Lecturers</a>
      <a href="<?= $adminBase ?>/programs.php"   class="nav-link <?= $activePage==='programs'?'active':'' ?>"><i class="fas fa-sitemap"></i> Programs &amp; Courses</a>
      <a href="<?= $adminBase ?>/students.php"   class="nav-link <?= $activePage==='students'?'active':'' ?>"><i class="fas fa-user-graduate"></i> All Students</a>

      <div class="nav-section-label">Insights</div>
      <a href="<?= $adminBase ?>/reports.php"    class="nav-link <?= $activePage==='reports'?'active':'' ?>"><i class="fas fa-file-pdf"></i> Reports</a>
      <a href="<?= $adminBase ?>/flagged.php"    class="nav-link <?= $activePage==='flagged'?'active':'' ?>"><i class="fas fa-flag"></i> Flagged Students</a>
      <a href="<?= $adminBase ?>/audit-log.php"  class="nav-link <?= $activePage==='audit'?'active':'' ?>"><i class="fas fa-history"></i> Audit Log</a>

    <?php else: ?>
      <div class="nav-section-label">Overview</div>
      <a href="<?= $lecturerBase ?>/dashboard.php"  class="nav-link <?= $activePage==='dashboard'?'active':'' ?>"><i class="fas fa-chart-pie"></i> Dashboard</a>

      <div class="nav-section-label">My Teaching</div>
      <a href="<?= $lecturerBase ?>/courses.php"    class="nav-link <?= $activePage==='courses'?'active':'' ?>"><i class="fas fa-book"></i> My Course Units</a>
      <a href="<?= $lecturerBase ?>/attendance.php" class="nav-link <?= $activePage==='attendance'?'active':'' ?>"><i class="fas fa-clipboard-check"></i> Take Attendance</a>
      <a href="<?= $lecturerBase ?>/sessions.php"   class="nav-link <?= $activePage==='sessions'?'active':'' ?>"><i class="fas fa-calendar-alt"></i> Lecture Sessions</a>

      <div class="nav-section-label">Students</div>
      <a href="<?= $lecturerBase ?>/students.php"   class="nav-link <?= $activePage==='students'?'active':'' ?>"><i class="fas fa-user-graduate"></i> Student Management</a>
      <a href="<?= $lecturerBase ?>/flagged.php"    class="nav-link <?= $activePage==='flagged'?'active':'' ?>"><i class="fas fa-flag"></i> Flagged Students</a>

      <div class="nav-section-label">Insights</div>
      <a href="<?= $lecturerBase ?>/reports.php"    class="nav-link <?= $activePage==='reports'?'active':'' ?>"><i class="fas fa-file-pdf"></i> Reports</a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?= htmlspecialchars($initials ?: 'U') ?></div>
      <div style="flex:1;min-width:0">
        <div class="user-name truncate"><?= htmlspecialchars($u['full_name']) ?></div>
        <div class="user-role"><?= $isAdmin ? 'Super Admin' : 'Lecturer' ?></div>
      </div>
      <a href="<?= BASE_PATH ?>/api/logout.php" title="Logout" style="color:rgba(255,255,255,.6)">
        <i class="fas fa-sign-out-alt"></i>
      </a>
    </div>
  </div>
</aside>
