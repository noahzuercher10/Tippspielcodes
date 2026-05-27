<?php
require_once __DIR__ . '/SportApi.php';

/**
 * Formel 1 – Ergast/Jolpica API
 * Erstellt pro Rennen 10 Sub-Matches: P1…P10 (Podium + Top-10-Tipping).
 * api_event_id = "ergast_{year}_{round}_P1" … "_P10"
 */
class Formula1Sport extends SportApi
{
    private const POS_LABELS = [
        'P1'  => '1. Platz',
        'P2'  => '2. Platz',
        'P3'  => '3. Platz',
        'P4'  => '4. Platz',
        'P5'  => '5. Platz',
        'P6'  => '6. Platz',
        'P7'  => '7. Platz',
        'P8'  => '8. Platz',
        'P9'  => '9. Platz',
        'P10' => '10. Platz',
    ];

    public function syncLeague(int $leagueId, string $apiLeagueId, string $season = ''): array
    {
        $imported = 0; $updated = 0; $seen = 0;

        $year = '';
        if (preg_match('/(\d{4})/', $season, $m)) $year = $m[1];
        if (!$year) $year = date('Y');

        $rawSched = $this->httpGet("https://api.jolpi.ca/ergast/f1/$year.json?limit=50");
        if (!$rawSched) {
            return ['source'=>"ergast f1/$year",'imported'=>0,'updated'=>0,'seen'=>0,'last_error'=>$this->lastError];
        }
        $data  = json_decode($rawSched, true);
        $races = $data['MRData']['RaceTable']['Races'] ?? [];

        // Ergebnisse nach Runde indexieren
        $byRound = [];
        $rawRes  = $this->httpGet("https://api.jolpi.ca/ergast/f1/$year/results.json?limit=500");
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

            // Top-10-Fahrer aus Ergebnissen
            $results = $byRound[$round]['Results'] ?? [];
            $drivers = array_fill(0, 10, null);
            for ($i = 0; $i < 10 && $i < count($results); $i++) {
                $d = $results[$i]['Driver'] ?? null;
                if ($d) {
                    $drivers[$i] = trim(($d['givenName'] ?? '') . ' ' . ($d['familyName'] ?? ''));
                }
            }

            foreach (self::POS_LABELS as $pos => $label) {
                $idx        = (int)ltrim($pos, 'P') - 1;
                $driver     = $drivers[$idx] ?? null;
                $driverStr  = $driver ? " ($driver)" : '';
                $homeName   = mb_substr("$name – $label$driverStr", 0, 120);
                $apiEventId = "ergast_{$year}_{$round}_{$pos}";
                $isFinished = ($driver !== null);

                $n = [
                    'home_name'    => $homeName,
                    'away_name'    => mb_substr($name, 0, 120),
                    'home_short'   => $pos,
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
