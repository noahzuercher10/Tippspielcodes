<?php
/**
 * GET /api/matches.php?league_id=1&date=YYYY-MM-DD
 * Liefert Spiele inkl. eigenem Tipp (falls vorhanden) und
 * Tipp-Quote (wieviele User vom Total schon getippt haben).
 */
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
$user = require_login();

$leagueId = (int)($_GET['league_id'] ?? 0);
$date     = $_GET['date'] ?? date('Y-m-d');
$mode     = $_GET['mode'] ?? 'points';
$groupId  = isset($_GET['group_id']) && $_GET['group_id'] !== '' ? (int)$_GET['group_id'] : null;

// gesamte Userzahl (fuer Quote)
$totalUsers = (int) db()->query('SELECT COUNT(*) FROM users WHERE role = "user"')
                        ->fetchColumn();

$sql = "SELECT m.*,
               h.name AS home_name, h.short_name AS home_short,
               a.name AS away_name, a.short_name AS away_short,
               (SELECT COUNT(DISTINCT user_id) FROM bets b WHERE b.match_id = m.id) AS tip_count
        FROM matches m
        JOIN teams h ON h.id = m.home_team_id
        JOIN teams a ON a.id = m.away_team_id
        WHERE m.league_id = ?
          AND DATE(m.match_datetime) = ?
        ORDER BY m.match_datetime";
$stmt = db()->prepare($sql);
$stmt->execute([$leagueId, $date]);
$matches = $stmt->fetchAll();

// eigenen Tipp dazuladen
$bet = db()->prepare(
    'SELECT * FROM bets
     WHERE user_id = ? AND match_id = ? AND mode = ?
       AND ' . ($groupId === null ? 'group_id IS NULL' : 'group_id = ?')
);

foreach ($matches as &$m) {
    $params = [$user['id'], $m['id'], $mode];
    if ($groupId !== null) $params[] = $groupId;
    $bet->execute($params);
    $m['my_bet'] = $bet->fetch() ?: null;
    $m['total_users'] = $totalUsers;
}

echo json_encode([
    'matches' => $matches,
    'date'    => $date,
    'total_users' => $totalUsers,
]);
