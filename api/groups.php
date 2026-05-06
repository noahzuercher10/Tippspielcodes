<?php
/**
 * Gruppen-API
 *
 *  GET    /api/groups.php             -> meine Gruppen
 *  GET    /api/groups.php?id=1        -> Detail mit Mitgliedern
 *  POST   /api/groups.php  action=create  { name, mode, league_id, join_code? }
 *  POST   /api/groups.php  action=join    { join_code }
 *  POST   /api/groups.php  action=leave   { group_id }
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');
$user = require_login();
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        $g = $pdo->prepare(
            'SELECT g.*, l.name AS league_name, s.name AS sport_name
             FROM groups_t g
             JOIN leagues l ON l.id = g.league_id
             JOIN sports  s ON s.id = l.sport_id
             WHERE g.id = ?'
        );
        $g->execute([$id]);
        $group = $g->fetch();
        if (!$group) { http_response_code(404); echo json_encode(['error'=>'not found']); exit; }

        $m = $pdo->prepare(
            'SELECT u.id, u.username, u.first_name, u.last_name,
                    gm.points, gm.money
             FROM group_members gm
             JOIN users u ON u.id = gm.user_id
             WHERE gm.group_id = ?
             ORDER BY (CASE WHEN ? = "points" THEN gm.points ELSE gm.money END) DESC'
        );
        $m->execute([$id, $group['mode']]);
        $group['members'] = $m->fetchAll();
        echo json_encode($group);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT g.*, l.name AS league_name, s.name AS sport_name,
                (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) AS member_count
         FROM groups_t g
         JOIN group_members gm ON gm.group_id = g.id AND gm.user_id = ?
         JOIN leagues l ON l.id = g.league_id
         JOIN sports  s ON s.id = l.sport_id
         ORDER BY g.created_at DESC'
    );
    $stmt->execute([$user['id']]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// POST
$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $in['action'] ?? '';

try {
    if ($action === 'create') {
        $name     = trim($in['name'] ?? '');
        $mode     = $in['mode'] ?? 'points';
        $leagueId = (int)($in['league_id'] ?? 0);
        $code     = trim($in['join_code'] ?? '') ?: generate_join_code();
        if ($name === '' || !in_array($mode,['points','money'],true) || $leagueId<=0) {
            throw new RuntimeException('Ungueltige Eingabe.');
        }
        $pdo->beginTransaction();
        $pdo->prepare(
            'INSERT INTO groups_t (name,join_code,mode,league_id,admin_id) VALUES (?,?,?,?,?)'
        )->execute([$name, $code, $mode, $leagueId, $user['id']]);
        $gid = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO group_members (group_id,user_id) VALUES (?,?)')
            ->execute([$gid, $user['id']]);
        $pdo->commit();
        echo json_encode(['ok'=>true, 'id'=>$gid, 'join_code'=>$code]);

    } elseif ($action === 'join') {
        $code = trim($in['join_code'] ?? '');
        $g = $pdo->prepare('SELECT id FROM groups_t WHERE join_code = ?');
        $g->execute([$code]);
        $gid = (int)$g->fetchColumn();
        if (!$gid) throw new RuntimeException('Gruppe nicht gefunden.');
        $pdo->prepare('INSERT IGNORE INTO group_members (group_id,user_id) VALUES (?,?)')
            ->execute([$gid, $user['id']]);
        echo json_encode(['ok'=>true, 'id'=>$gid]);

    } elseif ($action === 'leave') {
        $gid = (int)($in['group_id'] ?? 0);
        $pdo->prepare('DELETE FROM group_members WHERE group_id=? AND user_id=?')
            ->execute([$gid, $user['id']]);
        echo json_encode(['ok'=>true]);

    } else {
        http_response_code(400);
        echo json_encode(['error'=>'unknown action']);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error'=>$e->getMessage()]);
}
