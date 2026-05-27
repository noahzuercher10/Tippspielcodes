<?php
/**
 * ============================================================
 * Punkte- / Geld-Auswertung + Hilfsfunktionen
 * ------------------------------------------------------------
 * Hier liegt die komplette Spielmechanik:
 *  - calculate_points()       : Punktemodus-Tipp-Bewertung
 *  - calculate_money_payout() : Geldmodus-Auszahlung
 *  - evaluate_match()         : alle Tipps eines beendeten Spiels abrechnen
 * ============================================================
 */
require_once __DIR__ . '/../config/db.php';

/** Startkapital im Geldmodus (laut Spec). */
const MONEY_START_BALANCE = 2500.00;
/** Maximaler Einsatz pro Spiel im Geldmodus. */
const STAKE_UPPER_LIMIT   = 500.00;
/** Minimaler Einsatz pro Spiel im Geldmodus. */
const STAKE_LOWER_LIMIT   = 10.00;

/**
 * Bewertet einen Tipp im Punktemodus.
 *
 * Punkteschluessel (laut Doku):
 *  - exakter Tipp (genaues Resultat)                  = 10
 *  - richtiger Sieger / Unentschieden                 =  5
 *  - richtige Anzahl Heim-Tore                        =  1
 *  - richtige Anzahl Auswaerts-Tore                   =  1
 *  - richtige Tordifferenz (nur wenn Sieger stimmt)   =  3
 *
 * @param int $tipH  getippte Heim-Tore
 * @param int $tipA  getippte Auswaerts-Tore
 * @param int $resH  tatsaechliche Heim-Tore
 * @param int $resA  tatsaechliche Auswaerts-Tore
 * @return int       erreichte Punkte
 */
function calculate_points(int $tipH, int $tipA, int $resH, int $resA): int {
    // Volltreffer -> 10 Punkte, kein weiteres Aufaddieren
    if ($tipH === $resH && $tipA === $resA) return 10;

    $points = 0;
    // <=> ist der Spaceship-Operator: liefert -1 / 0 / +1
    $tipWinner = $tipH <=> $tipA;
    $resWinner = $resH <=> $resA;
    $sameWinner = $tipWinner === $resWinner;

    // Sieger / Unentschieden korrekt -> 5
    if ($sameWinner)             $points += 5;
    // Einzelne richtige Toranzahl -> jeweils 1
    if ($tipH === $resH)         $points += 1;
    if ($tipA === $resA)         $points += 1;
    // Tordifferenz korrekt UND Sieger korrekt -> +3
    if ($sameWinner && ($tipH - $tipA) === ($resH - $resA)) {
        $points += 3;
    }
    return $points;
}

/**
 * Berechnet die Auszahlung im Geldmodus.
 *  - richtig getippt -> Einsatz * 2 (Einsatz zurueck + gleicher Gewinn)
 *  - falsch          -> 0 (Einsatz wurde beim Tippen schon abgezogen)
 */
function calculate_money_payout(string $tipWinner, int $resH, int $resA, float $stake): float {
    $actual = $resH > $resA ? 'home' : ($resH < $resA ? 'away' : 'draw');
    return $tipWinner === $actual ? $stake * 2.0 : 0.0;
}

/**
 * Berechnet den maximal erlaubten Einsatz fuer einen User.
 *  - max = min(500, Guthaben)
 *  - unter 10 Guthaben -> 0 (kann nichts mehr setzen)
 */
function max_stake(float $balance): float {
    if ($balance < STAKE_LOWER_LIMIT) return 0.0;
    return round(min(STAKE_UPPER_LIMIT, $balance), 2);
}

/**
 * Formel 1: Tipp-Bewertung (Fahrer korrekt getippt = 10 Punkte).
 * Der korrekte Fahrer steht in Klammern am Ende von home_name:
 *   "Monaco GP – Sieger (Max Verstappen)"
 */
function calculate_points_f1(string $extraData, string $homeName): int {
    $ed = json_decode($extraData, true);
    $tipped = trim($ed['driver'] ?? '');
    if ($tipped === '') return 0;
    if (preg_match('/\(([^)]+)\)\s*$/', $homeName, $m)) {
        return strcasecmp($tipped, trim($m[1])) === 0 ? 10 : 0;
    }
    return 0;
}

/**
 * Tennis: Tipp-Bewertung anhand von extra_data.sets.
 * Leitet aus den getippten Einzel-Satz-Ergebnissen die Satz-Anzahl ab
 * und bewertet dann wie im Standard-Punktemodus (Sätze statt Tore).
 */
function calculate_points_tennis(string $extraData, int $resH, int $resA): int {
    $ed   = json_decode($extraData, true);
    $sets = $ed['sets'] ?? [];
    if (!$sets) return 0;
    $tipH = 0; $tipA = 0;
    foreach ($sets as $s) {
        $sh = (int)($s[0] ?? 0);
        $sa = (int)($s[1] ?? 0);
        if ($sh > $sa) $tipH++;
        elseif ($sa > $sh) $tipA++;
    }
    return calculate_points($tipH, $tipA, $resH, $resA);
}

/**
 * Wertet ALLE noch nicht ausgewerteten Tipps eines beendeten Spiels aus.
 * Wird aufgerufen wenn:
 *  - der Admin ein Resultat manuell eintraegt (api/admin.php set_result)
 *  - der Cron-Sync ein neues Resultat von der API bekommt
 *
 * Verbucht die Gewinne automatisch auf User-Total und Gruppen-Mitgliedschaft.
 */
function evaluate_match(int $matchId): void {
    $pdo = db();
    // Match muss "finished" sein, sonst nichts auswerten
    $m = $pdo->prepare('SELECT * FROM matches WHERE id = ? AND status = "finished"');
    $m->execute([$matchId]);
    $match = $m->fetch();
    if (!$match) return;

    // Alle Tipps fuer dieses Spiel die noch nicht ausgewertet sind
    $bets = $pdo->prepare('SELECT * FROM bets WHERE match_id = ? AND evaluated = 0');
    $bets->execute([$matchId]);

    foreach ($bets as $bet) {
        if ($bet['mode'] === 'points') {
            // ----- Punktemodus -----
            $extra = (string)($bet['extra_data'] ?? '');
            $ed    = $extra ? json_decode($extra, true) : [];
            if (!empty($ed['driver'])) {
                $pts = calculate_points_f1($extra, $match['home_name']);
            } elseif (!empty($ed['sets'])) {
                $pts = calculate_points_tennis($extra, (int)$match['home_score'], (int)$match['away_score']);
            } else {
                $pts = calculate_points(
                    (int)$bet['tip_home'], (int)$bet['tip_away'],
                    (int)$match['home_score'], (int)$match['away_score']
                );
            }
            // Tipp als "evaluated" markieren + Punkte am Tipp speichern
            $pdo->prepare('UPDATE bets SET points_earned=?, evaluated=1 WHERE id=?')
                ->execute([$pts, $bet['id']]);
            // Punkte auf User-Total addieren
            $pdo->prepare('UPDATE users SET points_total = points_total + ? WHERE id = ?')
                ->execute([$pts, $bet['user_id']]);
            // Wenn Tipp in einer Gruppe abgegeben war: auch dort verbuchen
            if ($bet['group_id']) {
                $pdo->prepare('UPDATE group_members SET points = points + ? WHERE group_id=? AND user_id=?')
                    ->execute([$pts, $bet['group_id'], $bet['user_id']]);
            }
        } else {
            // ----- Geldmodus -----
            $payout = calculate_money_payout(
                (string)$bet['tip_winner'],
                (int)$match['home_score'], (int)$match['away_score'],
                (float)$bet['stake']
            );
            $pdo->prepare('UPDATE bets SET money_earned=?, evaluated=1 WHERE id=?')
                ->execute([$payout, $bet['id']]);
            // Einsatz wurde beim Tippen abgezogen -> Auszahlung als Plus drauf
            $pdo->prepare('UPDATE users SET money_balance = money_balance + ? WHERE id = ?')
                ->execute([$payout, $bet['user_id']]);
            if ($bet['group_id']) {
                $pdo->prepare('UPDATE group_members SET money = money + ? WHERE group_id=? AND user_id=?')
                    ->execute([$payout, $bet['group_id'], $bet['user_id']]);
            }
        }
    }
}

/**
 * Generiert einen Zufalls-Code fuer Gruppen-Beitritt (z.B. "X7K3MQR").
 * Ohne verwechselbare Zeichen (kein 0/O/I/1).
 */
function generate_join_code(int $len = 7): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code  = '';
    for ($i = 0; $i < $len; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}
