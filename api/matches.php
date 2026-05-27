<?php
/**
 * GET /api/matches.php
 *
 * Parameter:
 *   league_id  - Pflicht
 *   date       - YYYY-MM-DD (default: heute)
 *   mode       - "points" | "money" (default: points)
 *   group_id   - optional
 *   force      - optional: Cache umgehen + neu syncen
 *   all        - optional: alle Spiele der Liga (Saisonübersicht)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sports/SportFactory.php';
header('Content-Type: application/json');
$user = require_login();

$leagueId = (int)($_GET['league_id'] ?? 0);
$date     = $_GET['date'] ?? date('Y-m-d');
$mode     = $_GET['mode'] ?? 'points';
$groupId  = isset($_GET['group_id']) && $_GET['group_id'] !== '' ? (int)$_GET['group_id'] : null;
$force    = !empty($_GET['force']);
$showAll  = !empty($_GET['all']);

if ($leagueId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'league_id und date erforderlich']);
    exit;
}

$api = SportFactory::forLeagueId($leagueId);
if (!$api) {
    http_response_code(404);
    echo json_encode(['error' => 'Liga nicht gefunden.']);
    exit;
}

try {
    // Sync: für vergangene Tage nur wenn force=1 explizit gesetzt
    $isPast = strtotime($date) < strtotime(date('Y-m-d'));
    if ($force) {
        $api->ensureFresh($leagueId, true);
    } elseif (!$isPast) {
        $api->ensureFresh($leagueId);
    }

    if ($showAll) {
        // Saisonübersicht: alle Spiele der Liga
        $st = db()->prepare(
            'SELECT * FROM matches
             WHERE league_id = ?
             ORDER BY match_datetime'
        );
        $st->execute([$leagueId]);
        $matches = $st->fetchAll();
    } else {
        // Tagesansicht: alle Spiele am gewählten Tag
        $st = db()->prepare(
            'SELECT * FROM matches
             WHERE league_id = ? AND DATE(match_datetime) = ?
             ORDER BY match_datetime'
        );
        $st->execute([$leagueId, $date]);
        $matches = $st->fetchAll();

        // Future auto-force: zukünftiger Tag leer → einmal frisch syncen
        if (!$matches && !$force && !$isPast) {
            $api->ensureFresh($leagueId, true);
            $st->execute([$leagueId, $date]);
            $matches = $st->fetchAll();
        }
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Fehler: ' . $e->getMessage()]);
    exit;
}

$totalUsers = (int)db()->query(
    'SELECT COUNT(*) FROM users WHERE role = "user"'
)->fetchColumn();

$bet = db()->prepare(
    'SELECT * FROM bets
     WHERE user_id = ? AND match_id = ? AND mode = ?
       AND ' . ($groupId === null ? 'group_id IS NULL' : 'group_id = ?')
);
$cnt = db()->prepare(
    'SELECT COUNT(DISTINCT user_id) FROM bets WHERE match_id = ?'
);

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

// Nächste 5 Spiele als Orientierung wenn kein Match an gewähltem Tag
$nextSuggestions = [];
if (!$matches && !$showAll) {
    $sug = db()->prepare(
        'SELECT id, home_name, away_name, match_datetime
         FROM matches
         WHERE league_id = ? AND match_datetime > NOW()
         ORDER BY match_datetime LIMIT 5'
    );
    $sug->execute([$leagueId]);
    $nextSuggestions = $sug->fetchAll();
}

$out = [
    'matches'     => $matches,
    'date'        => $date,
    'total_users' => $totalUsers,
    'sport'       => $api->getSportName(),
];

if (!$matches && !$showAll) {
    $out['hint'] = $isPast
        ? 'Keine Spiele für diesen Tag in der Datenbank. Klicke auf "Aktualisieren" um vergangene Daten zu laden.'
        : 'Keine Spiele an diesem Tag. Klicke auf "Aktualisieren" um von der API zu laden.';
    $out['next_matches'] = $nextSuggestions;
}

echo json_encode($out);
