/* ============================================================
   app.js — IT Attendance System  (PHP 7.1+ / ES2017+)
   ============================================================ */
'use strict';

// ── Base path resolution ──────────────────────────────────────
// PHP injects window.BASE_PATH via every page's inline <script>.
// If it isn't set yet, auto-detect it from the URL.
(function () {
  if (window.BASE_PATH !== undefined) return;
  var p = window.location.pathname;
  var cut = p.search(/\/(pages|api)(\/|$)/);
  window.BASE_PATH = cut !== -1 ? p.slice(0, cut) : '';
})();

// ── API client ────────────────────────────────────────────────
// Automatically prepends BASE_PATH to any root-relative /api/ URL.
var API = {
  _url: function (url) {
    if (url.charAt(0) === '/' && url.indexOf('/api/') !== -1) {
      return (window.BASE_PATH || '') + url;
    }
    return url;
  },
  request: async function (url, opts) {
    opts = opts || {};
    var resolved = API._url(url);
    var headers  = Object.assign(
      { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      opts.headers || {}
    );
    if (opts.body && typeof opts.body === 'object') {
      opts.body = JSON.stringify(opts.body);
    }
    var res;
    try {
      res = await fetch(resolved, Object.assign({}, opts, { headers: headers }));
    } catch (e) {
      Toast.error('Network error — is the server running?');
      return { success: false, message: 'Network error.' };
    }
    if (res.status === 401) {
      window.location.href = (window.BASE_PATH || '') + '/index.php?redirect=1';
      return { success: false, message: 'Session expired.' };
    }
    var data;
    try { data = await res.json(); }
    catch (e) { data = { success: false, message: 'Invalid server response (HTTP ' + res.status + ').' }; }
    return data;
  },
  get: function (url, params) {
    var q = params ? new URLSearchParams(params).toString() : '';
    return API.request(q ? url + '?' + q : url);
  },
  post:   function (url, body) { return API.request(url, { method: 'POST',   body: body || {} }); },
  put:    function (url, body) { return API.request(url, { method: 'PUT',    body: body || {} }); },
  delete: function (url, params) {
    var q = params ? new URLSearchParams(params).toString() : '';
    return API.request(q ? url + '?' + q : url, { method: 'DELETE' });
  }
};

// ── Toast notifications ───────────────────────────────────────
var Toast = (function () {
  var container = null;

  function getContainer() {
    if (!container) {
      container = document.createElement('div');
      container.style.cssText = [
        'position:fixed', 'bottom:24px', 'right:24px',
        'z-index:9999', 'display:flex', 'flex-direction:column', 'gap:10px',
        'pointer-events:none'
      ].join(';');
      document.body.appendChild(container);
    }
    return container;
  }

  var icons = {
    success: '✔',
    error:   '✖',
    warning: '⚠',
    info:    'ℹ'
  };
  var colors = {
    success: { bg: '#16a34a', border: '#15803d' },
    error:   { bg: '#dc2626', border: '#b91c1c' },
    warning: { bg: '#d97706', border: '#b45309' },
    info:    { bg: '#2563eb', border: '#1d4ed8' }
  };

  function show(msg, type, duration) {
    type     = type     || 'info';
    duration = duration || 3500;
    var c  = colors[type] || colors.info;
    var el = document.createElement('div');
    el.style.cssText = [
      'background:' + c.bg,
      'color:#fff',
      'border-left:4px solid ' + c.border,
      'padding:12px 18px',
      'border-radius:8px',
      'font-size:14px',
      'font-family:Inter,sans-serif',
      'font-weight:500',
      'box-shadow:0 4px 20px rgba(0,0,0,.18)',
      'display:flex',
      'align-items:center',
      'gap:10px',
      'max-width:340px',
      'pointer-events:auto',
      'cursor:pointer',
      'animation:toastIn .3s ease',
      'transition:opacity .3s,transform .3s'
    ].join(';');
    el.innerHTML = '<span style="font-size:16px">' + (icons[type] || 'ℹ') + '</span><span>' + msg + '</span>';
    el.addEventListener('click', function () { dismiss(el); });
    getContainer().appendChild(el);

    var timer = setTimeout(function () { dismiss(el); }, duration);
    el._timer = timer;
  }

  function dismiss(el) {
    clearTimeout(el._timer);
    el.style.opacity  = '0';
    el.style.transform = 'translateX(60px)';
    setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 300);
  }

  // Inject keyframes once
  (function () {
    if (document.getElementById('toast-style')) return;
    var s = document.createElement('style');
    s.id = 'toast-style';
    s.textContent = '@keyframes toastIn{from{opacity:0;transform:translateX(60px)}to{opacity:1;transform:translateX(0)}}';
    document.head.appendChild(s);
  })();

  return {
    success: function (m, d) { show(m, 'success', d); },
    error:   function (m, d) { show(m, 'error',   d); },
    warning: function (m, d) { show(m, 'warning', d); },
    info:    function (m, d) { show(m, 'info',    d); }
  };
})();

// ── Modal ─────────────────────────────────────────────────────
var Modal = {
  open: function (id) {
    var el = document.getElementById(id);
    if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
  },
  close: function (id) {
    var el = document.getElementById(id);
    if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
  },
  closeAll: function () {
    document.querySelectorAll('.modal-overlay.open').forEach(function (m) { m.classList.remove('open'); });
    document.body.style.overflow = '';
  }
};

document.addEventListener('click', function (e) {
  if (e.target.classList.contains('modal-overlay') || e.target.classList.contains('modal-close')) {
    Modal.closeAll();
  }
});

// ── Sidebar mobile toggle ─────────────────────────────────────
(function () {
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('#sidebarToggle');
    var sb = document.querySelector('.sidebar');
    
    if (btn) {
      // Toggle the sidebar
      if (sb) sb.classList.toggle('open');
    } else if (sb && sb.classList.contains('open')) {
      // Close sidebar if clicking outside of it on mobile
      if (!sb.contains(e.target)) {
        sb.classList.remove('open');
      }
    }
  });
})();

// ── Confirm dialog ────────────────────────────────────────────
function confirmAction(msg, callback) {
  var overlay = document.createElement('div');
  overlay.className = 'modal-overlay open';
  overlay.innerHTML = [
    '<div class="modal-box" style="max-width:420px">',
    '<div class="modal-header"><h4>⚠ Confirm Action</h4></div>',
    '<div class="modal-body"><p>' + msg + '</p></div>',
    '<div class="modal-footer">',
    '<button class="btn btn-outline" id="_cfmNo">Cancel</button>',
    '<button class="btn btn-danger"  id="_cfmYes">Confirm</button>',
    '</div></div>'
  ].join('');
  document.body.appendChild(overlay);
  document.getElementById('_cfmNo').addEventListener('click',  function () { overlay.remove(); });
  document.getElementById('_cfmYes').addEventListener('click', function () { overlay.remove(); callback(); });
}

// ── Global search ─────────────────────────────────────────────
(function () {
  var input    = document.getElementById('globalSearch');
  var dropdown = document.getElementById('searchDropdown');
  if (!input || !dropdown) return;

  var timer;
  input.addEventListener('input', function () {
    clearTimeout(timer);
    var q = input.value.trim();
    if (q.length < 2) { dropdown.classList.remove('show'); return; }
    timer = setTimeout(function () { doSearch(q); }, 280);
  });
  input.addEventListener('focus', function () {
    if (input.value.trim().length >= 2) dropdown.classList.add('show');
  });
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.search-global')) dropdown.classList.remove('show');
  });

  async function doSearch(q) {
    var res = await API.get('/api/search.php', { q: q });
    if (!res.success) return;
    var d = res.data;
    var html = '';
    var hl = function (t) {
      return t.replace(new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi'), '<mark>$1</mark>');
    };
    var base = window.BASE_PATH || '';
    var role = window.USER_ROLE || 'lecturer';
    var pagesBase = base + '/pages/' + (role === 'super_admin' ? 'admin' : 'lecturer');

    if (d.students && d.students.length) {
      html += '<div class="search-result-section"><div class="search-result-section-title">Students</div>';
      d.students.forEach(function (s) {
        html += '<a class="search-result-item" href="' + pagesBase + '/students.php">' +
          '<div class="icon icon-student"><i class="fas fa-user"></i></div>' +
          '<div class="text"><strong>' + hl(s.name) + '</strong>' +
          '<span>' + hl(s.student_number) + ' &bull; ' + s.program + '</span></div>' +
          (s.is_flagged ? '<i class="fas fa-flag text-danger" style="margin-left:auto"></i>' : '') +
          '</a>';
      });
      html += '</div>';
    }
    if (d.courses && d.courses.length) {
      html += '<div class="search-result-section"><div class="search-result-section-title">Courses</div>';
      d.courses.forEach(function (c) {
        html += '<a class="search-result-item" href="#">' +
          '<div class="icon icon-course"><i class="fas fa-book"></i></div>' +
          '<div class="text"><strong>' + hl(c.code) + ' — ' + hl(c.name) + '</strong>' +
          '<span>' + c.program_name + '</span></div></a>';
      });
      html += '</div>';
    }
    if (d.sessions && d.sessions.length) {
      html += '<div class="search-result-section"><div class="search-result-section-title">Sessions</div>';
      d.sessions.forEach(function (s) {
        html += '<a class="search-result-item" href="' + pagesBase + '/sessions.php">' +
          '<div class="icon icon-session"><i class="fas fa-calendar"></i></div>' +
          '<div class="text"><strong>' + hl(s.topic || s.course_name) + '</strong>' +
          '<span>' + s.code + ' &bull; ' + s.session_date + '</span></div></a>';
      });
      html += '</div>';
    }
    if (!html) html = '<div class="empty-state" style="padding:1.5rem"><p>No results for "' + q + '"</p></div>';
    dropdown.innerHTML = html;
    dropdown.classList.add('show');
  }
})();

// ── Pagination ────────────────────────────────────────────────
function renderPagination(containerId, pg, onPage) {
  var el = document.getElementById(containerId);
  if (!el || !pg) return;
  var cur = pg.current_page, tot = pg.total_pages;
  var html = '<button class="page-btn" ' + (cur <= 1 ? 'disabled' : '') +
    ' data-page="' + (cur - 1) + '">&#8249;</button>';
  for (var p = Math.max(1, cur - 2); p <= Math.min(tot, cur + 2); p++) {
    html += '<button class="page-btn ' + (p === cur ? 'active' : '') + '" data-page="' + p + '">' + p + '</button>';
  }
  html += '<button class="page-btn" ' + (cur >= tot ? 'disabled' : '') +
    ' data-page="' + (cur + 1) + '">&#8250;</button>' +
    '<span class="text-muted fs-sm" style="margin-left:.5rem">Page ' + cur + ' of ' + tot + '</span>';
  el.innerHTML = html;
  el.querySelectorAll('.page-btn:not([disabled])').forEach(function (btn) {
    btn.addEventListener('click', function () { onPage(+btn.dataset.page); });
  });
}

// ── Helpers ───────────────────────────────────────────────────
function fmtDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}
function fmtDateTime(d) {
  if (!d) return '—';
  return new Date(d).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
function statusBadge(s) {
  var map = {
    present:    ['badge-present',  '✔ Present'],
    absent:     ['badge-absent',   '✖ Absent'],
    late:       ['badge-late',     '⏱ Late'],
    excused:    ['badge-excused',  '📄 Excused'],
    active:     ['badge-active',   '● Active'],
    inactive:   ['badge-inactive', '● Inactive'],
    not_marked: ['badge-inactive', '— Not Marked'],
  };
  var v = map[s] || ['badge-inactive', s];
  return '<span class="badge ' + v[0] + '">' + v[1] + '</span>';
}
function pctClass(p) { return p >= 75 ? 'pct-high' : p >= 50 ? 'pct-medium' : 'pct-low'; }
function buildProgressBar(pct) {
  var c = pct >= 75 ? 'var(--success)' : pct >= 50 ? 'var(--warning)' : 'var(--danger)';
  return '<div class="progress-bar-wrap" style="min-width:80px">' +
    '<div class="progress-bar-fill" style="width:' + pct + '%;background:' + c + '"></div></div>' +
    '<span class="' + pctClass(pct) + ' fs-sm">' + pct + '%</span>';
}
function exportTableCSV(tableId, filename) {
  var table = document.getElementById(tableId);
  if (!table) return;
  var rows = Array.from(table.querySelectorAll('tr'));
  var csv  = rows.map(function (r) {
    return Array.from(r.querySelectorAll('th,td'))
      .map(function (c) { return '"' + c.innerText.replace(/"/g, '""') + '"'; }).join(',');
  }).join('\n');
  var a = document.createElement('a');
  a.href     = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
  a.download = filename || 'export.csv';
  a.click(); a.remove();
}

// ── Idle timeout warning ──────────────────────────────────────
(function () {
  var timer;
  var WARN = 50 * 60 * 1000;
  function reset() {
    clearTimeout(timer);
    timer = setTimeout(function () {
      Toast.warning('Your session will expire in 10 minutes due to inactivity.');
    }, WARN);
  }
  ['mousemove','keydown','click','scroll'].forEach(function (e) {
    document.addEventListener(e, reset, { passive: true });
  });
  reset();
})();