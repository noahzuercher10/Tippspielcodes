<?php
/**
 * Hilfsfunktionen fuer Tipps, Punkteberechnung und Geldlogik.
 */
require_once __DIR__ . '/../config/db.php';

/**
 * Punkteberechnung gemaess Doku:
 *  - Genau richtiger Tipp                      = 10 Pkt
 *  - Richtiges Team (Sieger / Unentschieden)   =  5 Pkt
 *  - Richtige Anzahl Heimtore                  =  1 Pkt
 *  - Richtige Anzahl Auswaertstore             =  1 Pkt
 *  - Richtige Tordifferenz (nur bei richtigem
 *    Sieger-/Unentschieden-Tipp)               =  3 Pkt
 *
 *  Beispiele aus der Doku:
 *    1:1 vs 2:3 -> 0
 *    2:1 vs 2:3 -> 1
 *    2:3 vs 2:3 -> 10
 *    3:2 vs 2:3 -> 0
 *    2:5 vs 2:3 -> 6  (5 Sieger + 1 Heimtore)
 *    1:2 vs 2:3 -> 8  (5 Sieger + 3 Differenz)
 *    4:3 vs 2:3 -> 1  (1 Auswaertstore)
 */
function calculate_points(int $tipH, int $tipA, int $resH, int $resA): int {
    if ($tipH === $resH && $tipA === $resA) {
        return 10;
    }
    $points = 0;

    $tipWinner = $tipH <=> $tipA;       // -1 / 0 / 1
    $resWinner = $resH <=> $resA;
    $sameWinner = $tipWinner === $resWinner;

    if ($sameWinner) $points += 5;
    if ($tipH === $resH) $points += 1;
    if ($tipA === $resA) $points += 1;
    if ($sameWinner && ($tipH - $tipA) === ($resH - $resA)) {
        $points += 3;
    }
    return $points;
}

/**
 * Geld-Modus: bei korrektem Tipp wird Einsatz verdoppelt,
 * sonst geht der Einsatz verloren.
 */
function calculate_money_payout(float $stake, int $tipH, int $tipA, int $resH, int $resA): float {
    return ($tipH === $resH && $tipA === $resA) ? $stake * 2.0 : 0.0;
}

/** Maximaler Einsatz: 25% des aktuellen Guthabens, mind. 10. */
function max_stake(float $balance): float {
    return max(10.0, round($balance * 0.25, 2));
}

/** Wertet alle noch nicht ausgewerteten Tipps eines beendeten Spiels aus. */
function evaluate_match(int $matchId): void {
    $pdo = db();
    $m = $pdo->prepare('SELECT * FROM matches WHERE id = ? AND status = "finished"');
    $m->execute([$matchId]);
    $match = $m->fetch();
    if (!$match) return;

    $bets = $pdo->prepare('SELECT * FROM bets WHERE match_id = ? AND evaluated = 0');
    $bets->execute([$matchId]);

    foreach ($bets as $bet) {
        if ($bet['mode'] === 'points') {
            $pts = calculate_points(
                (int)$bet['tip_home'], (int)$bet['tip_away'],
                (int)$match['home_score'], (int)$match['away_score']
            );
            $upd = $pdo->prepare(
                'UPDATE bets SET points_earned = ?, evaluated = 1 WHERE id = ?'
            );
            $upd->execute([$pts, $bet['id']]);
            $pdo->prepare('UPDATE users SET points_total = points_total + ? WHERE id = ?')
                ->execute([$pts, $bet['user_id']]);
            if ($bet['group_id']) {
                $pdo->prepare('UPDATE group_members SET points = points + ? WHERE group_id = ? AND user_id = ?')
                    ->execute([$pts, $bet['group_id'], $bet['user_id']]);
            }
        } else { // money
            $payout = calculate_money_payout(
                (float)$bet['stake'],
                (int)$bet['tip_home'], (int)$bet['tip_away'],
                (int)$match['home_score'], (int)$match['away_score']
            );
            $upd = $pdo->prepare(
                'UPDATE bets SET money_earned = ?, evaluated = 1 WHERE id = ?'
            );
            $upd->execute([$payout, $bet['id']]);
            // Einsatz wurde beim Setzen schon abgebucht -> nur Auszahlung gutschreiben
            $pdo->prepare('UPDATE users SET money_balance = money_balance + ? WHERE id = ?')
                ->execute([$payout, $bet['user_id']]);
            if ($bet['group_id']) {
                $pdo->prepare('UPDATE group_members SET money = money + ? WHERE group_id = ? AND user_id = ?')
                    ->execute([$payout, $bet['group_id'], $bet['user_id']]);
            }
        }
    }
}

/** Generiert einen einfachen Beitrittscode. */
function generate_join_code(int $len = 7): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code  = '';
    for ($i = 0; $i < $len; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}
