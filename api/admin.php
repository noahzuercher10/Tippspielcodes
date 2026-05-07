<?php
/**
 * Admin-API - nur fuer role=admin.
 *
 *   GET  action=stats         -> Zählungen
 *   POST action=add_sport     {name, type, api_class}
 *   POST action=add_league    {sport_id, name, season, api_id?}
 *   POST action=set_result    {match_id, home_score, away_score}
 *   POST action=delete_user   {user_id}
 *   POST action=gift_money    {user_id, amount}
 *   GET  action=list_users
 *   GET  action=list_matches  -> letzte 200 Spiele (alle Ligen)
 *   GET  action=sync_league   {league_id}  -> Spiele neu aus API laden
 *   GET  action=sync_all      -> ALLE Ligen mit api_id frisch holen
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/sports/SportFactory.php';
header('Content-Type: application/json');
$admin = require_admin();
$pdo   = db();

$action = $_REQUEST['action'] ?? '';
$in     = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $action ?: ($in['action'] ?? '');

try {
    switch ($action) {
        case 'stats':
            echo json_encode([
                'users'   => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
                'matches' => (int)$pdo->query('SELECT COUNT(*) FROM matches')->fetchColumn(),
                'bets'    => (int)$pdo->query('SELECT COUNT(*) FROM bets')->fetchColumn(),
                'groups'  => (int)$pdo->query('SELECT COUNT(*) FROM groups_t')->fetchColumn(),
            ]);
            break;

        case 'add_sport':
            $pdo->prepare('INSERT INTO sports (name,type,api_class) VALUES (?,?,?)')
                ->execute([
                    trim($in['name'] ?? ''),
                    $in['type'] ?? 'team',
                    trim($in['api_class'] ?? 'FootballSport'),
                ]);
            echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
            break;

        case 'add_league':
            $pdo->prepare('INSERT INTO leagues (sport_id,name,season,api_id) VALUES (?,?,?,?)')
                ->execute([
                    (int)$in['sport_id'],
                    trim($in['name'] ?? ''),
                    trim($in['season'] ?? ''),
                    trim($in['api_id'] ?? '') ?: null,
                ]);
            echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
            break;

        case 'set_result':
            $mid = (int)$in['match_id'];
            $pdo->prepare(
                'UPDATE matches SET home_score=?, away_score=?, status="finished" WHERE id=?'
            )->execute([(int)$in['home_score'], (int)$in['away_score'], $mid]);
            evaluate_match($mid);
            echo json_encode(['ok' => true]);
            break;

        case 'delete_user':
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([(int)$in['user_id']]);
            echo json_encode(['ok' => true]);
            break;

        case 'gift_money':
            $uid    = (int)($in['user_id'] ?? 0);
            $amount = round((float)($in['amount'] ?? 0), 2);
            if ($uid <= 0 || $amount <= 0) throw new RuntimeException('Ungueltige Eingabe.');
            $pdo->prepare('UPDATE users SET money_balance = money_balance + ? WHERE id = ?')
                ->execute([$amount, $uid]);
            $pdo->prepare('UPDATE group_members SET money = money + ? WHERE user_id = ?')
                ->execute([$amount, $uid]);
            echo json_encode(['ok' => true, 'amount' => $amount]);
            break;

        case 'list_users':
            echo json_encode($pdo->query(
                'SELECT id,username,first_name,last_name,role,points_total,money_balance
                 FROM users ORDER BY id'
            )->fetchAll());
            break;

        case 'list_matches':
            $sql = 'SELECT m.id, m.match_datetime, m.status,
                           m.home_name, m.away_name, m.home_score, m.away_score,
                           l.name AS league_name, s.name AS sport_name
                    FROM matches m
                    JOIN leagues l ON l.id = m.league_id
                    JOIN sports  s ON s.id = l.sport_id
                    ORDER BY m.match_datetime DESC LIMIT 200';
            echo json_encode($pdo->query($sql)->fetchAll());
            break;

        case 'sync_league':
            $lid = (int)($_REQUEST['league_id'] ?? $in['league_id'] ?? 0);
            $r = $pdo->prepare('SELECT api_id, season FROM leagues WHERE id = ?');
            $r->execute([$lid]);
            $lg = $r->fetch();
            if (!$lg || !$lg['api_id']) throw new RuntimeException('Liga hat keine API-ID.');
            $api = SportFactory::forLeagueId($lid);
            if (!$api) throw new RuntimeException('Sport-Klasse nicht gefunden.');
            $res = $api->syncLeague($lid, (string)$lg['api_id'], (string)($lg['season'] ?? ''));
            echo json_encode(['ok' => true, 'sync' => $res]);
            break;

        case 'sync_all':
            $lgs = $pdo->query(
                'SELECT id, api_id, season FROM leagues WHERE api_id IS NOT NULL AND api_id != ""'
            )->fetchAll();
            $report = [];
            foreach ($lgs as $lg) {
                $api = SportFactory::forLeagueId((int)$lg['id']);
                if (!$api) { $report[$lg['id']] = ['error'=>'no class']; continue; }
                try {
                    $report[$lg['id']] = $api->syncLeague(
                        (int)$lg['id'], (string)$lg['api_id'], (string)($lg['season'] ?? '')
                    );
                } catch (Throwable $e) {
                    $report[$lg['id']] = ['error' => $e->getMessage()];
                }
            }
            echo json_encode(['ok' => true, 'report' => $report]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
