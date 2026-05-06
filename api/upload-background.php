<?php
/**
 * POST /api/upload-background.php
 * multipart/form-data, Feld "background" = Datei (jpg/jpeg/png/webp)
 *
 * Speichert die Datei unter /img/backgrounds/<userId>.<ext> und merkt
 * sich den relativen Pfad in users.background_image.
 *
 * Action "remove" (POST action=remove) -> Hintergrund auf Standard zuruecksetzen.
 */
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
$user = require_login();
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error'=>'POST only']); exit;
}

if (($_POST['action'] ?? '') === 'remove') {
    $pdo->prepare('UPDATE users SET background_image = NULL WHERE id = ?')->execute([$user['id']]);
    echo json_encode(['ok'=>true, 'background_image'=>null]);
    exit;
}

if (!isset($_FILES['background']) || $_FILES['background']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error'=>'Datei-Upload fehlgeschlagen.']);
    exit;
}

$f    = $_FILES['background'];
$mime = mime_content_type($f['tmp_name']);
$exts = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
if (!isset($exts[$mime])) {
    http_response_code(400);
    echo json_encode(['error'=>'Nur Bilder (JPG, PNG, WEBP, GIF) sind erlaubt.']);
    exit;
}
if ($f['size'] > 4 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error'=>'Datei zu gross (max 4 MB).']);
    exit;
}

$dir = __DIR__ . '/../img/backgrounds';
if (!is_dir($dir)) @mkdir($dir, 0775, true);

$ext   = $exts[$mime];
$fname = $user['id'] . '_' . time() . '.' . $ext;
$path  = $dir . '/' . $fname;
if (!move_uploaded_file($f['tmp_name'], $path)) {
    http_response_code(500);
    echo json_encode(['error'=>'Datei konnte nicht gespeichert werden.']);
    exit;
}

// alten Hintergrund loeschen (falls vorhanden)
$old = $pdo->prepare('SELECT background_image FROM users WHERE id = ?');
$old->execute([$user['id']]);
$oldFile = $old->fetchColumn();
if ($oldFile && is_file($dir . '/' . basename($oldFile))) {
    @unlink($dir . '/' . basename($oldFile));
}

$rel = 'img/backgrounds/' . $fname;
$pdo->prepare('UPDATE users SET background_image = ? WHERE id = ?')->execute([$rel, $user['id']]);

echo json_encode(['ok'=>true, 'background_image'=>$rel]);
