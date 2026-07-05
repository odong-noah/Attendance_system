<?php
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::startSession();
Auth::requireAdmin();
$activePage = 'dashboard';
$pageTitle  = 'Dean Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — IT Faculty Attendance System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/app.css">
<!-- FIX: Move Chart.js to head to ensure it loads before the body scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>
<body>
<script>window.BASE_PATH='<?= addslashes(BASE_PATH) ?>';window.USER_ROLE='super_admin';</script>
<div class="app-wrapper">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <div class="main-content">
    <?php include __DIR__ . '/../partials/topbar.php'; ?>
    <div class="page-content">
      <div class="page-header">
        <div><h2>Faculty Overview</h2>
          <div class="breadcrumb">Welcome back, Dean. Here's what's happening across all programs.</div></div>
        <a href="reports.php" class="btn btn-primary"><i class="fas fa-file-pdf"></i> Generate Report</a>
      </div>

      <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-sitemap"></i></div>
          <div><div class="stat-value" id="statPrograms">—</div><div class="stat-label">Active Programs</div></div></div>
        <div class="stat-card"><div class="stat-icon purple"><i class="fas fa-chalkboard-teacher"></i></div>
          <div><div class="stat-value" id="statLecturers">—</div><div class="stat-label">Lecturers</div></div></div>
        <div class="stat-card"><div class="stat-icon cyan"><i class="fas fa-user-graduate"></i></div>
          <div><div class="stat-value" id="statStudents">—</div><div class="stat-label">Total Students</div></div></div>
        <div class="stat-card"><div class="stat-icon green"><i class="fas fa-calendar-check"></i></div>
          <div><div class="stat-value" id="statSessions">—</div><div class="stat-label">Total Sessions</div></div></div>
        <div class="stat-card"><div class="stat-icon red"><i class="fas fa-flag"></i></div>
          <div><div class="stat-value" id="statFlagged">—</div><div class="stat-label">Flagged Students</div></div></div>
        <div class="stat-card"><div class="stat-icon yellow"><i class="fas fa-percentage"></i></div>
          <div><div class="stat-value" id="statPct">—</div><div class="stat-label">Overall Attendance</div></div></div>
      </div>

      <div class="row g-3">
        <div class="col-lg-7">
          <div class="card">
            <div class="card-header"><h3><i class="fas fa-chart-bar"></i> Attendance by Program</h3></div>
            <div class="card-body"><div class="chart-wrap"><canvas id="programChart"></canvas></div>
              <div id="programEmpty" class="empty-state" style="display:none"><i class="fas fa-chart-bar icon"></i><h4>No attendance data yet</h4><p>Data will appear after lecturers record sessions.</p></div>
            </div>
          </div>
        </div>
        <div class="col-lg-5">
          <div class="card">
            <div class="card-header"><h3><i class="fas fa-chart-pie"></i> Attendance Distribution</h3></div>
            <div class="card-body"><div class="chart-wrap"><canvas id="distChart"></canvas></div>
              <div id="distEmpty" class="empty-state" style="display:none"><i class="fas fa-chart-pie icon"></i><h4>No data yet</h4></div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-lg-7">
          <div class="card">
            <div class="card-header"><h3><i class="fas fa-chart-line"></i> Attendance Trend</h3></div>
            <div class="card-body"><div class="chart-wrap"><canvas id="trendChart"></canvas></div>
              <div id="trendEmpty" class="empty-state" style="display:none"><i class="fas fa-chart-line icon"></i><h4>No session data yet</h4></div>
            </div>
          </div>
        </div>
        <div class="col-lg-5">
          <div class="card">
            <div class="card-header"><h3><i class="fas fa-flag text-danger"></i> Flagged Students</h3>
              <a href="flagged.php" class="btn btn-sm btn-outline">View All</a></div>
            <div class="card-body" style="padding:0">
              <table class="data-table" id="flaggedTable">
                <thead><tr><th>Student</th><th>Program</th><th>Absences</th></tr></thead>
                <tbody><tr><td colspan="3" class="text-muted text-center" style="padding:1.5rem">Loading...</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-12">
          <div class="card">
            <div class="card-header"><h3><i class="fas fa-history"></i> Recent Activity</h3>
              <a href="audit-log.php" class="btn btn-sm btn-outline">View Full Log</a></div>
            <div class="card-body" style="padding:0">
              <table class="data-table">
                <thead><tr><th>Action</th><th>Performed By</th><th>When</th></tr></thead>
                <tbody id="activityTable"><tr><td colspan="3" class="text-muted text-center" style="padding:1.5rem">Loading...</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/app.js"></script>
<script>
function fmt(dateString) {
    if (!dateString) return '—';
    const d = new Date(dateString);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}

async function loadDashboard() {
    try {
        console.log("Fetching dashboard data...");
        const response = await fetch(window.BASE_PATH + '/api/dashboard.php');
        const result = await response.json();
        
        if (!result.success) {
            console.error('API Error:', result.message);
            return;
        }

        const d = result.data;

        // 1. Update Stats Cards
        const statsMap = {
            'statPrograms': d.stats?.total_programs,
            'statLecturers': d.stats?.total_lecturers,
            'statStudents': d.stats?.total_students,
            'statSessions': d.stats?.total_sessions,
            'statFlagged': d.stats?.flagged_students
        };

        for (const [id, val] of Object.entries(statsMap)) {
            const el = document.getElementById(id);
            if (el) el.textContent = val ?? 0;
        }

        const pctEl = document.getElementById('statPct');
        if (pctEl) pctEl.textContent = (d.stats?.overall_attendance_pct ?? 0) + '%';

        // 2. Charts Logic - ENSURE CHART IS DEFINED
        if (typeof Chart === 'undefined') {
            console.error("Chart.js failed to load. Please check your internet connection.");
            return;
        }

        if (d.program_stats && d.program_stats.length > 0) {
            const hasData = d.program_stats.some(p => +p.present > 0 || +p.absent > 0);
            
            if (hasData) {
                new Chart(document.getElementById('programChart'), {
                    type: 'bar',
                    data: {
                        labels: d.program_stats.map(p => p.code || p.program),
                        datasets: [
                            { label: 'Present', data: d.program_stats.map(p => +p.present || 0), backgroundColor: '#16a34a', borderRadius: 4 },
                            { label: 'Absent',  data: d.program_stats.map(p => +p.absent || 0), backgroundColor: '#dc2626', borderRadius: 4 }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
                });

                const totalP = d.program_stats.reduce((sum, p) => sum + (+p.present || 0), 0);
                const totalA = d.program_stats.reduce((sum, p) => sum + (+p.absent || 0), 0);
                
                new Chart(document.getElementById('distChart'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Present', 'Absent'],
                        datasets: [{ data: [totalP, totalA], backgroundColor: ['#16a34a', '#dc2626'], borderWidth: 0 }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
                });
            } else {
                showEmpty('program');
                showEmpty('dist');
            }
        }

        if (d.daily_trend && d.daily_trend.length > 0) {
            new Chart(document.getElementById('trendChart'), {
                type: 'line',
                data: {
                    labels: d.daily_trend.map(t => t.day),
                    datasets: [
                        { label: 'Present', data: d.daily_trend.map(t => +t.present || 0), borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,0.1)', fill: true, tension: 0.4 },
                        { label: 'Absent',  data: d.daily_trend.map(t => +t.absent || 0), borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,0.1)', fill: true, tension: 0.4 }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
            });
        } else {
            showEmpty('trend');
        }

        // 3. Tables
        const flaggedBody = document.querySelector('#flaggedTable tbody');
        if (flaggedBody) {
            flaggedBody.innerHTML = (d.flagged && d.flagged.length > 0)
                ? d.flagged.map(f => `<tr><td><strong>${f.name}</strong><br><small class="text-muted">${f.student_number}</small></td><td>${f.program}</td><td><span class="badge bg-danger">${f.absences} Absences</span></td></tr>`).join('')
                : '<tr><td colspan="3" class="text-center text-muted p-4">No flagged students found.</td></tr>';
        }

        const activityBody = document.getElementById('activityTable');
        if (activityBody) {
            activityBody.innerHTML = (d.recent && d.recent.length > 0)
                ? d.recent.map(a => `<tr><td>${a.action.replace(/_/g, ' ')}</td><td>${a.performed_by}</td><td class="text-muted">${fmt(a.created_at)}</td></tr>`).join('')
                : '<tr><td colspan="3" class="text-center text-muted p-4">No recent activity.</td></tr>';
        }

    } catch (err) {
        console.error("Dashboard Load Failed:", err);
    }
}

function showEmpty(type) {
    const chart = document.getElementById(type + 'Chart');
    const empty = document.getElementById(type + 'Empty');
    if (chart) chart.style.display = 'none';
    if (empty) empty.style.display = 'block';
}

// FIX: Use window.onload to ensure all external scripts (Chart.js) are fully ready
window.addEventListener('load', loadDashboard);

</script>
</body>
</html>