<?php
/**
 * GET /api/leagues.php?sport_id=1  -> alle Ligen dieser Sportart
 * ------------------------------------------------------------
 * Wird vom Frontend benutzt, sobald eine Sportart im Dropdown
 * gewaehlt wurde - dann fuellt sich das Liga-Dropdown.
 *
 * Antwort: [{id, name, season}, ...]
 */
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
require_login();

// sport_id aus Query-String (default 0)
$sportId = (int)($_GET['sport_id'] ?? 0);

$stmt = db()->prepare('SELECT id,name,season FROM leagues WHERE sport_id = ? ORDER BY name');
$stmt->execute([$sportId]);
echo json_encode($stmt->fetchAll());
