<?php
/**
 * Punkte- und Geldlogik fuers Tippspiel.
 */
require_once __DIR__ . '/../config/db.php';

/** Geld-Konstanten gemaess Spec. */
const MONEY_START_BALANCE = 2500.00;  // Startkapital
const STAKE_UPPER_LIMIT   = 500.00;   // max. Einsatz pro Spiel
const STAKE_LOWER_LIMIT   = 10.00;    // min. möglicher Einsatz

/**
 * Punkteberechnung fuer den Punktemodus (laut Doku):
 *  - Genau richtiger Tipp                = 10
 *  - Richtiges Team / Unentschieden      =  5
 *  - Richtige Anzahl Heimtore            =  1
 *  - Richtige Anzahl Auswärtstore       =  1
 *  - Richtige Tordifferenz (nur bei rich-
 *    tigem Sieger-/Unentschieden-Tipp)   =  3
 */
function calculate_points(int $tipH, int $tipA, int $resH, int $resA): int {
    if ($tipH === $resH && $tipA === $resA) return 10;

    $points = 0;
    $tipWinner = $tipH <=> $tipA;
    $resWinner = $resH <=> $resA;
    $sameWinner = $tipWinner === $resWinner;

    if ($sameWinner)             $points += 5;
    if ($tipH === $resH)         $points += 1;
    if ($tipA === $resA)         $points += 1;
    if ($sameWinner && ($tipH - $tipA) === ($resH - $resA)) {
        $points += 3;
    }
    return $points;
}

/**
 * Geldmodus: nur Sieger-Tipp ('home', 'draw', 'away').
 *  - Korrekt   -> Auszahlung = Einsatz * 2
 *  - Falsch    -> Auszahlung = 0 (Einsatz bereits abgebucht)
 */
function calculate_money_payout(string $tipWinner, int $resH, int $resA, float $stake): float {
    $actual = $resH > $resA ? 'home' : ($resH < $resA ? 'away' : 'draw');
    return $tipWinner === $actual ? $stake * 2.0 : 0.0;
}

/**
 * Maximaler Einsatz nach Spec:
 *   max = min(500, balance), aber nie unter 10.
 *   Wenn Guthaben < 10 -> 0 (kann nichts mehr setzen, Admin muss auffuellen).
 */
function max_stake(float $balance): float {
    if ($balance < STAKE_LOWER_LIMIT) return 0.0;
    return round(min(STAKE_UPPER_LIMIT, $balance), 2);
}

/** Wertet alle nicht-ausgewerteten Tipps eines beendeten Spiels aus. */
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
            $pdo->prepare('UPDATE bets SET points_earned=?, evaluated=1 WHERE id=?')
                ->execute([$pts, $bet['id']]);
            $pdo->prepare('UPDATE users SET points_total = points_total + ? WHERE id = ?')
                ->execute([$pts, $bet['user_id']]);
            if ($bet['group_id']) {
                $pdo->prepare('UPDATE group_members SET points = points + ? WHERE group_id=? AND user_id=?')
                    ->execute([$pts, $bet['group_id'], $bet['user_id']]);
            }
        } else { // money
            $payout = calculate_money_payout(
                (string)$bet['tip_winner'],
                (int)$match['home_score'], (int)$match['away_score'],
                (float)$bet['stake']
            );
            $pdo->prepare('UPDATE bets SET money_earned=?, evaluated=1 WHERE id=?')
                ->execute([$payout, $bet['id']]);
            // Einsatz wurde beim Setzen abgezogen -> Auszahlung gutschreiben
            $pdo->prepare('UPDATE users SET money_balance = money_balance + ? WHERE id = ?')
                ->execute([$payout, $bet['user_id']]);
            if ($bet['group_id']) {
                $pdo->prepare('UPDATE group_members SET money = money + ? WHERE group_id=? AND user_id=?')
                    ->execute([$payout, $bet['group_id'], $bet['user_id']]);
            }
        }
    }
}

function generate_join_code(int $len = 7): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code  = '';
    for ($i = 0; $i < $len; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}
