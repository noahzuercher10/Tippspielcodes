<?php
require_once __DIR__ . '/SportApi.php';

/**
 * Formel 1 – Ergast/Jolpica API
 * Erstellt pro Rennen 3 Sub-Matches: P1, P2, P3 (Podium-Tipping).
 */
class Formula1Sport extends SportApi
{
    public function syncLeague(int $leagueId, string $apiLeagueId, string $season = ''): array
    {
        $imported = 0; $updated = 0; $seen = 0;

        $year = '';
        if (preg_match('/(\d{4})/', $season, $m)) $year = $m[1];
        if (!$year) $year = date('Y');

        $rawSched = $this->httpGet("https://api.jolpi.ca/ergast/f1/$year.json?limit=50");
        if (!$rawSched) {
            return ['source'=>'ergast', 'imported'=>0, 'updated'=>0, 'seen'=>0, 'last_error'=>$this->lastError];
        }
        $data  = json_decode($rawSched, true);
        $races = $data['MRData']['RaceTable']['Races'] ?? [];

        $byRound = [];
        $rawRes  = $this->httpGet("https://api.jolpi.ca/ergast/f1/$year/results.json?limit=300");
        if ($rawRes) {
            $dr = json_decode($rawRes, true);
            foreach (($dr['MRData']['RaceTable']['Races'] ?? []) as $r) {
                $byRound[(string)$r['round']] = $r;
            }
        }

        $now   = time();
        $maxTs = strtotime($this->maxDate . ' 23:59:59');
        $pdo   = db();

        foreach ($races as $r) {
            $seen++;
            $round = (string)($r['round'] ?? '');
            $name  = $r['raceName'] ?? "Runde $round";
            $date  = $r['date']     ?? '';
            $time  = rtrim($r['time'] ?? '14:00:00Z', 'Z');
            if ($date === '' || $round === '') continue;
            $dt = trim("$date $time");
            $ts = strtotime($dt);
            if ($ts === false || $ts > $maxTs) continue;

            // Fahrer aus Results
            $results = $byRound[$round]['Results'] ?? [];
            $drivers = [null, null, null];
            for ($i = 0; $i < 3 && $i < count($results); $i++) {
                $d = $results[$i]['Driver'] ?? null;
                if ($d) $drivers[$i] = trim(($d['givenName'] ?? '') . ' ' . ($d['familyName'] ?? ''));
            }

            // 3 Sub-Matches: P1, P2, P3
            $positions = [
                ['pos' => 'P1', 'label' => 'Sieger',    'driver' => $drivers[0]],
                ['pos' => 'P2', 'label' => 'Podium 2.', 'driver' => $drivers[1]],
                ['pos' => 'P3', 'label' => 'Podium 3.', 'driver' => $drivers[2]],
            ];

            foreach ($positions as $p) {
                $driverStr  = $p['driver'] ? " ({$p['driver']})" : '';
                $homeName   = mb_substr("$name – {$p['label']}$driverStr", 0, 120);
                $awayName   = mb_substr($name, 0, 120);
                $apiEventId = "ergast_{$year}_{$round}_{$p['pos']}";
                $isFinished = ($p['driver'] !== null);

                $n = [
                    'home_name'    => $homeName,
                    'away_name'    => $awayName,
                    'home_short'   => $p['pos'],
                    'away_short'   => 'F1',
                    'home_badge'   => null,
                    'away_badge'   => null,
                    'datetime'     => $dt,
                    'home_score'   => $isFinished ? 1 : null,
                    'away_score'   => $isFinished ? 0 : null,
                    'api_event_id' => $apiEventId,
                ];

                if ($isFinished) {
                    $mid = $this->upsertFinished($leagueId, $n);
                    if ($mid) { evaluate_match($mid); $updated++; }
                } else {
                    $imported += $this->upsertUpcoming($leagueId, $n) ? 1 : 0;
                }
            }
        }

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
