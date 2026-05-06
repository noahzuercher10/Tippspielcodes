<?php
/**
 * Admin-API – nur fuer role=admin.
 *
 *   POST action=add_match     {league_id, home_team_id, away_team_id, match_datetime}
 *   POST action=set_result    {match_id, home_score, away_score}
 *   POST action=add_team      {sport_id, name, short_name}
 *   POST action=add_league    {sport_id, name, season}
 *   POST action=add_sport     {name, type}
 *   POST action=delete_user   {user_id}
 *   GET  action=stats         -> Zaehlungen
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');
$admin = require_admin();
$pdo = db();

$action = $_REQUEST['action'] ?? ($_SERVER['REQUEST_METHOD']==='GET' ? ($_GET['action'] ?? '') : '');
$in     = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $action ?: ($in['action'] ?? '');

try {
    switch ($action) {
        case 'stats':
            echo json_encode([
                'users'    => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
                'matches'  => (int)$pdo->query('SELECT COUNT(*) FROM matches')->fetchColumn(),
                'bets'     => (int)$pdo->query('SELECT COUNT(*) FROM bets')->fetchColumn(),
                'groups'   => (int)$pdo->query('SELECT COUNT(*) FROM groups_t')->fetchColumn(),
            ]);
            break;

        case 'add_sport':
            $pdo->prepare('INSERT INTO sports (name,type) VALUES (?,?)')
                ->execute([trim($in['name']??''), $in['type']??'team']);
            echo json_encode(['ok'=>true,'id'=>(int)$pdo->lastInsertId()]);
            break;

        case 'add_league':
            $pdo->prepare('INSERT INTO leagues (sport_id,name,season) VALUES (?,?,?)')
                ->execute([(int)$in['sport_id'], trim($in['name']??''), trim($in['season']??'')]);
            echo json_encode(['ok'=>true,'id'=>(int)$pdo->lastInsertId()]);
            break;

        case 'add_team':
            $pdo->prepare('INSERT INTO teams (sport_id,name,short_name) VALUES (?,?,?)')
                ->execute([(int)$in['sport_id'], trim($in['name']??''), trim($in['short_name']??'')]);
            echo json_encode(['ok'=>true,'id'=>(int)$pdo->lastInsertId()]);
            break;

        case 'add_match':
            $pdo->prepare(
                'INSERT INTO matches (league_id,home_team_id,away_team_id,match_datetime)
                 VALUES (?,?,?,?)'
            )->execute([
                (int)$in['league_id'], (int)$in['home_team_id'], (int)$in['away_team_id'],
                $in['match_datetime']
            ]);
            echo json_encode(['ok'=>true,'id'=>(int)$pdo->lastInsertId()]);
            break;

        case 'set_result':
            $mid = (int)$in['match_id'];
            $pdo->prepare(
                'UPDATE matches SET home_score=?, away_score=?, status="finished" WHERE id=?'
            )->execute([(int)$in['home_score'], (int)$in['away_score'], $mid]);
            evaluate_match($mid);
            echo json_encode(['ok'=>true]);
            break;

        case 'delete_user':
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([(int)$in['user_id']]);
            echo json_encode(['ok'=>true]);
            break;

        case 'gift_money':
            $uid    = (int)($in['user_id'] ?? 0);
            $amount = round((float)($in['amount'] ?? 0), 2);
            if ($uid <= 0 || $amount <= 0) throw new RuntimeException('Ungueltige Eingabe.');
            $pdo->prepare('UPDATE users SET money_balance = money_balance + ? WHERE id = ?')
                ->execute([$amount, $uid]);
            // auch in allen Gruppen, in denen der User Mitglied ist:
            $pdo->prepare('UPDATE group_members SET money = money + ? WHERE user_id = ?')
                ->execute([$amount, $uid]);
            echo json_encode(['ok'=>true,'amount'=>$amount]);
            break;

        case 'list_users':
            echo json_encode($pdo->query('SELECT id,username,first_name,last_name,role,points_total,money_balance FROM users ORDER BY id')->fetchAll());
            break;

        case 'list_teams':
            $sportId = (int)($_GET['sport_id'] ?? 0);
            $st = $pdo->prepare('SELECT id,name,short_name FROM teams WHERE sport_id = ? ORDER BY name');
            $st->execute([$sportId]);
            echo json_encode($st->fetchAll());
            break;

        case 'list_matches':
            $sql = 'SELECT m.*, h.name AS home_name, a.name AS away_name, l.name AS league_name
                    FROM matches m
                    JOIN teams h ON h.id=m.home_team_id
                    JOIN teams a ON a.id=m.away_team_id
                    JOIN leagues l ON l.id=m.league_id
                    ORDER BY m.match_datetime DESC LIMIT 200';
            echo json_encode($pdo->query($sql)->fetchAll());
            break;

        default:
            http_response_code(400);
            echo json_encode(['error'=>'unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error'=>$e->getMessage()]);
}
