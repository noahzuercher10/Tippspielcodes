<?php
/**
 * ============================================================
 * POST /api/upload-background.php  (Multipart-Form, file=...)
 * ------------------------------------------------------------
 * Erlaubt dem User ein eigenes Hintergrundbild fuer seine
 * Profilseite hochzuladen.
 *
 * Limits:
 *   - max. 5 MB
 *   - nur Bilder (JPG/PNG/WebP)
 *
 * Speichert die Datei nach img/backgrounds/{user_id}.{ext}
 * und merkt sich den Pfad in users.background_image.
 * ============================================================
 */
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

// Datei aus Form holen
$f = $_FILES['file'] ?? null;
if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Keine Datei oder Upload-Fehler.']);
    exit;
}

// Mimetype und Groesse pruefen
$mime = mime_content_type($f['tmp_name']);
$allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
if (!isset($allowed[$mime])) {
    echo json_encode(['error' => 'Ungueltiger Bildtyp. Erlaubt: JPG, PNG, WebP.']);
    exit;
}
if ($f['size'] > 5 * 1024 * 1024) {                  // 5 MB
    echo json_encode(['error' => 'Datei zu gross (max. 5 MB).']);
    exit;
}

// Zielpfad: img/backgrounds/{user_id}.{ext}
$ext  = $allowed[$mime];
$dir  = __DIR__ . '/../img/backgrounds';
if (!is_dir($dir)) mkdir($dir, 0775, true);

// Alte Datei mit beliebiger Endung loeschen
foreach (['jpg','png','webp'] as $e) {
    $old = $dir . '/' . $user['id'] . '.' . $e;
    if (file_exists($old)) @unlink($old);
}

// Neue Datei speichern
$rel = 'img/backgrounds/' . $user['id'] . '.' . $ext;
$abs = __DIR__ . '/../' . $rel;
if (!move_uploaded_file($f['tmp_name'], $abs)) {
    echo json_encode(['error' => 'Speichern fehlgeschlagen.']);
    exit;
}

// Pfad in DB speichern (relativ zum Tippspiel-Root)
db()->prepare('UPDATE users SET background_image = ? WHERE id = ?')
    ->execute([$rel, $user['id']]);

echo json_encode(['ok' => true, 'path' => $rel]);
