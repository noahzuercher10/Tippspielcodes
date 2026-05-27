<?php
/**
 * ============================================================
 * Manueller Sync einer einzelnen Liga (Admin-only)
 * ------------------------------------------------------------
 * GET /api/import-from-thesportsdb.php?league_id=1
 *
 * Aequivalent zu api/admin.php?action=sync_league, aber als
 * eigenstaendige URL (z.B. fuer Browser-Bookmarks oder Tests).
 *
 * Triggert den vollen Sync-Lauf der zugehoerigen Sport-Klasse,
 * also inkl. OpenLigaDB / football-data.org / NHL / Ergast etc.
 * ============================================================
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sports/SportFactory.php';
header('Content-Type: application/json');
require_admin();

// 1) league_id aus Query
$leagueId = (int)($_GET['league_id'] ?? 0);
if ($leagueId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'league_id fehlt']);
    exit;
}

// 2) Liga + api_id holen
$row = db()->prepare('SELECT api_id, season FROM leagues WHERE id = ?');
$row->execute([$leagueId]);
$lg = $row->fetch();
if (!$lg || !$lg['api_id']) {
    http_response_code(400);
    echo json_encode(['error' => 'Liga hat keine API-ID.']);
    exit;
}

// 3) Passende Sport-Klasse instanziieren
$api = SportFactory::forLeagueId($leagueId);
if (!$api) {
    http_response_code(404);
    echo json_encode(['error' => 'Sport-Klasse nicht gefunden.']);
    exit;
}

// 4) Sync ausfuehren + Report zurueckgeben
try {
    $res = $api->syncLeague($leagueId, (string)$lg['api_id'], (string)($lg['season'] ?? ''));
    echo json_encode([
        'ok'        => true,
        'league_id' => $leagueId,
        'sport'     => $api->getSportName(),
        'sync'      => $res,    // {source, imported, updated, seen, last_error}
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
