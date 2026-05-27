<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

// Bild entfernen
if (($_POST['action'] ?? '') === 'remove') {
    foreach (['jpg','png','webp'] as $e) {
        $f = __DIR__ . '/../img/avatars/' . $user['id'] . '.' . $e;
        if (file_exists($f)) @unlink($f);
    }
    db()->prepare('UPDATE users SET profile_picture = NULL WHERE id = ?')
        ->execute([$user['id']]);
    echo json_encode(['ok' => true]);
    exit;
}

$f = $_FILES['avatar'] ?? null;
if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Keine Datei oder Upload-Fehler.']);
    exit;
}

$mime = mime_content_type($f['tmp_name']);
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($allowed[$mime])) {
    echo json_encode(['error' => 'Nur JPG, PNG oder WebP erlaubt.']);
    exit;
}
if ($f['size'] > 3 * 1024 * 1024) {
    echo json_encode(['error' => 'Max. 3 MB.']);
    exit;
}

$ext = $allowed[$mime];
$dir = __DIR__ . '/../img/avatars';
if (!is_dir($dir)) mkdir($dir, 0775, true);

foreach (['jpg','png','webp'] as $e) {
    $old = $dir . '/' . $user['id'] . '.' . $e;
    if (file_exists($old)) @unlink($old);
}

$rel = 'img/avatars/' . $user['id'] . '.' . $ext;
$abs = __DIR__ . '/../' . $rel;
if (!move_uploaded_file($f['tmp_name'], $abs)) {
    echo json_encode(['error' => 'Speichern fehlgeschlagen.']);
    exit;
}

db()->prepare('UPDATE users SET profile_picture = ? WHERE id = ?')
    ->execute([$rel, $user['id']]);

echo json_encode(['ok' => true, 'path' => $rel]);
