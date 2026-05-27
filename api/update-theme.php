<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$theme = $body['theme'] ?? 'light';
if (!in_array($theme, ['light', 'dark'], true)) {
    echo json_encode(['error' => 'Ungültiger Wert.']);
    exit;
}

db()->prepare('UPDATE users SET theme = ? WHERE id = ?')
    ->execute([$theme, $user['id']]);

echo json_encode(['ok' => true]);
