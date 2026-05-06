<?php
/** GET /api/leaderboard.php?mode=points|money */
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
require_login();

$mode = $_GET['mode'] ?? 'points';
$col  = $mode === 'money' ? 'money_balance' : 'points_total';

$sql = "SELECT id, username, first_name, last_name, $col AS score
        FROM users
        WHERE role = 'user'
        ORDER BY score DESC, username
        LIMIT 100";
echo json_encode(db()->query($sql)->fetchAll());
