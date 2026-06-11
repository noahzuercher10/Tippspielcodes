<?php
/**
 * POST /api/bets.php – Tipp abgeben oder aktualisieren
 *
 * Body (JSON):
 *   match_id    int       – Pflicht
 *   mode        string    – 'points' | 'money'
 *   group_id    int|null  – optional, muss zur Liga des Spiels passen
 *   tip_home    int       – Punkte-Modus: getippte Heimtore/-punkte
 *   tip_away    int       – Punkte-Modus: getippte Auswärtstore/-punkte
 *   tip_winner  string    – Geld-Modus: 'home' | 'draw' | 'away'
 *   stake       float     – Geld-Modus: Einsatz (10–500 CHF)
 *   extra_data  object    – optional:
 *                           { driver: string }      → F1-Tipp
 *                           { sets: [[sh,sa],…] }   → Tennis-Tipp
 *
 * Geschäftsregeln:
 *  - Tipp nur möglich solange match.status='upcoming' UND Startzeit in der Zukunft
 *  - Im Geld-Modus wird der Einsatz sofort vom Guthaben abgezogen
 *  - Wird ein bestehender Tipp überschrieben, wird der alte Einsatz zurückgebucht
 *  - Gruppe muss zur gleichen Liga wie das Spiel gehören
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error'=>'POST only']); exit;
}

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$matchId   = (int)($in['match_id'] ?? 0);
$mode      = $in['mode'] ?? 'points';
$groupId   = isset($in['group_id']) && $in['group_id'] !== '' ? (int)$in['group_id'] : null;
$extraData = isset($in['extra_data']) ? json_encode($in['extra_data']) : null;

if ($matchId <= 0 || !in_array($mode, ['points','money'], true)) {
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

    // Gruppe muss zur gleichen Liga gehören wie das getippte Spiel
    if ($groupId !== null) {
        $gChk = $pdo->prepare('SELECT league_id FROM groups_t WHERE id = ?');
        $gChk->execute([$groupId]);
        $gLeague = (int)$gChk->fetchColumn();
        if ($gLeague !== (int)$match['league_id']) {
            throw new RuntimeException('Diese Gruppe gehört zu einer anderen Liga.');
        }
    }

    if ($mode === 'points') {
        $tipH = (int)($in['tip_home'] ?? 0);
        $tipA = (int)($in['tip_away'] ?? 0);
        if ($extraData !== null) {
            $ed = json_decode($extraData, true);
            if (isset($ed['driver']) && trim($ed['driver']) === '') {
                throw new RuntimeException('Bitte einen Fahrer auswählen.');
            }
            if (isset($ed['sets']) && (!is_array($ed['sets']) || count($ed['sets']) === 0)) {
                throw new RuntimeException('Bitte Satz-Ergebnisse eingeben.');
            }
        }
        $tipWinner = null;
        $stake     = 0;
    } else {
        $tipWinner  = $in['tip_winner'] ?? '';
        $stake      = round((float)($in['stake'] ?? 0), 2);

        // F1-Fahrertipp im Geldmodus: tip_winner wird nicht benötigt
        $inEd = $extraData ? json_decode($extraData, true) : [];
        $isF1DriverPick = !empty($inEd['driver']);

        if (!$isF1DriverPick) {
            if (!in_array($tipWinner, ['home','draw','away'], true)) {
                throw new RuntimeException('Bitte Sieger oder Unentschieden tippen.');
            }
        }

        if ($stake > 0) {
            $u = $pdo->prepare('SELECT money_balance FROM users WHERE id = ? FOR UPDATE');
            $u->execute([$user['id']]);
            $bal      = (float)$u->fetchColumn();
            $maxStake = max_stake($bal);

            if ($maxStake <= 0)             throw new RuntimeException('Du bist pleite. Frag den Admin.');
            if ($stake < STAKE_LOWER_LIMIT) throw new RuntimeException('Mindesteinsatz: '.STAKE_LOWER_LIMIT.'.');
            if ($stake > $maxStake)         throw new RuntimeException('Maximal-Einsatz: '.number_format($maxStake,2,'.',''));

            $pdo->prepare('UPDATE users SET money_balance = money_balance - ? WHERE id = ?')
                ->execute([$stake, $user['id']]);
            if ($groupId) {
                $pdo->prepare('UPDATE group_members SET money = money - ? WHERE group_id=? AND user_id=?')
                    ->execute([$stake, $groupId, $user['id']]);
            }
        }
        $tipH = null; $tipA = null;
    }

    $find = $pdo->prepare(
        'SELECT id FROM bets WHERE user_id=? AND match_id=? AND mode=? AND ' .
        ($groupId === null ? 'group_id IS NULL' : 'group_id=?')
    );
    $params = [$user['id'], $matchId, $mode];
    if ($groupId !== null) $params[] = $groupId;
    $find->execute($params);
    $existingId = $find->fetchColumn();

    if ($existingId) {
        $pdo->prepare(
            'UPDATE bets SET tip_home=?, tip_away=?, tip_winner=?, stake=?, extra_data=? WHERE id=?'
        )->execute([$tipH, $tipA, $tipWinner, $stake, $extraData, $existingId]);
        $betId = (int)$existingId;
    } else {
        $pdo->prepare(
            'INSERT INTO bets (user_id,match_id,group_id,mode,tip_home,tip_away,tip_winner,stake,extra_data)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([$user['id'], $matchId, $groupId, $mode, $tipH, $tipA, $tipWinner, $stake, $extraData]);
        $betId = (int)$pdo->lastInsertId();
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'bet_id' => $betId]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
