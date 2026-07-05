<?php
// ============================================================
// includes/auth.php — Authentication & Session Management
// ============================================================

defined('ATTENDANCE_SYS') or die('Direct access not permitted.');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class Auth
{
    // ── Start session (idempotent) ────────────────────────────
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // ── Login ─────────────────────────────────────────────────
    public static function login(string $email, string $password): array
    {
        self::startSession();
        $email = strtolower(trim($email));

        $user = DB::run(
            "SELECT id, employee_id, first_name, last_name, email,
                    password_hash, role, program_id, is_active,
                    login_attempts, locked_until
             FROM users WHERE email = ? LIMIT 1",
            [$email]
        )->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        // Brute-force lockout
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $mins = (int)ceil((strtotime($user['locked_until']) - time()) / 60);
            return ['success' => false, 'message' => "Account locked. Try again in {$mins} minute(s)."];
        }

        if (!$user['is_active']) {
            return ['success' => false, 'message' => 'This account has been deactivated.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            $attempts = (int)$user['login_attempts'] + 1;
            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                $until = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
                DB::run("UPDATE users SET login_attempts=?, locked_until=? WHERE id=?",
                        [$attempts, $until, $user['id']]);
                return ['success' => false, 'message' => 'Too many failed attempts. Account locked for 15 minutes.'];
            }
            DB::run("UPDATE users SET login_attempts=? WHERE id=?", [$attempts, $user['id']]);
            $left = MAX_LOGIN_ATTEMPTS - $attempts;
            return ['success' => false, 'message' => "Invalid email or password. {$left} attempt(s) remaining."];
        }

        // ── Success ───────────────────────────────────────────
        DB::run("UPDATE users SET login_attempts=0, locked_until=NULL, last_login=NOW() WHERE id=?",
                [$user['id']]);

        // Regenerate to prevent session fixation, then write all
        // session data. We do NOT call session_write_close() here —
        // that would close the session handle before the response
        // cookie is sent, causing the NEXT request to get a fresh
        // (empty) session even though the user just logged in.
        session_regenerate_id(true);

        $_SESSION['user_id']     = (int)$user['id'];
        $_SESSION['employee_id'] = $user['employee_id'];
        $_SESSION['full_name']   = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['email']       = $user['email'];
        $_SESSION['role']        = $user['role'];
        $_SESSION['program_id']  = $user['program_id'];
        $_SESSION['last_active'] = time();

        // Write the session NOW (while the handler is still open)
        // so the data is on disk before we return the redirect URL.
        // This is safe — the session cookie header was already queued
        // by session_regenerate_id() and will be sent with the response.
        session_write_close();

        try {
            self::logAudit((int)$user['id'], 'LOGIN', 'users', (int)$user['id']);
        } catch (Throwable $e) {
            error_log('Login audit failed: ' . $e->getMessage());
        }

        $redirect = BASE_PATH . ($user['role'] === 'super_admin'
            ? '/pages/admin/dashboard.php'
            : '/pages/lecturer/dashboard.php');

        return ['success' => true, 'role' => $user['role'], 'redirect' => $redirect];
    }

    // ── Logout ────────────────────────────────────────────────
    public static function logout(): void
    {
        self::startSession();
        try {
            if (!empty($_SESSION['user_id'])) {
                self::logAudit((int)$_SESSION['user_id'], 'LOGOUT', 'users', (int)$_SESSION['user_id']);
            }
        } catch (Throwable $e) {
            error_log('Logout audit failed: ' . $e->getMessage());
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
        }
        session_destroy();
    }

    // ── Idle timeout check ────────────────────────────────────
    public static function checkTimeout(): void
    {
        if (!empty($_SESSION['last_active']) &&
            (time() - (int)$_SESSION['last_active']) > SESSION_LIFETIME) {
            self::logout();
            header('Location: ' . BASE_URL . '/index.php?timeout=1');
            exit;
        }
        $_SESSION['last_active'] = time();
    }

    // ── Guards ────────────────────────────────────────────────
    public static function requireLogin(): void
    {
        self::startSession();
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '/index.php?redirect=1');
            exit;
        }
        self::checkTimeout();
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (($_SESSION['role'] ?? '') !== 'super_admin') {
            http_response_code(403);
            echo '<h2>403 Forbidden</h2><p>You do not have permission to view this page.</p>';
            exit;
        }
    }

    public static function requireLecturer(): void
    {
        self::requireLogin();
        if (!in_array($_SESSION['role'] ?? '', ['super_admin', 'lecturer'], true)) {
            http_response_code(403);
            echo '<h2>403 Forbidden</h2><p>You do not have permission to view this page.</p>';
            exit;
        }
    }

    // ── Helpers ───────────────────────────────────────────────
    public static function user(): array
    {
        return [
            'id'         => (int)($_SESSION['user_id']   ?? 0),
            'full_name'  => $_SESSION['full_name']        ?? '',
            'role'       => $_SESSION['role']              ?? '',
            'program_id' => isset($_SESSION['program_id']) ? (int)$_SESSION['program_id'] : null,
        ];
    }

    public static function isAdmin(): bool
    {
        return ($_SESSION['role'] ?? '') === 'super_admin';
    }

    // ── Audit logging ─────────────────────────────────────────
    public static function logAudit(
        int $userId,
        string $action,
        string $entityType = '',
        ?int $entityId = null,
        array $old = [],
        array $new = []
    ): void {
        DB::run(
            "INSERT INTO audit_logs
               (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES (?,?,?,?,?,?,?,?)",
            [
                $userId, $action, $entityType, $entityId,
                $old ? json_encode($old) : null,
                $new  ? json_encode($new)  : null,
                $_SERVER['REMOTE_ADDR']     ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]
        );
    }
}
