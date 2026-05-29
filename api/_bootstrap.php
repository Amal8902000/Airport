<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expiree']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

function json_body(): array
{
    return json_decode(file_get_contents('php://input'), true) ?: [];
}

function ok(array $payload = []): void
{
    echo json_encode(['success' => true] + $payload);
    exit;
}

function fail(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function require_roles(array $roles): void
{
    $role = $_SESSION['user']['role'] ?? '';
    if (!in_array($role, $roles, true)) {
        fail('Acces refuse pour ce role', 403);
    }
}
