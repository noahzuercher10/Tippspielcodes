<?php
/**
 * GET /api/matches.php – Spiele einer Liga für einen Tag (oder die ganze Saison)
 *
 * Query-Parameter:
 *   league_id  int              Pflicht – interne Liga-ID
 *   date       YYYY-MM-DD       optional, default: heute
 *   mode       points|money     optional, default: points
 *   group_id   int              optional – filtert my_bet auf diese Gruppe
 *   force      1                optional – Cache umgehen, Sync erzwingen
 *   all        1                optional – alle Spiele der Saison (Saisonübersicht)
 *
 * Antwort-Felder pro Match:
 *   + my_bet        object|null  Tipp des eingeloggten Users (oder null)
 *   + tip_count     int          Anzahl User die auf dieses Spiel getippt haben
 *   + total_users   int          Gesamtanzahl User (für Fortschrittsbalken)
 *
 * Zusätzliche Felder auf Root-Ebene:
 *   sport         string   Name der Sportart
 *   sport_class   string   PHP-Klasse (z.B. 'Formula1Sport')
 *   league_name   string   Anzeigename der Liga
 *   hint          string   Hinweis wenn keine Spiele gefunden
 *   next_matches  array    Nächste 5 Spiele wenn Tagesansicht leer
 *
 * Sync-Verhalten:
 *   - Ohne ?force wird ensureFresh() aufgerufen (synct nur wenn > 1h alt)
 *   - Mit ?force wird immer neu von der API geladen
 *   - Leerer Tag → automatisch force-Sync (einmalig)
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
    // Sync immer aufrufen – ensureFresh entscheidet anhand des 1-Stunden-Cache selbst
    if ($force) {
        $api->ensureFresh($leagueId, true);
    } else {
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

        // Leerer Tag → einmalig force-sync (egal ob Vergangenheit oder Zukunft)
        if (!$matches && !$force) {
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

// ---- Badges für Matches ohne Badge aus DB nachfüllen ----
// (OpenLigaDB/FDO-Rows haben oft kein Badge, TheSportsDB-Rows schon)
$missingTeams = [];
foreach ($matches as $m) {
    if (empty($m['home_badge'])) $missingTeams[strtolower($m['home_name'])] = true;
    if (empty($m['away_badge'])) $missingTeams[strtolower($m['away_name'])] = true;
}
if ($missingTeams) {
    $teamList = array_keys($missingTeams);
    $ph = implode(',', array_fill(0, count($teamList), '?'));
    $bSt = db()->prepare(
        "SELECT team, badge FROM (
            SELECT LOWER(home_name) AS team, home_badge AS badge FROM matches
              WHERE LOWER(home_name) IN ($ph) AND home_badge IS NOT NULL AND home_badge != ''
            UNION
            SELECT LOWER(away_name), away_badge FROM matches
              WHERE LOWER(away_name) IN ($ph) AND away_badge IS NOT NULL AND away_badge != ''
         ) AS t GROUP BY team"
    );
    $bSt->execute(array_merge($teamList, $teamList));
    $badgeMap = [];
    foreach ($bSt->fetchAll() as $row) $badgeMap[$row['team']] = $row['badge'];
    foreach ($matches as &$m) {
        if (empty($m['home_badge'])) $m['home_badge'] = $badgeMap[strtolower($m['home_name'])] ?? null;
        if (empty($m['away_badge'])) $m['away_badge'] = $badgeMap[strtolower($m['away_name'])] ?? null;
    }
    unset($m);
}

// ---- Dedup Pass 1: gleiche api_event_id (Schutz gegen DB-Duplikate) ----
// Vorher nach Qualität sortieren: Rows mit Badge + Ergebnis bevorzugen
usort($matches, function ($a, $b) {
    $qa = (!empty($a['home_badge']) ? 2 : 0) + ($a['home_score'] !== null ? 1 : 0);
    $qb = (!empty($b['home_badge']) ? 2 : 0) + ($b['home_score'] !== null ? 1 : 0);
    if ($qb !== $qa) return $qb - $qa;
    return $a['id'] - $b['id']; // gleiche Qualität: ältere Row bevorzugen
});
$seenKeys = [];
$matches  = array_values(array_filter($matches, function ($m) use (&$seenKeys) {
    $key = !empty($m['api_event_id']) ? $m['api_event_id'] : 'id_' . $m['id'];
    if (isset($seenKeys[$key])) return false;
    $seenKeys[$key] = true;
    return true;
}));

// ---- Dedup Pass 2: gleiche Teams am gleichen Tag (Cross-Source-Duplikate) ----
// z.B. PSG vs Arsenal 3× weil TheSportsDB + OpenLigaDB + FDO alle eine eigene Row haben
$seen2 = [];
$matches = array_values(array_filter($matches, function ($m) use (&$seen2) {
    $key = strtolower($m['home_name']) . '§' . strtolower($m['away_name'])
         . '§' . substr($m['match_datetime'], 0, 10);
    if (isset($seen2[$key])) return false;
    $seen2[$key] = true;
    return true;
}));

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

$isPast = strtotime($date) < strtotime(date('Y-m-d'));

$lgRow = db()->prepare(
    'SELECT l.name AS league_name, s.api_class FROM leagues l JOIN sports s ON s.id = l.sport_id WHERE l.id = ?'
);
$lgRow->execute([$leagueId]);
$lgInfo = $lgRow->fetch();

$out = [
    'matches'      => $matches,
    'date'         => $date,
    'total_users'  => $totalUsers,
    'sport'        => $api->getSportName(),
    'sport_class'  => $lgInfo ? $lgInfo['api_class']    : '',
    'league_name'  => $lgInfo ? $lgInfo['league_name']  : '',
];

if (!$matches && !$showAll) {
    $out['hint'] = $isPast
        ? 'Keine Spiele für diesen Tag in der Datenbank. Klicke auf "Aktualisieren" um vergangene Daten zu laden.'
        : 'Keine Spiele an diesem Tag. Klicke auf "Aktualisieren" um von der API zu laden.';
    $out['next_matches'] = $nextSuggestions;
}

echo json_encode($out);
