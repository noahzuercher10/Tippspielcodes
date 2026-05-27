<?php
require_once __DIR__ . '/SportApi.php';

/**
 * ============================================================
 * Formel 1 - Jolpica Ergast API
 * ------------------------------------------------------------
 * Holt Daten von https://api.jolpi.ca/ergast/ - der offizielle
 * Open-Source-Nachfolger der originalen Ergast-API. Hat den
 * vollen F1-Spielplan inkl. Resultate, ohne API-Key.
 *
 * Mapping in unsere matches-Tabelle:
 *   home_name = "<GP-Name> - Sieger"     (+ Fahrername wenn Resultat da)
 *   away_name = "<GP-Name> - Podium 2."  (+ Fahrername wenn Resultat da)
 *
 * Die F1-Klasse ueberschreibt syncLeague() KOMPLETT - sie nutzt
 * NICHT TheSportsDB-eventsseason/round, weil Ergast viel bessere
 * F1-Daten liefert (offizielle Renndaten + Fahrerresultate).
 * ============================================================
 */
class Formula1Sport extends SportApi
{
    /**
     * Komplett eigene Sync-Logik mit Ergast statt TheSportsDB.
     * Holt zwei Endpoints:
     *  1) /f1/{year}.json          -> alle Rennen der Saison
     *  2) /f1/{year}/results.json  -> alle bisherigen Resultate
     * und merged dann beide.
     */
    public function syncLeague(int $leagueId, string $apiLeagueId, string $season = ''): array
    {
        $imported = 0; $updated = 0; $seen = 0;

        // Jahr extrahieren (auch aus "2025-2026" -> "2025")
        $year = '';
        if (preg_match('/(\d{4})/', $season, $m)) $year = $m[1];
        if (!$year) $year = date('Y');

        // 1) Alle Rennen der Saison
        $rawSched = $this->httpGet("https://api.jolpi.ca/ergast/f1/$year.json?limit=50");
        if (!$rawSched) {
            return ['source'=>'ergast', 'imported'=>0, 'updated'=>0,
                    'seen'=>0, 'last_error'=>$this->lastError];
        }
        $data  = json_decode($rawSched, true);
        $races = $data['MRData']['RaceTable']['Races'] ?? [];

        // 2) Resultate (alle bisherigen) in einer einzigen Anfrage holen
        //    und nach round indexieren fuer schnellen Lookup.
        $byRound = [];
        $rawRes = $this->httpGet("https://api.jolpi.ca/ergast/f1/$year/results.json?limit=300");
        if ($rawRes) {
            $dr = json_decode($rawRes, true);
            foreach (($dr['MRData']['RaceTable']['Races'] ?? []) as $r) {
                $byRound[(string)$r['round']] = $r;
            }
        }

        // Verarbeitung
        $now   = time();
        $maxTs = strtotime($this->maxDate . ' 23:59:59');
        $pdo   = db();
        $checkExist = $pdo->prepare('SELECT id FROM matches WHERE api_event_id = ?');

        foreach ($races as $r) {
            $seen++;
            $round = (string)($r['round'] ?? '');
            $name  = $r['raceName'] ?? "Runde $round";
            $date  = $r['date']     ?? '';
            $time  = $r['time']     ?? '14:00:00Z';
            if ($date === '' || $round === '') continue;
            $time = rtrim($time, 'Z');
            // Ergast liefert UTC -> wir behalten es als UTC im DATETIME drin
            $dt = trim("$date $time");
            $ts = strtotime($dt);
            if ($ts === false || $ts > $maxTs) continue;

            // eigene API-Event-ID (Namespace "ergast_" gegen Kollisionen)
            $apiEventId = "ergast_{$year}_{$round}";
            $isPast = $ts < $now;
            if ($isPast) {
                // Vergangene Rennen: nur weiterverarbeiten wenn schon in DB
                $checkExist->execute([$apiEventId]);
                if (!$checkExist->fetchColumn()) continue;
            }

            // Sieger + 2. Platz aus den Results auslesen (falls Rennen schon gelaufen)
            $winner = null; $second = null;
            $results = $byRound[$round]['Results'] ?? [];
            if (isset($results[0]['Driver'])) {
                $d = $results[0]['Driver'];
                $winner = trim(($d['givenName'] ?? '') . ' ' . ($d['familyName'] ?? ''));
            }
            if (isset($results[1]['Driver'])) {
                $d = $results[1]['Driver'];
                $second = trim(($d['givenName'] ?? '') . ' ' . ($d['familyName'] ?? ''));
            }

            // home/away als "GP-Name - Sieger/P2 (Fahrer)" abbilden
            $homeName = $name . ' - Sieger'    . ($winner ? " ($winner)" : '');
            $awayName = $name . ' - Podium 2.' . ($second ? " ($second)" : '');

            $n = [
                'home_name'    => mb_substr($homeName, 0, 120),  // VARCHAR(120)
                'away_name'    => mb_substr($awayName, 0, 120),
                'home_short'   => 'WIN',
                'away_short'   => 'P2',
                'datetime'     => $dt,
                // F1 hat kein klassisches Score - wir markieren "fertig" als 1:1
                'home_score'   => $winner ? 1 : null,
                'away_score'   => $winner ? 1 : null,
                'api_event_id' => $apiEventId,
            ];

            if ($winner) {
                // Rennen ist gefahren -> als finished speichern + Tipps auswerten
                $mid = $this->upsertFinished($leagueId, $n);
                if ($mid) { evaluate_match($mid); $updated++; }
            } else {
                // Rennen kommt noch -> upcoming
                $imported += $this->upsertUpcoming($leagueId, $n) ? 1 : 0;
            }
        }

        // last_sync setzen (fuer Cache-TTL)
        $pdo->prepare('UPDATE leagues SET last_sync = NOW() WHERE id = ?')->execute([$leagueId]);

        return [
            'source'     => "ergast f1/$year",
            'imported'   => $imported,
            'updated'    => $updated,
            'seen'       => $seen,
            'last_error' => $this->lastError,
        ];
    }
}
