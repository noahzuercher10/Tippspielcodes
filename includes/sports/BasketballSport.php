<?php
require_once __DIR__ . '/SportApi.php';
require_once __DIR__ . '/../../config/api_keys.php';

/**
 * ============================================================
 * Basketball-Klasse
 * ------------------------------------------------------------
 * Erbt von SportApi (TheSportsDB-Sync) und ergaenzt fuer die NBA
 * die balldontlie.io-API.
 * Diese braucht einen kostenlosen API-Key, der in
 * config/api_keys.php als BALLDONTLIE_KEY eingetragen wird.
 *
 * Mapping nach TheSportsDB-Liga-ID:
 *   4387 (NBA)        -> balldontlie.io (wenn Key gesetzt)
 *   4408 (EuroLeague) -> nur TheSportsDB
 * ============================================================
 */
class BasketballSport extends SportApi
{
    /** Bekannte Team-Kuerzel. */
    private static array $aliases = [
        'la lakers' => 'LAL', 'los angeles lakers' => 'LAL',
        'boston celtics' => 'BOS', 'golden state warriors' => 'GSW',
        'miami heat' => 'MIA', 'denver nuggets' => 'DEN',
        'milwaukee bucks' => 'MIL', 'philadelphia 76ers' => 'PHI',
        'real madrid' => 'RMA', 'fc barcelona' => 'BAR',
        'olympiacos piraeus' => 'OLY',
    ];

    protected function shortName(string $name): string
    {
        $key = mb_strtolower($name);
        if (isset(self::$aliases[$key])) return self::$aliases[$key];
        return parent::shortName($name);
    }

    /**
     * Override: erst TheSportsDB-Sync via Parent, dann ggf.
     * NBA-Daten via balldontlie.io.
     */
    public function syncLeague(int $leagueId, string $apiLeagueId, string $season = ''): array
    {
        // 1) Standard-Sync ueber TheSportsDB
        $res = parent::syncLeague($leagueId, $apiLeagueId, $season);

        // 2) Zusatz nur bei NBA + wenn Key gesetzt
        $key = trim((string)(defined('BALLDONTLIE_KEY') ? BALLDONTLIE_KEY : ''));
        if ($apiLeagueId === '4387' && $key !== '') {
            // Jahr extrahieren ("2025-2026" -> "2025")
            $year = '';
            if (preg_match('/(\d{4})/', $season, $m)) $year = $m[1];
            if (!$year) $year = (string)(int)date('Y');

            $s = $this->syncFromBallDontLie($leagueId, $year, $key);
            $res['source']   = ($res['source'] ?? '') . ', bdl=' . $s['seen'];
            $res['imported'] = ($res['imported'] ?? 0) + $s['imported'];
            $res['updated']  = ($res['updated']  ?? 0) + $s['updated'];
            $res['seen']     = ($res['seen']     ?? 0) + $s['seen'];
        }
        return $res;
    }

    /**
     * NBA-Spiele via balldontlie.io. Pagination ueber cursor.
     */
    private function syncFromBallDontLie(int $leagueId, string $year, string $key): array
    {
        $stats = ['seen'=>0,'imported'=>0,'updated'=>0];
        $now = time();
        $maxTs = strtotime($this->maxDate . ' 23:59:59');
        $checkExist = db()->prepare('SELECT id FROM matches WHERE api_event_id = ?');

        // Pagination: per_page=100, weiter ueber cursor
        $cursor = '';
        $iter = 0;
        while ($iter < 30) {                  // max. 30 Pages = 3000 Spiele
            $url = "https://api.balldontlie.io/v1/games?seasons[]=" . urlencode($year)
                 . "&per_page=100" . ($cursor !== '' ? "&cursor=" . urlencode($cursor) : '');
            // balldontlie braucht "Authorization: <KEY>" Header
            $raw = $this->httpGet($url, ['Authorization: ' . $key]);
            if (!$raw) break;
            $data = json_decode($raw, true);
            if (!is_array($data) || empty($data['data'])) break;

            foreach ($data['data'] as $g) {
                $stats['seen']++;
                $gid  = $g['id'] ?? null;
                $date = $g['date'] ?? '';
                $st   = $g['status'] ?? '';
                $home = trim((string)($g['home_team']['full_name'] ?? ''));
                $away = trim((string)($g['visitor_team']['full_name'] ?? ''));
                if (!$gid || !$date || !$home || !$away) continue;

                $ts = strtotime($date);
                if ($ts === false || $ts > $maxTs) continue;
                $dt = date('Y-m-d H:i:s', $ts);

                $hs = $g['home_team_score'] ?? 0;
                $as = $g['visitor_team_score'] ?? 0;
                // finished: Status "Final" ODER Punktzahl != 0
                $finished = (strtolower($st) === 'final') || ($hs > 0 || $as > 0);

                $apiEventId = "bdl_{$gid}";    // eigener Namespace
                if ($ts < $now) {
                    $checkExist->execute([$apiEventId]);
                    if (!$checkExist->fetchColumn()) continue;
                }

                $n = [
                    'home_name'  => $home,
                    'away_name'  => $away,
                    'home_short' => $g['home_team']['abbreviation'] ?? $this->shortName($home),
                    'away_short' => $g['visitor_team']['abbreviation'] ?? $this->shortName($away),
                    'datetime'   => $dt,
                    'home_score' => $finished ? (int)$hs : null,
                    'away_score' => $finished ? (int)$as : null,
                    'api_event_id' => $apiEventId,
                ];
                if ($n['home_score'] !== null && $n['away_score'] !== null) {
                    $mid = $this->upsertFinished($leagueId, $n);
                    if ($mid) { evaluate_match($mid); $stats['updated']++; }
                } else {
                    $stats['imported'] += $this->upsertUpcoming($leagueId, $n) ? 1 : 0;
                }
            }

            // Naechste Page via cursor (von balldontlie geliefert)
            $cursor = $data['meta']['next_cursor'] ?? '';
            if (!$cursor) break;
            $iter++;
            usleep(150000);
        }
        return $stats;
    }
}
