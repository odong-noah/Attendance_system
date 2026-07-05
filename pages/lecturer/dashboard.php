<?php
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::startSession();
Auth::requireLecturer();
$activePage = 'dashboard';
$pageTitle  = 'My Dashboard';
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
<!-- FIX: Move Chart.js to head to ensure it is defined before the script runs -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>
<body>
<script>window.BASE_PATH='<?= addslashes(BASE_PATH) ?>';window.USER_ROLE='lecturer';</script>
<div class="app-wrapper">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <div class="main-content">
    <?php include __DIR__ . '/../partials/topbar.php'; ?>
    <div class="page-content">
      <div class="page-header">
        <div><h2>My Teaching Overview</h2>
          <div class="breadcrumb">Welcome back! Here's a summary of your courses and attendance.</div></div>
        <a href="attendance.php" class="btn btn-primary"><i class="fas fa-clipboard-check"></i> Take Attendance</a>
      </div>

      <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-book"></i></div>
          <div><div class="stat-value" id="statCourses">—</div><div class="stat-label">My Course Units</div></div></div>
        <div class="stat-card"><div class="stat-icon cyan"><i class="fas fa-user-graduate"></i></div>
          <div><div class="stat-value" id="statStudents">—</div><div class="stat-label">Program Students</div></div></div>
        <div class="stat-card"><div class="stat-icon green"><i class="fas fa-calendar-check"></i></div>
          <div><div class="stat-value" id="statSessions">—</div><div class="stat-label">Sessions Held</div></div></div>
        <div class="stat-card"><div class="stat-icon red"><i class="fas fa-flag"></i></div>
          <div><div class="stat-value" id="statFlagged">—</div><div class="stat-label">Flagged Students</div></div></div>
        <div class="stat-card"><div class="stat-icon yellow"><i class="fas fa-percentage"></i></div>
          <div><div class="stat-value" id="statPct">—</div><div class="stat-label">My Avg. Attendance</div></div></div>
      </div>

      <div class="row g-3">
        <div class="col-lg-7">
          <div class="card">
            <div class="card-header"><h3><i class="fas fa-chart-bar"></i> Attendance by Course Unit</h3></div>
            <div class="card-body">
              <div class="chart-wrap"><canvas id="courseChart"></canvas></div>
              <div id="courseEmpty" class="empty-state" style="display:none">
                <i class="fas fa-chart-bar icon"></i><h4>No attendance data yet</h4>
                <p>Start recording sessions to see charts here.</p>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-5">
          <div class="card">
            <div class="card-header"><h3><i class="fas fa-chart-line"></i> Attendance Trend</h3></div>
            <div class="card-body">
              <div class="chart-wrap"><canvas id="trendChart"></canvas></div>
              <div id="trendEmpty" class="empty-state" style="display:none">
                <i class="fas fa-chart-line icon"></i><h4>No trend data yet</h4>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-lg-6">
          <div class="card">
            <div class="card-header">
              <h3><i class="fas fa-calendar-alt"></i> Recent Sessions</h3>
              <a href="sessions.php" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="card-body" style="padding:0">
              <table class="data-table" id="sessionsTable">
                <thead><tr><th>Course</th><th>Date</th><th>Present/Total</th></tr></thead>
                <tbody><tr><td colspan="3" class="text-muted text-center" style="padding:1.5rem">Loading...</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="card">
            <div class="card-header">
              <h3><i class="fas fa-flag text-danger"></i> Flagged Students</h3>
              <a href="flagged.php" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="card-body" style="padding:0">
              <table class="data-table" id="flaggedTable">
                <thead><tr><th>Student</th><th>Absences</th></tr></thead>
                <tbody><tr><td colspan="2" class="text-muted text-center" style="padding:1.5rem">Loading...</td></tr></tbody>
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
// Helper to safely format dates
function fmt(dateString) {
    if (!dateString) return '—';
    const d = new Date(dateString);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
}

async function loadDashboard() {
    try {
        console.log("Fetching lecturer dashboard data...");
        // Use standard fetch if API.get is unreliable
        const response = await fetch(window.BASE_PATH + '/api/dashboard.php');
        const result = await response.json();
        
        if (!result.success) {
            console.error('API Error:', result.message);
            return;
        }

        const d = result.data;

        // 1. Update Stats Cards
        const setStat = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val ?? 0;
        };

        setStat('statCourses', d.stats?.my_courses);
        setStat('statStudents', d.stats?.program_students);
        setStat('statSessions', d.stats?.my_sessions);
        setStat('statFlagged', d.stats?.flagged_in_program);
        if (document.getElementById('statPct')) {
            document.getElementById('statPct').textContent = (d.stats?.my_attendance_pct ?? 0) + '%';
        }

        // 2. Charts - Guarded by typeof Chart check
        if (typeof Chart !== 'undefined') {
            // Course Bar Chart
            const hasCourseData = d.courses && d.courses.some(c => +c.present > 0 || +c.absent > 0);
            if (hasCourseData) {
                new Chart(document.getElementById('courseChart'), {
                    type: 'bar',
                    data: {
                        labels: d.courses.map(c => c.code),
                        datasets: [
                            { label:'Present', data:d.courses.map(c=>+c.present||0), backgroundColor:'#16a34a', borderRadius:4 },
                            { label:'Absent',  data:d.courses.map(c=>+c.absent ||0), backgroundColor:'#dc2626', borderRadius:4 },
                        ]
                    },
                    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom'}} }
                });
            } else {
                document.getElementById('courseChart').style.display = 'none';
                document.getElementById('courseEmpty').style.display = '';
            }

            // Trend Line Chart
            if (d.daily_trend && d.daily_trend.length > 0) {
                new Chart(document.getElementById('trendChart'), {
                    type: 'line',
                    data: {
                        labels: d.daily_trend.map(t => formatDate(t.day)), // Uses local formatDate helper
                        datasets: [
                            { label:'Present', data:d.daily_trend.map(t=>+t.present||0), borderColor:'#16a34a', tension:0.3 },
                            { label:'Absent',  data:d.daily_trend.map(t=>+t.absent ||0), borderColor:'#dc2626', tension:0.3 },
                        ]
                    },
                    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom'}} }
                });
            } else {
                document.getElementById('trendChart').style.display = 'none';
                document.getElementById('trendEmpty').style.display = '';
            }
        } else {
            console.warn("Chart.js not loaded. Skipping charts.");
        }

        // 3. Tables
        const sessionsBody = document.querySelector('#sessionsTable tbody');
        if (sessionsBody) {
            sessionsBody.innerHTML = d.recent_sessions && d.recent_sessions.length
                ? d.recent_sessions.map(s => `
                    <tr>
                        <td><strong>${s.code}</strong><br><small class="text-muted">${s.topic || s.course_name}</small></td>
                        <td>${fmt(s.session_date)}</td>
                        <td><span class="badge bg-success">${s.present_count}/${s.total_marked}</span></td>
                    </tr>`).join('')
                : '<tr><td colspan="3" class="text-center p-4">No sessions recorded yet.</td></tr>';
        }

        const flaggedBody = document.querySelector('#flaggedTable tbody');
        if (flaggedBody) {
            flaggedBody.innerHTML = d.flagged && d.flagged.length
                ? d.flagged.map(f => `
                    <tr>
                        <td><strong>${f.name}</strong></td>
                        <td><span class="badge bg-danger">${f.absences} Absences</span></td>
                    </tr>`).join('')
                : '<tr><td colspan="2" class="text-center p-4">No flagged students 🎉</td></tr>';
        }

    } catch (err) {
        console.error("Lecturer Dashboard Load Failed:", err);
    }
}

// Internal helper for trend labels
function formatDate(day) {
    const d = new Date(day);
    return d.toLocaleDateString('en-GB', {day:'2-digit', month:'short'});
}

// Use window.onload to ensure Chart.js in the head is fully ready
window.addEventListener('load', loadDashboard);
</script>
</body>
</html>