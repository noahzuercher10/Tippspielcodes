<?php
/**
 * ============================================================
 * Admin-API - alles was nur Admins duerfen
 * ------------------------------------------------------------
 * Alle Endpoints liefern JSON. Authentication: require_admin().
 *
 *   GET  action=stats        -> {users, matches, bets, groups}
 *   POST action=add_sport    {name, type, api_class}
 *   POST action=add_league   {sport_id, name, season, api_id?}
 *   POST action=set_result   {match_id, home_score, away_score}
 *                            -> Resultat eintragen + Tipps auswerten
 *   POST action=delete_user  {user_id}
 *   POST action=gift_money   {user_id, amount}
 *   GET  action=list_users
 *   GET  action=list_matches -> letzte 200 Spiele aller Ligen
 *   GET  action=sync_league  {league_id}  -> 1 Liga frisch syncen
 *   GET  action=sync_all     -> ALLE Ligen frisch syncen
 * ============================================================
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/sports/SportFactory.php';
header('Content-Type: application/json');
$admin = require_admin();              // zwingt Admin-Rolle
$pdo   = db();

// Action sowohl aus Query-String als auch aus JSON-Body lesen
$action = $_REQUEST['action'] ?? '';
$in     = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $action ?: ($in['action'] ?? '');

try {
    switch ($action) {
        // ---- Dashboard-Statistik ----
        case 'stats':
            echo json_encode([
                'users'   => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
                'matches' => (int)$pdo->query('SELECT COUNT(*) FROM matches')->fetchColumn(),
                'bets'    => (int)$pdo->query('SELECT COUNT(*) FROM bets')->fetchColumn(),
                'groups'  => (int)$pdo->query('SELECT COUNT(*) FROM groups_t')->fetchColumn(),
            ]);
            break;

        // ---- Neue Sportart anlegen ----
        case 'add_sport':
            // api_class muss zu einer existierenden Klasse passen (FootballSport etc.)
            $pdo->prepare('INSERT INTO sports (name,type,api_class) VALUES (?,?,?)')
                ->execute([
                    trim($in['name'] ?? ''),
                    $in['type'] ?? 'team',
                    trim($in['api_class'] ?? 'FootballSport'),
                ]);
            echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
            break;

        // ---- Neue Liga anlegen ----
        case 'add_league':
            $pdo->prepare('INSERT INTO leagues (sport_id,name,season,api_id) VALUES (?,?,?,?)')
                ->execute([
                    (int)$in['sport_id'],
                    trim($in['name'] ?? ''),
                    trim($in['season'] ?? ''),
                    trim($in['api_id'] ?? '') ?: null,    // leerer String -> NULL
                ]);
            echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
            break;

        // ---- Resultat eintragen + alle Tipps dieses Matches auswerten ----
        case 'set_result':
            $mid = (int)$in['match_id'];
            // Status zwingend auf "finished" damit evaluate_match() arbeitet
            $pdo->prepare(
                'UPDATE matches SET home_score=?, away_score=?, status="finished" WHERE id=?'
            )->execute([(int)$in['home_score'], (int)$in['away_score'], $mid]);
            // verteilt automatisch Punkte/Geld
            evaluate_match($mid);
            echo json_encode(['ok' => true]);
            break;

        // ---- User loeschen (Foreign-Keys cascaden Tipps/Member weg) ----
        case 'delete_user':
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([(int)$in['user_id']]);
            echo json_encode(['ok' => true]);
            break;

        // ---- User Geld schenken (z.B. wenn pleite) ----
        case 'gift_money':
            $uid    = (int)($in['user_id'] ?? 0);
            $amount = round((float)($in['amount'] ?? 0), 2);
            if ($uid <= 0 || $amount <= 0) throw new RuntimeException('Ungueltige Eingabe.');
            // Globales Guthaben + alle Gruppen-Guthaben des Users
            $pdo->prepare('UPDATE users SET money_balance = money_balance + ? WHERE id = ?')
                ->execute([$amount, $uid]);
            $pdo->prepare('UPDATE group_members SET money = money + ? WHERE user_id = ?')
                ->execute([$amount, $uid]);
            echo json_encode(['ok' => true, 'amount' => $amount]);
            break;

        // ---- User-Liste ----
        case 'list_users':
            echo json_encode($pdo->query(
                'SELECT id,username,first_name,last_name,role,points_total,money_balance
                 FROM users ORDER BY id'
            )->fetchAll());
            break;

        // ---- Spiel-Liste (letzte 200) ----
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

        // ---- Eine Liga frisch syncen (TheSportsDB + Subklassen-Quellen) ----
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

        // ---- ALLE Ligen mit api_id syncen (fuer cron oder Admin-Knopf) ----
        case 'sync_all':
            // Erst Duplikate bereinigen
            $pdo->exec(
                'DELETE m1 FROM matches m1
                 INNER JOIN matches m2
                   ON m1.api_event_id = m2.api_event_id
                  AND m1.api_event_id IS NOT NULL
                  AND m1.api_event_id != ""
                  AND m1.id > m2.id'
            );
            $lgs = $pdo->query(
                'SELECT id, api_id, season FROM leagues WHERE api_id IS NOT NULL AND api_id != ""'
            )->fetchAll();
            $report = [];
            foreach ($lgs as $lg) {
                $api = SportFactory::forLeagueId((int)$lg['id']);
                if (!$api) { $report[$lg['id']] = ['error'=>'no class']; continue; }
                try {
                    $api->ensureFresh((int)$lg['id'], true);
                    $report[$lg['id']] = ['ok' => true];
                } catch (Throwable $e) {
                    $report[$lg['id']] = ['error' => $e->getMessage()];
                }
            }
            echo json_encode(['ok' => true, 'report' => $report]);
            break;

        // ---- Duplikate in matches-Tabelle bereinigen ----
        case 'cleanup_dupes':
            $deleted = 0;

            // Pass 1: gleiche api_event_id – niedrigste id behalten
            $deleted += (int)$pdo->exec(
                'DELETE m1 FROM matches m1
                 INNER JOIN matches m2
                   ON m1.api_event_id = m2.api_event_id
                  AND m1.api_event_id IS NOT NULL
                  AND m1.api_event_id != ""
                  AND m1.id > m2.id'
            );

            // Pass 2: gleiche Teams am gleichen Tag (Cross-Source: TheSportsDB vs OpenLigaDB vs FDO)
            // Qualitätsscore: hat Badge (2 Pkt) + hat Ergebnis (1 Pkt) → höchster Score wird behalten
            $groups = $pdo->query(
                "SELECT GROUP_CONCAT(id ORDER BY
                    (home_badge IS NOT NULL AND home_badge != '') * 2 +
                    (home_score IS NOT NULL) DESC, id ASC) AS ids
                 FROM matches
                 GROUP BY LOWER(home_name), LOWER(away_name), DATE(match_datetime)
                 HAVING COUNT(*) > 1"
            )->fetchAll(PDO::FETCH_COLUMN);

            foreach ($groups as $idStr) {
                $ids = explode(',', $idStr);
                $keepId  = (int)array_shift($ids); // höchste Qualität zuerst
                $delIds  = array_map('intval', $ids);
                if (!$delIds) continue;

                // Badge vom besten Duplikat auf den Keeper übertragen, falls Keeper keins hat
                $badgeRow = $pdo->prepare(
                    'SELECT home_badge, away_badge FROM matches
                     WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
                       AND home_badge IS NOT NULL AND home_badge != "" LIMIT 1'
                );
                $badgeRow->execute($delIds);
                $badge = $badgeRow->fetch();
                if ($badge) {
                    $pdo->prepare(
                        'UPDATE matches SET
                           home_badge = COALESCE(NULLIF(home_badge,""), ?),
                           away_badge = COALESCE(NULLIF(away_badge,""), ?)
                         WHERE id = ?'
                    )->execute([$badge['home_badge'], $badge['away_badge'], $keepId]);
                }

                // Duplikate löschen (Tipps auf diese IDs bleiben erhalten, da kein FK-Constraint)
                $pdo->exec(
                    'DELETE FROM matches WHERE id IN (' . implode(',', $delIds) . ')'
                );
                $deleted += count($delIds);
            }

            // Pass 3: alle Matches ohne Badge – Badge aus anderer Row gleichen Teams nachladen
            $updated = $pdo->exec(
                "UPDATE matches m
                 JOIN (
                   SELECT LOWER(home_name) AS team, MIN(home_badge) AS badge
                   FROM matches WHERE home_badge IS NOT NULL AND home_badge != ''
                   GROUP BY LOWER(home_name)
                 ) b ON LOWER(m.home_name) = b.team
                 SET m.home_badge = b.badge
                 WHERE (m.home_badge IS NULL OR m.home_badge = '')"
            );
            $updated += (int)$pdo->exec(
                "UPDATE matches m
                 JOIN (
                   SELECT LOWER(away_name) AS team, MIN(away_badge) AS badge
                   FROM matches WHERE away_badge IS NOT NULL AND away_badge != ''
                   GROUP BY LOWER(away_name)
                 ) b ON LOWER(m.away_name) = b.team
                 SET m.away_badge = b.badge
                 WHERE (m.away_badge IS NULL OR m.away_badge = '')"
            );

            echo json_encode(['ok' => true, 'deleted' => $deleted, 'badges_filled' => $updated]);
            break;

        default:
            // Unbekannte Action -> 400
            http_response_code(400);
            echo json_encode(['error' => 'unknown action']);
    }
} catch (Throwable $e) {
    // Globaler Catch: alle Exceptions als JSON-Error zurueck
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
