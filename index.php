<?php
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

Auth::startSession();

// Already logged in — send to the right dashboard
if (!empty($_SESSION['user_id'])) {
    $dest = BASE_PATH . ($_SESSION['role'] === 'super_admin'
        ? '/pages/admin/dashboard.php'
        : '/pages/lecturer/dashboard.php');
    header("Location: $dest");
    exit;
}

$timeout   = isset($_GET['timeout']);
$redirect  = isset($_GET['redirect']);
$loggedout = isset($_GET['loggedout']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — IT Attendance System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/app.css">
</head>
<body>

<div class="login-page">
  <!-- Hero -->
  <div class="login-hero">
    <div class="hero-icon"><i class="fas fa-user-graduate"></i></div>
    <h1>IT Faculty Attendance System</h1>
    <p>A secure, centralized platform for managing student attendance across Information Technology, Software Engineering, Computer Science, and Information Systems programs.</p>
    <div class="d-flex gap-2 mt-3" style="opacity:.8">
      <div style="text-align:center"><i class="fas fa-shield-alt fa-lg"></i><div style="font-size:.7rem;margin-top:.3rem">Secure</div></div>
      <div style="width:1px;background:rgba(255,255,255,.2)"></div>
      <div style="text-align:center"><i class="fas fa-chart-line fa-lg"></i><div style="font-size:.7rem;margin-top:.3rem">Analytics</div></div>
      <div style="width:1px;background:rgba(255,255,255,.2)"></div>
      <div style="text-align:center"><i class="fas fa-bolt fa-lg"></i><div style="font-size:.7rem;margin-top:.3rem">Real-time</div></div>
    </div>
  </div>

  <!-- Login panel -->
  <div class="login-panel">
    <div class="login-card">
      <h2>Welcome back</h2>
      <p class="subtitle">Sign in to access your dashboard</p>

      <div id="alertBox">
        <?php if ($timeout): ?>
          <div class="alert alert-warning mb-2"><i class="fas fa-clock"></i> Your session expired. Please log in again.</div>
        <?php elseif ($redirect): ?>
          <div class="alert alert-info mb-2"><i class="fas fa-info-circle"></i> Please log in to continue.</div>
        <?php elseif ($loggedout): ?>
          <div class="alert alert-success mb-2"><i class="fas fa-check-circle"></i> You have been logged out successfully.</div>
        <?php endif; ?>
      </div>

      <form id="loginForm" novalidate autocomplete="off">
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" class="form-control" id="email" placeholder="you@university.ac.ug" autocomplete="off">
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div style="position:relative">
            <input type="password" class="form-control" id="password" placeholder="Enter your password" autocomplete="new-password">
            <button type="button" id="togglePw" style="position:absolute;right:.8rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>
        <div class="d-flex align-center" style="justify-content:space-between;margin-bottom:1.5rem">
          <label class="d-flex align-center gap-1" style="font-size:.82rem;cursor:pointer">
            <input type="checkbox" id="remember"> Remember me
          </label>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:.75rem" id="loginBtn">
          <span id="loginBtnText"><i class="fas fa-sign-in-alt"></i> Sign In</span>
        </button>
      </form>

      <p class="text-muted fs-sm mt-3" style="text-align:center">
        Forgot your password? Contact the IT Faculty administrator.
      </p>
    </div>
  </div>
</div>

<script>
window.BASE_PATH = <?= json_encode(BASE_PATH) ?>;

// Comprehensive function to clear all inputs
function clearLoginForm() {
  const form = document.getElementById('loginForm');
  const email = document.getElementById('email');
  const pass = document.getElementById('password');
  
  if (form) form.reset();
  if (email) email.value = '';
  if (pass) pass.value = '';
}

document.getElementById('togglePw').addEventListener('click', function () {
  const pw = document.getElementById('password');
  const ic = this.querySelector('i');
  pw.type = pw.type === 'password' ? 'text' : 'password';
  ic.className = pw.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
});

document.getElementById('loginForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  const btn     = document.getElementById('loginBtn');
  const btnText = document.getElementById('loginBtnText');
  const alertBox = document.getElementById('alertBox');
  alertBox.innerHTML = '';

  const email    = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value;

  if (!email || !password) {
    alertBox.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Please fill in all fields.</div>';
    return;
  }

  btn.disabled = true;
  btnText.innerHTML = '<span class="loader"></span> Signing in...';

  try {
    const res  = await fetch(window.BASE_PATH + '/api/login.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ email, password })
    });

    let data;
    try { data = await res.json(); }
    catch { data = { success: false, message: 'Server error (HTTP ' + res.status + '). Check PHP error log.' }; }

    if (data.success) {
      btnText.innerHTML = '<i class="fas fa-check"></i> Success! Redirecting...';
      
      // CLEAR FIELDS ON SUCCESSFUL LOGIN
      clearLoginForm();
      
      window.location.replace(data.data.redirect);
    } else {
      alertBox.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ${data.message}</div>`;
      btn.disabled = false;
      btnText.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
    }
  } catch (err) {
    alertBox.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Network error — is the server running?</div>';
    btn.disabled = false;
    btnText.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
  }
});

/**
 * ENSURE FIELDS REMAIN EMPTY ON LOGOUT / PAGE LOAD
 * 'pageshow' captures navigating back, forward, and initial loads.
 * We use a small timeout to clear fields after the browser's 
 * password manager attempts to auto-fill them.
 */
window.addEventListener('pageshow', function() {
  clearLoginForm();
  setTimeout(clearLoginForm, 100);
});
</script>
</body>
</html>