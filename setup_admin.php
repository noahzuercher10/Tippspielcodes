<?php
/**
 * Einmal-Skript um den Admin-User korrekt anzulegen.
 *
 *   http://localhost/Tippspiel/setup_admin.php
 *
 * Löscht/aktualisiert den User "admin" und setzt das Passwort
 * auf "admin123" (richtig gehasht). Danach Datei aus Sicherheitsgründen
 * löschen oder zumindest umbenennen.
 */
require_once __DIR__ . '/config/db.php';
header('Content-Type: text/html; charset=utf-8');
echo '<pre style="background:#0e1116;color:#dee5ef;padding:20px;font-family:monospace;line-height:1.6">';

$pdo = db();
$pwHash = password_hash('admin123', PASSWORD_BCRYPT);

try {
    $st = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $st->execute(['admin']);
    $existing = (int)$st->fetchColumn();

    if ($existing) {
        $pdo->prepare(
            'UPDATE users SET password_hash = ?, role = "admin", first_name = "Admin",
                              last_name = "User", email = "admin@tippspiel.local"
             WHERE id = ?'
        )->execute([$pwHash, $existing]);
        echo "Bestehender Admin (id=$existing) aktualisiert.\n";
    } else {
        $pdo->prepare(
            'INSERT INTO users (username,email,password_hash,first_name,last_name,role,money_balance)
             VALUES (?,?,?,?,?,"admin", 2500.00)'
        )->execute(['admin','admin@tippspiel.local',$pwHash,'Admin','User']);
        $newId = (int)$pdo->lastInsertId();
        echo "Neuen Admin (id=$newId) angelegt.\n";
    }
    echo "\n";
    echo "Login-Daten:\n";
    echo "  Username: admin\n";
    echo "  Passwort: admin123\n";
    echo "\n";
    echo "Zur Sicherheit bitte diese Datei (setup_admin.php) jetzt löschen.\n";
    echo "\n";
    echo "<a style=\"color:#4f8cff\" href=\"index.php\">-> Zum Login</a>";
} catch (Throwable $e) {
    echo "FEHLER: " . htmlspecialchars($e->getMessage());
}
echo '</pre>';
