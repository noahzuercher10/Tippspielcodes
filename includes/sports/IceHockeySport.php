<?php
require_once __DIR__ . '/SportApi.php';

/**
 * ============================================================
 * Eishockey-Klasse
 * ------------------------------------------------------------
 * Erbt von SportApi (TheSportsDB-Sync ist Standard) und ergaenzt
 * fuer die NHL die offizielle NHL Stats API
 * (https://api-web.nhle.com/, kein Key noetig).
 *
 * Mapping nach TheSportsDB-Liga-ID:
 *   4380 (NHL)         -> api-web.nhle.com  (voller Spielplan)
 *   4423 (NL Schweiz)  -> nur TheSportsDB
 * ============================================================
 */
class IceHockeySport extends SportApi
{
    /**
     * Bekannte Team-Kuerzel. Wird in shortName() aufgeloest und
     * sorgt fuer korrekte Anzeige (statt "TOR" wirklich "TOR" usw.).
     */
    private static array $aliases = [
        // Schweizer National League
        'sc bern'             => 'SCB',
        'zsc lions'           => 'ZSC',
        'ev zug'              => 'EVZ',
        'hc davos'            => 'HCD',
        // NHL (Auszug)
        'toronto maple leafs' => 'TOR',
        'boston bruins'       => 'BOS',
        'edmonton oilers'     => 'EDM',
        'new york rangers'    => 'NYR',
        'tampa bay lightning' => 'TBL',
    ];

    /**
     * Override: zuerst im Alias-Map nachschlagen, sonst Parent-Default
     * (erste 3 Buchstaben).
     */
    protected function shortName(string $name): string
    {
        $key = mb_strtolower($name);
        if (isset(self::$aliases[$key])) return self::$aliases[$key];
        return parent::shortName($name);
    }

    /**
     * Override syncLeague: erst TheSportsDB ueber Parent, dann
     * fuer die NHL zusaetzlich api-web.nhle.com.
     */
    public function syncLeague(int $leagueId, string $apiLeagueId, string $season = ''): array
    {
        // 1) Standard-Sync ueber TheSportsDB (Parent-Klasse)
        $res = parent::syncLeague($leagueId, $apiLeagueId, $season);

        // 2) Wenn NHL: zusaetzlich offizielle NHL API
        if ($apiLeagueId === '4380') {
            $s = $this->syncFromNhleApi($leagueId);
            // Stats in das Ergebnis mergen
            $res['source']   = ($res['source'] ?? '') . ', nhle=' . $s['seen'];
            $res['imported'] = ($res['imported'] ?? 0) + $s['imported'];
            $res['updated']  = ($res['updated']  ?? 0) + $s['updated'];
            $res['seen']     = ($res['seen']     ?? 0) + $s['seen'];
        }
        return $res;
    }

    /**
     * Holt den NHL-Spielplan via api-web.nhle.com.
     * Die API liefert immer einen 7-Tages-Block; via nextStartDate
     * arbeiten wir uns wochenweise vor.
     */
    private function syncFromNhleApi(int $leagueId): array
    {
        $stats = ['seen'=>0,'imported'=>0,'updated'=>0];
        $now   = time();
        $maxTs = strtotime($this->maxDate . ' 23:59:59');

        // Start: heute. Iteriere weiter bis maxDate.
        $cursor = date('Y-m-d');
        $hardLimit = 30;             // max. 30 Wochen ~= 7 Monate
        $iter = 0;
        $checkExist = db()->prepare('SELECT id FROM matches WHERE api_event_id = ?');

        while ($iter < $hardLimit && strtotime($cursor) <= $maxTs) {
            // NHL-Endpoint: liefert die naechsten 7 Tage
            $url = "https://api-web.nhle.com/v1/schedule/" . urlencode($cursor);
            $raw = $this->httpGet($url);
            if (!$raw) break;
            $data = json_decode($raw, true);
            if (!is_array($data)) break;

            // gameWeek = Array von Tagen; jeder Tag hat games = Array von Matches
            foreach (($data['gameWeek'] ?? []) as $day) {
                foreach (($day['games'] ?? []) as $g) {
                    $stats['seen']++;
                    $gid  = $g['id'] ?? null;
                    $utc  = $g['startTimeUTC'] ?? '';
                    // NHL-Teams: placeName + name = "Toronto Maple Leafs"
                    $home = trim((string)($g['homeTeam']['placeName']['default'] ?? '')
                              . ' ' . ($g['homeTeam']['name']['default'] ?? ''));
                    $away = trim((string)($g['awayTeam']['placeName']['default'] ?? '')
                              . ' ' . ($g['awayTeam']['name']['default'] ?? ''));
                    if (!$gid || !$utc || $home === '' || $away === '') continue;

                    // UTC -> lokale Zeit (DATETIME)
                    $ts = strtotime($utc);
                    if ($ts === false || $ts > $maxTs) continue;
                    $dt = date('Y-m-d H:i:s', $ts);

                    // Score + Status
                    $hs = $g['homeTeam']['score'] ?? null;
                    $as = $g['awayTeam']['score'] ?? null;
                    $finished = (($g['gameState'] ?? '') === 'FINAL' || ($g['gameState'] ?? '') === 'OFF');

                    // Eigene API-Event-ID (Namespace nhle_ verhindert Kollision mit TheSportsDB)
                    $apiEventId = "nhle_{$gid}";
                    // Vergangene Spiele nur weiter verarbeiten wenn schon in DB
                    if ($ts < $now) {
                        $checkExist->execute([$apiEventId]);
                        if (!$checkExist->fetchColumn()) continue;
                    }

                    // Datensatz fuer upsert
                    $n = [
                        'home_name'  => $home,
                        'away_name'  => $away,
                        'home_short' => $g['homeTeam']['abbrev'] ?? $this->shortName($home),
                        'away_short' => $g['awayTeam']['abbrev'] ?? $this->shortName($away),
                        'datetime'   => $dt,
                        'home_score' => $finished ? (int)$hs : null,
                        'away_score' => $finished ? (int)$as : null,
                        'api_event_id' => $apiEventId,
                    ];
                    if ($n['home_score'] !== null && $n['away_score'] !== null) {
                        // Finished -> evaluate_match feuert ggf. Tipp-Auswertung
                        $mid = $this->upsertFinished($leagueId, $n);
                        if ($mid) { evaluate_match($mid); $stats['updated']++; }
                    } else {
                        $stats['imported'] += $this->upsertUpcoming($leagueId, $n) ? 1 : 0;
                    }
                }
            }

            // Pagination: NHL liefert nextStartDate fuer die naechste Woche
            $next = $data['nextStartDate'] ?? '';
            if (!$next || $next === $cursor) break;
            $cursor = $next;
            $iter++;
            usleep(150000);                // 0.15 s Pause zwischen Calls
        }
        return $stats;
    }
}
