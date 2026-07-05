<?php
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::startSession();
try { Auth::logout(); } catch (Throwable $e) {
    error_log('Logout error: ' . $e->getMessage());
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
}
header('Location: ' . BASE_PATH . '/index.php?loggedout=1');
exit;
