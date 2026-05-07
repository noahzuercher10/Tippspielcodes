<?php
/**
 * Manueller Sync einer Liga mit TheSportsDB.
 *   GET /api/import-from-thesportsdb.php?league_id=1
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sports/SportFactory.php';
header('Content-Type: application/json');
require_admin();

$leagueId = (int)($_GET['league_id'] ?? 0);
if ($leagueId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'league_id fehlt']);
    exit;
}

$row = db()->prepare('SELECT api_id, season FROM leagues WHERE id = ?');
$row->execute([$leagueId]);
$lg = $row->fetch();
if (!$lg || !$lg['api_id']) {
    http_response_code(400);
    echo json_encode(['error' => 'Liga hat keine API-ID.']);
    exit;
}

$api = SportFactory::forLeagueId($leagueId);
if (!$api) {
    http_response_code(404);
    echo json_encode(['error' => 'Sport-Klasse nicht gefunden.']);
    exit;
}

try {
    $res = $api->syncLeague($leagueId, (string)$lg['api_id'], (string)($lg['season'] ?? ''));
    echo json_encode([
        'ok'        => true,
        'league_id' => $leagueId,
        'sport'     => $api->getSportName(),
        'sync'      => $res,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
