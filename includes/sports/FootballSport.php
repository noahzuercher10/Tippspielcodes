<?php
require_once __DIR__ . '/SportApi.php';
require_once __DIR__ . '/../../config/api_keys.php';

/**
 * Fussball - kombiniert mehrere kostenlose APIs:
 *
 *   - TheSportsDB        : alle Ligen (Standard, immer)
 *   - OpenLigaDB         : deutsche Ligen + UCL (kein Key)
 *   - football-data.org  : PL, La Liga, Serie A, BL, CL, Ligue 1
 *                          (Free-API-Key in config/api_keys.php)
 */
class FootballSport extends SportApi
{
    /** TheSportsDB-ID -> OpenLigaDB shortcut */
    private static array $oldbMap = [
        '4331' => 'bl1',  // Bundesliga
        '4329' => 'bl2',  // 2. Bundesliga
        '4480' => 'ucl',  // Champions League
    ];

    /** TheSportsDB-ID -> football-data.org Competition-Code */
    private static array $fdoMap = [
        '4328' => 'PL',   // Premier League
        '4335' => 'PD',   // Primera Division (La Liga)
        '4332' => 'SA',   // Serie A
        '4331' => 'BL1',  // Bundesliga
        '4480' => 'CL',   // Champions League
    ];

    private static array $aliases = [
        'fc bayern munich' => 'FCB',  'bayern munich' => 'FCB',  'bayern münchen' => 'FCB',
        'borussia dortmund' => 'BVB', 'rb leipzig' => 'RBL',
        'bayer leverkusen' => 'B04',  'manchester city' => 'MCI',
        'manchester united' => 'MUN', 'liverpool' => 'LIV',
        'arsenal' => 'ARS',           'chelsea' => 'CHE',
        'real madrid' => 'RMA',       'fc barcelona' => 'BAR',
        'atletico madrid' => 'ATM',   'fc basel' => 'BAS',
        'fc zurich' => 'FCZ',         'bsc young boys' => 'YB',
        'fc st. gallen' => 'FCSG',    'fc luzern' => 'FCL',
        'servette fc' => 'SER',       'eintracht frankfurt' => 'SGE',
        'sc freiburg' => 'SCF',       'vfb stuttgart' => 'VFB',
        'vfl wolfsburg' => 'WOB',     'borussia mönchengladbach' => 'BMG',
        'tsg hoffenheim' => 'TSG',    '1. fc heidenheim' => 'HDH',
        '1. fsv mainz 05' => 'M05',   'werder bremen' => 'SVW',
        'fc augsburg' => 'FCA',       '1. fc union berlin' => 'FCU',
        'fc st. pauli' => 'STP',      'holstein kiel' => 'KSV',
    ];

    protected function shortName(string $name): string
    {
        $key = mb_strtolower($name);
        if (isset(self::$aliases[$key])) return self::$aliases[$key];
        return parent::shortName($name);
    }

    public function syncLeague(int $leagueId, string $apiLeagueId, string $season = ''): array
    {
        $res = parent::syncLeague($leagueId, $apiLeagueId, $season);

        $year = '';
        if (preg_match('/(\d{4})/', $season, $m)) $year = $m[1];
        if (!$year) $year = (string)(int)date('Y');

        // OpenLigaDB
        if (isset(self::$oldbMap[$apiLeagueId])) {
            $s = $this->syncFromOpenLigaDB($leagueId, self::$oldbMap[$apiLeagueId], $year);
            $res['source']   = ($res['source'] ?? '') . ', oldb=' . $s['seen'];
            $res['imported'] = ($res['imported'] ?? 0) + $s['imported'];
            $res['updated']  = ($res['updated']  ?? 0) + $s['updated'];
            $res['seen']     = ($res['seen']     ?? 0) + $s['seen'];
        }

        // football-data.org
        $fdoCode = self::$fdoMap[$apiLeagueId] ?? null;
        $fdoKey  = trim((string)(defined('FOOTBALL_DATA_ORG_KEY') ? FOOTBALL_DATA_ORG_KEY : ''));
        if ($fdoCode && $fdoKey !== '') {
            $s = $this->syncFromFootballDataOrg($leagueId, $fdoCode, $year, $fdoKey);
            $res['source']   = ($res['source'] ?? '') . ', fdo=' . $s['seen'];
            $res['imported'] = ($res['imported'] ?? 0) + $s['imported'];
            $res['updated']  = ($res['updated']  ?? 0) + $s['updated'];
            $res['seen']     = ($res['seen']     ?? 0) + $s['seen'];
        }
        return $res;
    }

    protected function syncFromOpenLigaDB(int $leagueId, string $oldbKey, string $year): array
    {
        $stats = ['seen'=>0,'imported'=>0,'updated'=>0];
        $url = "https://api.openligadb.de/getmatchdata/" . urlencode($oldbKey) . "/" . urlencode($year);
        $raw = $this->httpGet($url);
        if (!$raw) return $stats;
        $data = json_decode($raw, true);
        if (!is_array($data)) return $stats;

        $now = time();
        $maxTs = strtotime($this->maxDate . ' 23:59:59');
        $checkExist = db()->prepare('SELECT id FROM matches WHERE api_event_id = ?');

        foreach ($data as $m) {
            $stats['seen']++;
            $matchId = $m['matchID'] ?? null;
            $dt      = $m['matchDateTime'] ?? '';
            $home    = trim((string)($m['team1']['teamName'] ?? ''));
            $away    = trim((string)($m['team2']['teamName'] ?? ''));
            if (!$matchId || !$dt || $home === '' || $away === '') continue;

            $dt = preg_replace('/[+-]\d{2}:?\d{2}$/', '', str_replace('T', ' ', $dt));
            $ts = strtotime($dt);
            if ($ts === false || $ts > $maxTs) continue;

            $hs = null; $as = null;
            if (!empty($m['matchIsFinished'])) {
                foreach (($m['matchResults'] ?? []) as $r) {
                    $rn = strtolower((string)($r['resultName'] ?? ''));
                    if ($rn === 'endergebnis' || $rn === 'final result') {
                        $hs = (int)($r['pointsTeam1'] ?? 0);
                        $as = (int)($r['pointsTeam2'] ?? 0);
                        break;
                    }
                }
                if ($hs === null && !empty($m['matchResults'])) {
                    $last = end($m['matchResults']);
                    if (isset($last['pointsTeam1'], $last['pointsTeam2'])) {
                        $hs = (int)$last['pointsTeam1']; $as = (int)$last['pointsTeam2'];
                    }
                }
            }

            $apiEventId = "oldb_{$oldbKey}_{$matchId}";
            if ($ts < $now) {
                $checkExist->execute([$apiEventId]);
                if (!$checkExist->fetchColumn()) continue;
            }

            $n = [
                'home_name'  => $home, 'away_name'  => $away,
                'home_short' => $this->shortName($home), 'away_short' => $this->shortName($away),
                'datetime'   => $dt,
                'home_score' => $hs, 'away_score' => $as,
                'api_event_id' => $apiEventId,
                'home_badge' => $this->lookupBadgeFromDB($home),
                'away_badge' => $this->lookupBadgeFromDB($away),
            ];
            if ($n['home_score'] !== null && $n['away_score'] !== null) {
                $mid = $this->upsertFinished($leagueId, $n);
                if ($mid) { evaluate_match($mid); $stats['updated']++; }
            } else {
                $stats['imported'] += $this->upsertUpcoming($leagueId, $n) ? 1 : 0;
            }
        }
        return $stats;
    }

    protected function syncFromFootballDataOrg(int $leagueId, string $code, string $year, string $key): array
    {
        $stats = ['seen'=>0,'imported'=>0,'updated'=>0];
        $url = "https://api.football-data.org/v4/competitions/" . urlencode($code) . "/matches?season=" . urlencode($year);
        $raw = $this->httpGet($url, ['X-Auth-Token: ' . $key]);
        if (!$raw) return $stats;
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['matches'])) return $stats;

        $now = time();
        $maxTs = strtotime($this->maxDate . ' 23:59:59');
        $checkExist = db()->prepare('SELECT id FROM matches WHERE api_event_id = ?');

        foreach ($data['matches'] as $m) {
            $stats['seen']++;
            $matchId = $m['id'] ?? null;
            $home    = trim((string)($m['homeTeam']['name'] ?? ''));
            $away    = trim((string)($m['awayTeam']['name'] ?? ''));
            $utc     = $m['utcDate'] ?? '';
            if (!$matchId || $home === '' || $away === '' || $utc === '') continue;

            $clean = str_replace(['T','Z'], [' ',''], $utc);
            $ts = strtotime($clean . ' UTC');
            if ($ts === false || $ts > $maxTs) continue;
            $dt = date('Y-m-d H:i:s', $ts);

            $hs = $m['score']['fullTime']['home'] ?? null;
            $as = $m['score']['fullTime']['away'] ?? null;
            $isFinished = (($m['status'] ?? '') === 'FINISHED');

            $apiEventId = "fdo_{$code}_{$matchId}";
            if ($ts < $now) {
                $checkExist->execute([$apiEventId]);
                if (!$checkExist->fetchColumn()) continue;
            }

            $n = [
                'home_name'  => $home, 'away_name'  => $away,
                'home_short' => $this->shortName($home), 'away_short' => $this->shortName($away),
                'datetime'   => $dt,
                'home_score' => $isFinished ? (int)$hs : null,
                'away_score' => $isFinished ? (int)$as : null,
                'api_event_id' => $apiEventId,
                'home_badge' => $this->lookupBadgeFromDB($home),
                'away_badge' => $this->lookupBadgeFromDB($away),
            ];
            if ($n['home_score'] !== null && $n['away_score'] !== null) {
                $mid = $this->upsertFinished($leagueId, $n);
                if ($mid) { evaluate_match($mid); $stats['updated']++; }
            } else {
                $stats['imported'] += $this->upsertUpcoming($leagueId, $n) ? 1 : 0;
            }
        }
        return $stats;
    }

    /** httpGet mit optionalen Headern (z.B. X-Auth-Token). */
    protected function httpGet(string $url, array $headers = []): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $opts = [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
                CURLOPT_USERAGENT => 'TippspielApp/1.0 (ZbW 2608)',
                CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
            ];
            if ($headers) $opts[CURLOPT_HTTPHEADER] = $headers;
            curl_setopt_array($ch, $opts);
            $body = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);
            if ($body === false || $http >= 400) {
                $this->lastError = "cURL HTTP $http $err on $url";
                return null;
            }
            return $body;
        }
        if (ini_get('allow_url_fopen')) {
            $hdrLines = $headers ? implode("\r\n", $headers) : '';
            $ctx = stream_context_create([
                'http' => [
                    'timeout'    => 15,
                    'user_agent' => 'TippspielApp/1.0',
                    'header'     => $hdrLines,
                ],
                'ssl'  => ['verify_peer'=>false, 'verify_peer_name'=>false],
            ]);
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false) {
                $this->lastError = 'file_get_contents fehlgeschlagen fuer ' . $url;
                return null;
            }
            return $raw;
        }
        $this->lastError = 'Weder cURL noch allow_url_fopen verfuegbar.';
        return null;
    }
}
