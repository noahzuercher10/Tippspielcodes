<?php
/**
 * GET /api/matches.php?league_id=1&date=YYYY-MM-DD&mode=points|money[&group_id=][&debug=1]
 *
 * - Holt die Spiele EINER Liga an EINEM Tag
 * - Spiele werden via passendem SportApi-Objekt (Vererbung) aus
 *   TheSportsDB gezogen und in `matches` gecacht.
 * - Liefert eigenen Tipp + Tipp-Quote zurueck.
 * - Mit ?debug=1 kommen zusaetzlich Sync-Stats und Liga-Info zurueck.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sports/SportFactory.php';
header('Content-Type: application/json');
$user = require_login();

$leagueId = (int)($_GET['league_id'] ?? 0);
$date     = $_GET['date'] ?? date('Y-m-d');
$mode     = $_GET['mode'] ?? 'points';
$groupId  = isset($_GET['group_id']) && $_GET['group_id'] !== '' ? (int)$_GET['group_id'] : null;
$debug    = !empty($_GET['debug']);

if ($leagueId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'league_id und date erforderlich']);
    exit;
}

$api = SportFactory::forLeagueId($leagueId);
if (!$api) {
    http_response_code(404);
    echo json_encode(['error' => 'Liga oder Sport nicht gefunden.']);
    exit;
}

// Vor dem Lesen: Sync erzwingen wenn Cache abgelaufen
$syncStats = null;
try {
    $row = db()->prepare('SELECT api_id FROM leagues WHERE id = ?');
    $row->execute([$leagueId]);
    $apiId = (string)$row->fetchColumn();
    if ($apiId !== '') {
        // ensureFresh respektiert die 30-Min-TTL
        $api->ensureFresh($leagueId);
    }
    $matches = $api->getMatchesForDay($leagueId, $date);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'API/DB-Fehler: ' . $e->getMessage()]);
    exit;
}

// Tipp-Quote
$totalUsers = (int)db()->query(
    'SELECT COUNT(*) FROM users WHERE role = "user"'
)->fetchColumn();

// Eigenen Tipp pro Match dazuladen
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

// Wenn keine Spiele am gewünschten Tag: zeig die nächsten 5 Spiele
// (zur Diagnose / damit der User sieht, wann was stattfindet).
$nextSuggestions = [];
if (!$matches) {
    $sug = db()->prepare(
        'SELECT id, home_name, away_name, match_datetime, status
         FROM matches
         WHERE league_id = ?
           AND match_datetime >= NOW()
         ORDER BY match_datetime
         LIMIT 5'
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
if (!$matches) {
    $out['hint'] = 'Keine Spiele an diesem Tag - hier sind die nächsten geplanten Spiele dieser Liga:';
    $out['next_matches'] = $nextSuggestions;
}
if ($debug) {
    $out['debug'] = [
        'league_id'  => $leagueId,
        'last_error' => $api->lastError,
        'php_curl'   => function_exists('curl_init'),
        'allow_url_fopen' => (bool)ini_get('allow_url_fopen'),
    ];
}

echo json_encode($out);
