<?php
/**
 * ============================================================
 * GET /api/sports.php
 * ------------------------------------------------------------
 * Liefert alle verfuegbaren Sportarten als JSON.
 *
 * Antwortbeispiel:
 *   [{"id":1,"name":"Fussball","type":"team","api_class":"FootballSport","icon":null}, ...]
 *
 * Wird im Frontend benutzt, um das Sportarten-Dropdown
 * auf der Sportarten- und Admin-Seite zu fuellen.
 *
 * Zusatz: Die Datei migriert die DB sanft - falls Spalten
 * fehlen (alte Installation), werden sie hier nachgeruestet,
 * damit das System ohne SQL-Reimport laeuft.
 * ============================================================
 */
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
require_login();                              // nur eingeloggte User

$pdo = db();

// ------------------------------------------------------------
// 1) Soft-Migration: fehlende Spalten dazu ergaenzen, damit
//    aelteste Installationen ohne SQL-Reimport funktionieren.
// ------------------------------------------------------------
try {
    // Pruefen ob sports.api_class existiert
    $hasCol = (bool)$pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'sports'
           AND COLUMN_NAME  = 'api_class'"
    )->fetchColumn();

    if (!$hasCol) {
        // Spalte nachruesten und mit den Standard-Klassen vorbefuellen
        $pdo->exec("ALTER TABLE sports ADD COLUMN api_class VARCHAR(60) NOT NULL DEFAULT 'FootballSport'");
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

    // Pruefen ob leagues.last_sync existiert (fuer Cache-TTL)
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
    // Migrationsfehler nicht fatal werden lassen - wir fallen
    // im naechsten Schritt einfach auf einen Query ohne api_class zurueck
}

// ------------------------------------------------------------
// 2) Sportarten lesen und zurueckgeben
// ------------------------------------------------------------
try {
    $rows = $pdo->query(
        'SELECT id, name, type,
                COALESCE(api_class, "") AS api_class,
                icon
         FROM sports
         ORDER BY id'
    )->fetchAll();
} catch (Throwable $e) {
    // Fallback ohne api_class (sollte nach Migration nicht mehr passieren)
    $rows = $pdo->query('SELECT id, name, type, "" AS api_class, icon FROM sports ORDER BY id')->fetchAll();
}

echo json_encode($rows);
