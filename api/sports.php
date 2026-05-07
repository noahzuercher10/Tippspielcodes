<?php
/**
 * GET /api/sports.php
 * Liefert alle verfuegbaren Sportarten als JSON.
 * Funktioniert mit altem UND neuem DB-Schema:
 *  - Falls die Spalte api_class noch nicht existiert (altes SQL),
 *    wird sie automatisch ergänzt und mit Standardwerten gefüllt.
 */
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
require_login();

$pdo = db();

// 1) Pruefen ob die Spalte api_class existiert; wenn nicht: nachruesten.
try {
    $hasCol = (bool)$pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'sports'
           AND COLUMN_NAME  = 'api_class'"
    )->fetchColumn();

    if (!$hasCol) {
        $pdo->exec("ALTER TABLE sports ADD COLUMN api_class VARCHAR(60) NOT NULL DEFAULT 'FootballSport'");
        // Defaults setzen, damit die Vererbung funktioniert
        $map = [
            'Fussball'   => 'FootballSport',
            'Eishockey'  => 'IceHockeySport',
            'Basketball' => 'BasketballSport',
            'Tennis'     => 'TennisSport',
            'Formel 1'   => 'Formula1Sport',
        ];
        $upd = $pdo->prepare('UPDATE sports SET api_class = ? WHERE name = ?');
        foreach ($map as $name => $cls) {
            $upd->execute([$cls, $name]);
        }
    }

    // last_sync nachruesten falls noch nicht da
    $hasSync = (bool)$pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'leagues'
           AND COLUMN_NAME  = 'last_sync'"
    )->fetchColumn();
    if (!$hasSync) {
        $pdo->exec("ALTER TABLE leagues ADD COLUMN last_sync DATETIME DEFAULT NULL");
    }
} catch (Throwable $e) {
    // Migrationsfehler nicht fatal werden lassen - wir lesen sport-Liste auch ohne api_class
}

// 2) Sportarten lesen
try {
    $rows = $pdo->query(
        'SELECT id, name, type,
                COALESCE(api_class, "") AS api_class,
                icon
         FROM sports
         ORDER BY id'
    )->fetchAll();
} catch (Throwable $e) {
    // Fallback ohne api_class
    $rows = $pdo->query('SELECT id, name, type, "" AS api_class, icon FROM sports ORDER BY id')->fetchAll();
}

echo json_encode($rows);
