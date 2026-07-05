<?php
// ============================================================
// includes/helpers.php — Shared utilities  (PHP 7.1+ safe)
// ============================================================

defined('ATTENDANCE_SYS') or die('Direct access not permitted.');

function jsonResponse(bool $success, string $message = '', $data = null, int $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $out = ['success' => $success, 'message' => $message];
    if ($data !== null) $out['data'] = $data;
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitize($input)
{
    if (is_array($input)) return array_map('sanitize', $input);
    return htmlspecialchars(strip_tags(trim((string)$input)), ENT_QUOTES, 'UTF-8');
}

function requireJson(): array
{
    $raw  = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : null;
    return is_array($data) ? $data : [];
}

function paginate(int $total, int $page, int $limit): array
{
    $pages = max(1, (int)ceil($total / max($limit, 1)));
    return [
        'total'        => $total,
        'current_page' => $page,
        'per_page'     => $limit,
        'total_pages'  => $pages,
        'has_next'     => $page < $pages,
        'has_prev'     => $page > 1,
    ];
}

function currentAcademicYear(): string
{
    $y = (int)date('Y');
    $m = (int)date('n');
    return $m >= 8 ? "{$y}/" . ($y + 1) : ($y - 1) . "/{$y}";
}

function validatePassword(string $pw): bool
{
    return strlen($pw) >= 8
        && preg_match('/[A-Z]/', $pw)
        && preg_match('/[a-z]/', $pw)
        && preg_match('/[0-9]/', $pw);
}
