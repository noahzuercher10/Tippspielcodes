<?php
/**
 * Import von Spielen + Resultaten aus der KOSTENLOSEN TheSportsDB-API.
 * https://www.thesportsdb.com/free_sports_api
 *
 *  GET /api/import-from-thesportsdb.php?league_id=1
 *
 * Voraussetzung: in `leagues.api_id` muss die TheSportsDB-Liga-ID stehen
 * (siehe sql/tippspiel.sql – Beispiel: Super League = 4344).
 *
 * Holt:
 *  - die naechste Runde der Liga -> als 'upcoming' eintragen
 *  - die letzte gespielte Runde -> als 'finished' (mit Resultat) eintragen,
 *    danach evaluate_match() pro Spiel -> Punkte + Geld werden verteilt.
 *
 * Nur Admins duerfen den Import starten.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');
require_admin();

$pdo      = db();
$leagueId = (int)($_GET['league_id'] ?? 0);
if ($leagueId <= 0) { http_response_code(400); echo json_encode(['error'=>'league_id fehlt']); exit; }

$row = $pdo->prepare('SELECT id, sport_id, api_id FROM leagues WHERE id = ?');
$row->execute([$leagueId]);
$league = $row->fetch();
if (!$league || !$league['api_id']) {
    http_response_code(400);
    echo json_encode(['error'=>'Liga hat keine TheSportsDB-API-ID hinterlegt.']);
    exit;
}

function tsdb_call(string $endpoint): ?array {
    $url = "https://www.thesportsdb.com/api/v1/json/3/$endpoint";
    $ctx = stream_context_create(['http' => ['timeout' => 8]]);
    $raw = @file_get_contents($url, false, $ctx);
    return $raw ? json_decode($raw, true) : null;
}

/** Team mit Name suchen oder anlegen (sport_id der Liga). */
function ensure_team(PDO $pdo, int $sportId, string $name): int {
    $st = $pdo->prepare('SELECT id FROM teams WHERE sport_id=? AND name=? LIMIT 1');
    $st->execute([$sportId, $name]);
    if ($id = $st->fetchColumn()) return (int)$id;
    $pdo->prepare('INSERT INTO teams (sport_id,name) VALUES (?,?)')
        ->execute([$sportId, $name]);
    return (int)$pdo->lastInsertId();
}

$imported = 0; $finished = 0; $errors = [];

// --- Naechste Runde (upcoming) ---
$next = tsdb_call('eventsnextleague.php?id=' . urlencode($league['api_id']));
foreach ($next['events'] ?? [] as $ev) {
    try {
        $home = ensure_team($pdo, (int)$league['sport_id'], $ev['strHomeTeam']);
        $away = ensure_team($pdo, (int)$league['sport_id'], $ev['strAwayTeam']);
        $dt   = ($ev['dateEvent'] ?? '') . ' ' . ($ev['strTime'] ?? '00:00:00');

        $exists = $pdo->prepare('SELECT id FROM matches WHERE api_event_id = ?');
        $exists->execute([$ev['idEvent']]);
        if (!$exists->fetchColumn()) {
            $pdo->prepare(
                'INSERT INTO matches (league_id,home_team_id,away_team_id,match_datetime,api_event_id,status)
                 VALUES (?,?,?,?,?,"upcoming")'
            )->execute([$leagueId, $home, $away, $dt, $ev['idEvent']]);
            $imported++;
        }
    } catch (Throwable $e) { $errors[] = $e->getMessage(); }
}

// --- Letzte Runde (finished, mit Resultat) ---
$last = tsdb_call('eventspastleague.php?id=' . urlencode($league['api_id']));
foreach ($last['events'] ?? [] as $ev) {
    if ($ev['intHomeScore'] === null || $ev['intAwayScore'] === null) continue;
    try {
        $home = ensure_team($pdo, (int)$league['sport_id'], $ev['strHomeTeam']);
        $away = ensure_team($pdo, (int)$league['sport_id'], $ev['strAwayTeam']);
        $dt   = ($ev['dateEvent'] ?? '') . ' ' . ($ev['strTime'] ?? '00:00:00');

        $exists = $pdo->prepare('SELECT id FROM matches WHERE api_event_id = ?');
        $exists->execute([$ev['idEvent']]);
        $matchId = (int)$exists->fetchColumn();
        if (!$matchId) {
            $pdo->prepare(
                'INSERT INTO matches (league_id,home_team_id,away_team_id,match_datetime,
                                       home_score,away_score,status,api_event_id)
                 VALUES (?,?,?,?,?,?,"finished",?)'
            )->execute([$leagueId,$home,$away,$dt,
                        (int)$ev['intHomeScore'],(int)$ev['intAwayScore'],$ev['idEvent']]);
            $matchId = (int)$pdo->lastInsertId();
        } else {
            $pdo->prepare(
                'UPDATE matches SET home_score=?, away_score=?, status="finished" WHERE id=?'
            )->execute([(int)$ev['intHomeScore'], (int)$ev['intAwayScore'], $matchId]);
        }
        evaluate_match($matchId);
        $finished++;
    } catch (Throwable $e) { $errors[] = $e->getMessage(); }
}

echo json_encode([
    'ok'             => true,
    'league_id'      => $leagueId,
    'imported_new'   => $imported,
    'finished_synced'=> $finished,
    'errors'         => $errors,
    'note'           => 'Punkte und Geld der ausgewerteten Spiele wurden auf die User verbucht.',
]);
