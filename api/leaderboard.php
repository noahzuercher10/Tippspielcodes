<?php
/**
 * GET /api/leaderboard.php?mode=points|money
 * ------------------------------------------------------------
 * Liefert die globale Rangliste:
 *  - mode=points : sortiert nach points_total
 *  - mode=money  : sortiert nach money_balance
 *
 * Liefert nur "normale" User (keine Admins).
 */
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
require_login();

$mode = $_GET['mode'] ?? 'points';

// Spalte je nach Modus
$col  = $mode === 'money' ? 'money_balance' : 'points_total';

// Achtung: $col stammt aus einer fixen Whitelist - daher direkt im SQL
// (Prepared Statements koennen keine Spaltennamen parametrieren).
$sql = "SELECT id, username, first_name, last_name, $col AS score
        FROM users
        WHERE role = 'user'
        ORDER BY score DESC, username
        LIMIT 100";
echo json_encode(db()->query($sql)->fetchAll());
