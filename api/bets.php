<?php
/**
 * POST /api/bets.php
 * Body: { match_id, mode: "points"|"money", tip_home, tip_away, stake?, group_id? }
 *
 * - Punktemodus : speichert Tipp (kein Einsatz)
 * - Geldmodus   : zieht Einsatz vom Wallet ab; Auszahlung erst nach Auswertung
 *
 * Tipps koennen nur abgegeben werden bevor das Spiel begonnen hat.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error'=>'POST only']); exit;
}

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$matchId = (int) ($in['match_id'] ?? 0);
$mode    = $in['mode']            ?? 'points';
$tipH    = (int) ($in['tip_home'] ?? -1);
$tipA    = (int) ($in['tip_away'] ?? -1);
$stake   = (float)($in['stake']   ?? 0);
$groupId = isset($in['group_id']) && $in['group_id'] !== '' ? (int)$in['group_id'] : null;

if ($matchId <= 0 || $tipH < 0 || $tipA < 0 || !in_array($mode, ['points','money'], true)) {
    http_response_code(400); echo json_encode(['error'=>'invalid input']); exit;
}

$pdo = db();
$pdo->beginTransaction();
try {
    $m = $pdo->prepare('SELECT * FROM matches WHERE id = ? FOR UPDATE');
    $m->execute([$matchId]);
    $match = $m->fetch();
    if (!$match) throw new RuntimeException('Spiel nicht gefunden.');
    if ($match['status'] !== 'upcoming' || strtotime($match['match_datetime']) <= time()) {
        throw new RuntimeException('Spiel hat bereits begonnen oder ist beendet.');
    }

    if ($mode === 'money') {
        $u = $pdo->prepare('SELECT money_balance FROM users WHERE id = ? FOR UPDATE');
        $u->execute([$user['id']]);
        $bal = (float) $u->fetchColumn();
        $maxStake = max_stake($bal);
        if ($stake <= 0)        throw new RuntimeException('Einsatz muss > 0 sein.');
        if ($stake > $bal)      throw new RuntimeException('Nicht genug Guthaben.');
        if ($stake > $maxStake) throw new RuntimeException('Maximal-Einsatz: ' . $maxStake);

        $pdo->prepare('UPDATE users SET money_balance = money_balance - ? WHERE id = ?')
            ->execute([$stake, $user['id']]);
        if ($groupId) {
            $pdo->prepare('UPDATE group_members SET money = money - ? WHERE group_id = ? AND user_id = ?')
                ->execute([$stake, $groupId, $user['id']]);
        }
    } else {
        $stake = 0;
    }

    // Upsert: vorhandenen Tipp aktualisieren
    $find = $pdo->prepare(
        'SELECT id FROM bets WHERE user_id=? AND match_id=? AND mode=? AND ' .
        ($groupId === null ? 'group_id IS NULL' : 'group_id=?')
    );
    $params = [$user['id'], $matchId, $mode];
    if ($groupId !== null) $params[] = $groupId;
    $find->execute($params);
    $existingId = $find->fetchColumn();

    if ($existingId) {
        $pdo->prepare('UPDATE bets SET tip_home=?, tip_away=?, stake=? WHERE id=?')
            ->execute([$tipH, $tipA, $stake, $existingId]);
        $betId = (int)$existingId;
    } else {
        $pdo->prepare(
            'INSERT INTO bets (user_id,match_id,group_id,mode,tip_home,tip_away,stake)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([$user['id'], $matchId, $groupId, $mode, $tipH, $tipA, $stake]);
        $betId = (int)$pdo->lastInsertId();
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'bet_id' => $betId]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
