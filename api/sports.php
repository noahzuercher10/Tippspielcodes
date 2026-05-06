<?php
/** GET /api/sports.php  -> Liste aller Sportarten */
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
require_login();

$rows = db()->query('SELECT id,name,type FROM sports ORDER BY name')->fetchAll();
echo json_encode($rows);
