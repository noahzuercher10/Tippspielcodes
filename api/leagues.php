<?php
/** GET /api/leagues.php?sport_id=1  -> Ligen einer Sportart */
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
require_login();

$sportId = (int)($_GET['sport_id'] ?? 0);
$stmt = db()->prepare('SELECT id,name,season FROM leagues WHERE sport_id = ? ORDER BY name');
$stmt->execute([$sportId]);
echo json_encode($stmt->fetchAll());
