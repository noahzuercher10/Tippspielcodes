<?php
/**
 * ============================================================
 * POST /api/bets.php  - Tipp abgeben oder aktualisieren
 * ------------------------------------------------------------
 * Punktemodus:
 *   { match_id, mode:"points", tip_home, tip_away, group_id? }
 *
 * Geldmodus:
 *   { match_id, mode:"money", tip_winner:"home|draw|away", stake, group_id? }
 *
 * Speichert den Tipp oder aktualisiert einen bestehenden
 * (UPSERT-Logik via UNIQUE-Key user+match+group+mode).
 * Im Geldmodus wird der Einsatz sofort vom Guthaben abgezogen.
 * ============================================================
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');
$user = require_login();

// Nur POST erlaubt
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error'=>'POST only']); exit;
}

// Body lesen (JSON oder Form)
$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;

// Eingaben validieren
$matchId    = (int) ($in['match_id'] ?? 0);
$mode       = $in['mode'] ?? 'points';
$groupId    = isset($in['group_id']) && $in['group_id'] !== '' ? (int)$in['group_id'] : null;

if ($matchId <= 0 || !in_array($mode, ['points','money'], true)) {
    http_response_code(400); echo json_encode(['error'=>'invalid input']); exit;
}

$pdo = db();
// Transaktion: Guthaben + Tipp atomar (so kann man nicht via Race-Condition
// Geld duplizieren).
$pdo->beginTransaction();
try {
    // Match holen + Row sperren (FOR UPDATE)
    $m = $pdo->prepare('SELECT * FROM matches WHERE id = ? FOR UPDATE');
    $m->execute([$matchId]);
    $match = $m->fetch();
    if (!$match) throw new RuntimeException('Spiel nicht gefunden.');

    // Tipps nur bis Anpfiff erlaubt
    if ($match['status'] !== 'upcoming' || strtotime($match['match_datetime']) <= time()) {
        throw new RuntimeException('Spiel hat bereits begonnen oder ist beendet.');
    }

    // ----- Branch je nach Modus -----
    if ($mode === 'points') {
        // Punktemodus: Heim- und Auswaerts-Tore eingeben
        $tipH = (int)($in['tip_home'] ?? -1);
        $tipA = (int)($in['tip_away'] ?? -1);
        if ($tipH < 0 || $tipA < 0) throw new RuntimeException('Bitte beide Toranzahlen eingeben.');

        $tipWinner = null;
        $stake     = 0;
    } else {
        // Geldmodus: Sieger + Einsatz
        $tipWinner = $in['tip_winner'] ?? '';
        $stake     = round((float)($in['stake'] ?? 0), 2);
        if (!in_array($tipWinner, ['home','draw','away'], true)) {
            throw new RuntimeException('Bitte Sieger oder Unentschieden tippen.');
        }

        // Guthaben lesen + sperren
        $u = $pdo->prepare('SELECT money_balance FROM users WHERE id = ? FOR UPDATE');
        $u->execute([$user['id']]);
        $bal      = (float)$u->fetchColumn();
        $maxStake = max_stake($bal);

        // Plausibilitaetspruefungen
        if ($maxStake <= 0)        throw new RuntimeException('Du bist pleite. Frag den Admin nach Geld.');
        if ($stake < STAKE_LOWER_LIMIT) throw new RuntimeException('Mindesteinsatz ist '.STAKE_LOWER_LIMIT.'.');
        if ($stake > $maxStake)    throw new RuntimeException('Maximal-Einsatz: '.number_format($maxStake,2,'.',''));

        // Einsatz sofort abbuchen (vom User-Konto + ggf. Gruppen-Konto)
        $pdo->prepare('UPDATE users SET money_balance = money_balance - ? WHERE id = ?')
            ->execute([$stake, $user['id']]);
        if ($groupId) {
            $pdo->prepare('UPDATE group_members SET money = money - ? WHERE group_id=? AND user_id=?')
                ->execute([$stake, $groupId, $user['id']]);
        }

        // im Geldmodus gibt es keine Heim/Auswaerts-Tore-Tipps
        $tipH = null; $tipA = null;
    }

    // ----- UPSERT: existierenden Tipp aktualisieren oder neuen anlegen -----
    $find = $pdo->prepare(
        'SELECT id FROM bets WHERE user_id=? AND match_id=? AND mode=? AND ' .
        ($groupId === null ? 'group_id IS NULL' : 'group_id=?')
    );
    $params = [$user['id'], $matchId, $mode];
    if ($groupId !== null) $params[] = $groupId;
    $find->execute($params);
    $existingId = $find->fetchColumn();

    if ($existingId) {
        // Update bestehenden Tipp
        $pdo->prepare(
            'UPDATE bets SET tip_home=?, tip_away=?, tip_winner=?, stake=? WHERE id=?'
        )->execute([$tipH, $tipA, $tipWinner, $stake, $existingId]);
        $betId = (int)$existingId;
    } else {
        // Neuen Tipp anlegen
        $pdo->prepare(
            'INSERT INTO bets (user_id,match_id,group_id,mode,tip_home,tip_away,tip_winner,stake)
             VALUES (?,?,?,?,?,?,?,?)'
        )->execute([$user['id'], $matchId, $groupId, $mode, $tipH, $tipA, $tipWinner, $stake]);
        $betId = (int)$pdo->lastInsertId();
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'bet_id' => $betId]);
} catch (Throwable $e) {
    // Bei Fehler alles rollbacken (Einsatz bleibt nicht weg!)
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
