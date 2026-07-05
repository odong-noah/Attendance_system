<?php
define('ATTENDANCE_SYS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.', null, 405);
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];

$email    = strtolower(trim($body['email'] ?? ''));
$password = $body['password'] ?? '';

if (!$email || !$password) {
    jsonResponse(false, 'Email and password are required.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Please enter a valid email address.');
}

$result = Auth::login($email, $password);
jsonResponse(
    $result['success'],
    $result['message'] ?? '',
    $result['success'] ? ['redirect' => $result['redirect']] : null
);
