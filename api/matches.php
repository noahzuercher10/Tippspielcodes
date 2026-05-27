<?php
/**
 * ============================================================
 * GET /api/matches.php
 * ------------------------------------------------------------
 * Liefert die Spiele EINER Liga an EINEM Tag (nur ZUKUENFTIGE).
 *
 * Query-Parameter:
 *   league_id  - DB-ID der Liga (erforderlich)
 *   date       - YYYY-MM-DD (default: heute)
 *   mode       - "points" oder "money" (default: points)
 *   group_id   - optional: nur Tipps einer Gruppe beruecksichtigen
 *   debug      - optional: zusaetzliche Debug-Info im Result
 *   force      - optional: 30-Min-Cache ignorieren, frisch syncen
 *
 * Liefer-Logik:
 *   - Datum vor heute    -> leere Liste (vergangene Tage nicht anzeigen)
 *   - Datum heute        -> nur Spiele die noch nicht begonnen haben
 *   - Datum in Zukunft   -> alle Spiele dieses Tages
 *
 * Auto-Force: falls zukuenftiger Tag aber leer -> einmal hart resyncen.
 * ============================================================
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sports/SportFactory.php';
header('Content-Type: application/json');
$user = require_login();

// ---- Query-Parameter einlesen + Defaults ----
$leagueId = (int)($_GET['league_id'] ?? 0);
$date     = $_GET['date'] ?? date('Y-m-d');
$mode     = $_GET['mode'] ?? 'points';
$groupId  = isset($_GET['group_id']) && $_GET['group_id'] !== '' ? (int)$_GET['group_id'] : null;
$debug    = !empty($_GET['debug']);
$force    = !empty($_GET['force']);

// Validierung: league_id muss > 0 + Datum im Format YYYY-MM-DD
if ($leagueId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'league_id und date erforderlich']);
    exit;
}

// SportApi-Objekt fuer die Liga holen (FootballSport, IceHockeySport, ...)
$api = SportFactory::forLeagueId($leagueId);
if (!$api) {
    http_response_code(404);
    echo json_encode(['error' => 'Liga oder Sport nicht gefunden.']);
    exit;
}

$syncStats = null;
try {
    // ---- Sync vor dem Lesen ----
    if ($force) {
        // force=1 -> 30-Min-Cache komplett umgehen
        $api->ensureFresh($leagueId, true);
    } else {
        // ensureFresh checkt selbst ob last_sync zu alt ist
        $api->ensureFresh($leagueId);
    }

    // ---- Branch je nach Datum ----
    if (strtotime($date) < strtotime(date('Y-m-d'))) {
        // Vergangenheit -> nicht anzeigen
        $matches = [];
    } else {
        // Heute oder Zukunft: SQL passend bauen
        $sql = ($date === date('Y-m-d'))
            ? 'SELECT m.* FROM matches m
                WHERE m.league_id = ?
                  AND DATE(m.match_datetime) = ?
                  AND m.match_datetime > NOW()      -- nur noch nicht gestartete
                ORDER BY m.match_datetime'
            : 'SELECT m.* FROM matches m
                WHERE m.league_id = ?
                  AND DATE(m.match_datetime) = ?
                ORDER BY m.match_datetime';
        $st = db()->prepare($sql);
        $st->execute([$leagueId, $date]);
        $matches = $st->fetchAll();

        // Auto-Force: zukuenftiger Tag, leer, noch nicht geforced
        // -> einmal hart neu syncen und nochmal abfragen.
        if (!$matches && !$force && strtotime($date) > strtotime(date('Y-m-d'))) {
            $api->ensureFresh($leagueId, true);
            $st->execute([$leagueId, $date]);
            $matches = $st->fetchAll();
        }
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'API/DB-Fehler: ' . $e->getMessage()]);
    exit;
}

// ---- Tipp-Quote: wie viele User haben getippt? ----
$totalUsers = (int)db()->query(
    'SELECT COUNT(*) FROM users WHERE role = "user"'
)->fetchColumn();

// Vorbereitete Statements wiederverwenden (schneller)
$bet = db()->prepare(
    'SELECT * FROM bets
     WHERE user_id = ? AND match_id = ? AND mode = ?
       AND ' . ($groupId === null ? 'group_id IS NULL' : 'group_id = ?')
);
$cnt = db()->prepare(
    'SELECT COUNT(DISTINCT user_id) FROM bets WHERE match_id = ?'
);

// Pro Match: eigenen Tipp + Anzahl Tipper anhaengen
foreach ($matches as &$m) {
    $params = [$user['id'], $m['id'], $mode];
    if ($groupId !== null) $params[] = $groupId;
    $bet->execute($params);
    $m['my_bet'] = $bet->fetch() ?: null;

    $cnt->execute([$m['id']]);
    $m['tip_count']   = (int)$cnt->fetchColumn();
    $m['total_users'] = $totalUsers;
}
unset($m);

// ---- Leerer Tag: naechste 5 Spiele dieser Liga vorschlagen ----
$nextSuggestions = [];
if (!$matches) {
    $sug = db()->prepare(
        'SELECT id, home_name, away_name, match_datetime, status
         FROM matches
         WHERE league_id = ? AND match_datetime > NOW()
         ORDER BY match_datetime
         LIMIT 5'
    );
    $sug->execute([$leagueId]);
    $nextSuggestions = $sug->fetchAll();
}

// ---- Response zusammenbauen ----
$out = [
    'matches'     => $matches,
    'date'        => $date,
    'total_users' => $totalUsers,
    'sport'       => $api->getSportName(),
];
if (!$matches) {
    // Hinweistext je nach Situation
    if (strtotime($date) < strtotime(date('Y-m-d'))) {
        $out['hint'] = 'Vergangene Tage werden nicht angezeigt.';
    } else {
        $out['hint'] = 'Keine Spiele in der Datenbank fuer diesen Tag. Klicke auf "Aktualisieren" um frisch von der API zu laden.';
    }
    $out['next_matches'] = $nextSuggestions;
}
// Debug-Mode: zusaetzliche Diagnose-Felder
if ($debug) {
    $out['debug'] = [
        'league_id'        => $leagueId,
        'last_error'       => $api->lastError,
        'php_curl'         => function_exists('curl_init'),
        'allow_url_fopen'  => (bool)ini_get('allow_url_fopen'),
        'now'              => date('Y-m-d H:i:s'),
        'forced'           => $force,
    ];
}

echo json_encode($out);
