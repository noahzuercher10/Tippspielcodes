<?php
require_once __DIR__ . '/SportApi.php';

/**
 * Tennis – ATP Grand Slams + ATP Tour
 *
 * Für Ligen mit api_id = 'search:Roland Garros' (etc.) werden drei Strategien
 * kombiniert, weil die freie TheSportsDB-API Turnier-Suche instabil ist:
 *
 * 1. searchleagues.php → echte League-ID finden → season/next/past abrufen
 * 2. searchevents.php  → mehrere Suchbegriffe (Synonyme) + mehrere Saisons
 * 3. ATP-Tour-Filter   → alle ATP-Tour-Events holen und nach Turnier filtern
 */
class TennisSport extends SportApi
{
    /** Synonyme für Turniernamen (lowercase) */
    private const KEYWORDS = [
        'roland garros'   => ['french open', 'roland garros', 'roland', 'french', 'paris'],
        'french open'     => ['french open', 'roland garros', 'roland', 'french', 'paris'],
        'wimbledon'       => ['wimbledon', 'all england'],
        'australian open' => ['australian open', 'australian', 'ausopen', 'melbourne'],
        'us open'         => ['us open', 'usopen', 'flushing'],
    ];

    /** Suchbegriffe für searchevents.php / searchleagues.php */
    private const SEARCH_TERMS = [
        'roland garros'   => ['Roland Garros', 'French Open'],
        'french open'     => ['French Open', 'Roland Garros'],
        'wimbledon'       => ['Wimbledon'],
        'australian open' => ['Australian Open'],
        'us open'         => ['US Open'],
    ];

    /** ATP-Tour-Liga-ID in TheSportsDB (Fallback-Quelle) */
    private const ATP_TOUR_ID = '4464';

    public function syncLeague(int $leagueId, string $apiLeagueId, string $season = ''): array
    {
        $pdo     = db();
        $merged  = [];
        $sources = [];
        $add = function (array $events, string $src) use (&$merged, &$sources) {
            $cnt = 0;
            foreach ($events as $ev) {
                $id = $ev['idEvent'] ?? null;
                if (!$id || isset($merged[$id])) continue;
                $merged[$id] = $ev;
                $cnt++;
            }
            if ($cnt > 0) $sources[] = "$src=$cnt";
        };

        if (!str_starts_with($apiLeagueId, 'search:')) {
            // Normale ATP-Tour-Sync (kein Runden-Iterator)
            if ($season !== '') {
                $add($this->fetchSeasonEvents($apiLeagueId, $season), 'season');
            }
            $add($this->fetchUpcomingEvents($apiLeagueId), 'next');
            $add($this->fetchPastEvents($apiLeagueId),     'past');
        } else {
            $term     = trim(substr($apiLeagueId, 7));
            $termKey  = strtolower($term);
            $keywords = self::KEYWORDS[$termKey]   ?? [strtolower($term)];
            $searches = self::SEARCH_TERMS[$termKey] ?? [$term];

            // --- Strategie 1: Liga-ID via searchleagues ermitteln ---
            foreach ($searches as $st) {
                usleep(120000);
                $lgsData = $this->tsdbCall('searchleagues.php?t=' . urlencode($st));
                foreach ($lgsData['leagues'] ?? [] as $l) {
                    if (!$this->leagueMatchesKeywords($l, $keywords)) continue;
                    $lid = (string)($l['idLeague'] ?? '');
                    if (!$lid) continue;
                    if ($season !== '') {
                        $add($this->fetchSeasonEvents($lid, $season), "lg$lid-season");
                        $prev = (string)((int)$season - 1);
                        if ($prev) $add($this->fetchSeasonEvents($lid, $prev), "lg$lid-prev");
                    }
                    $add($this->fetchUpcomingEvents($lid), "lg$lid-next");
                    $add($this->fetchPastEvents($lid),     "lg$lid-past");
                }
            }

            // --- Strategie 2: searchevents (mehrere Suchbegriffe × Saisons) ---
            foreach ($searches as $st) {
                $seasons = array_filter(
                    [$season, $season ? (string)((int)$season - 1) : ''],
                    fn($s) => $s !== ''
                );
                foreach ($seasons as $s) {
                    usleep(80000);
                    $res = $this->tsdbCall('searchevents.php?e=' . urlencode($st) . '&s=' . urlencode($s));
                    $add($res['event'] ?? [], "se:$st/$s");
                }
                usleep(80000);
                $res = $this->tsdbCall('searchevents.php?e=' . urlencode($st));
                $add($res['event'] ?? [], "se:$st");
            }

            // --- Strategie 3: ATP-Tour-Events holen und nach Turnier filtern ---
            if ($season !== '') {
                $atpSeason = $this->fetchSeasonEvents(self::ATP_TOUR_ID, $season);
                $f = array_filter($atpSeason, fn($ev) => $this->eventMatchesKeywords($ev, $keywords));
                $add(array_values($f), 'atp-season');
            }
            $atpNext = $this->fetchUpcomingEvents(self::ATP_TOUR_ID);
            $f = array_filter($atpNext, fn($ev) => $this->eventMatchesKeywords($ev, $keywords));
            $add(array_values($f), 'atp-next');

            $atpPast = $this->fetchPastEvents(self::ATP_TOUR_ID);
            $f = array_filter($atpPast, fn($ev) => $this->eventMatchesKeywords($ev, $keywords));
            $add(array_values($f), 'atp-past');
        }

        // --- Events verarbeiten ---
        $imported = 0; $updated = 0; $seen = 0;
        $now   = time();
        $maxTs = strtotime($this->maxDate . ' 23:59:59');

        foreach ($merged as $ev) {
            $seen++;
            $n = $this->parseEvent($ev);
            if (!$n) continue;
            $ts = strtotime($n['datetime']);
            if ($ts === false || $ts > $maxTs) continue;

            if ($n['home_score'] !== null && $n['away_score'] !== null) {
                $mid = $this->upsertFinished($leagueId, $n);
                if ($mid) { evaluate_match($mid); $updated++; }
            } elseif ($ts >= $now) {
                $imported += $this->upsertUpcoming($leagueId, $n) ? 1 : 0;
            }
        }

        $pdo->prepare('UPDATE leagues SET last_sync = NOW() WHERE id = ?')->execute([$leagueId]);

        return [
            'source'     => implode(', ', $sources) ?: '(leer)',
            'imported'   => $imported,
            'updated'    => $updated,
            'seen'       => $seen,
            'last_error' => $this->lastError,
        ];
    }

    // ----------------------------------------------------------------

    private function leagueMatchesKeywords(array $league, array $keywords): bool
    {
        $hay = strtolower(implode(' ', array_filter([
            $league['strLeague']      ?? null,
            $league['strLeagueAlternate'] ?? null,
            $league['strSport']       ?? null,
            $league['strCountry']     ?? null,
        ])));
        foreach ($keywords as $kw) {
            if (str_contains($hay, $kw)) return true;
        }
        return false;
    }

    private function eventMatchesKeywords(array $ev, array $keywords): bool
    {
        $hay = strtolower(implode(' ', array_filter([
            $ev['strEvent']    ?? null,
            $ev['strFilename'] ?? null,
            $ev['strLeague']   ?? null,
            $ev['strVenue']    ?? null,
            $ev['strCity']     ?? null,
            $ev['strCountry']  ?? null,
            $ev['strRound']    ?? null,
        ])));
        foreach ($keywords as $kw) {
            if ($kw !== '' && str_contains($hay, $kw)) return true;
        }
        return false;
    }

    // ----------------------------------------------------------------

    protected function parseEvent(array $ev): ?array
    {
        $home = trim((string)($ev['strHomeTeam'] ?? ''));
        $away = trim((string)($ev['strAwayTeam'] ?? ''));

        if (($home === '' || $away === '') && !empty($ev['strEvent'])) {
            if (preg_match('/^(.*?)\s+(vs\.?|vs|gegen|-)\s+(.*)$/i', (string)$ev['strEvent'], $m)) {
                $home = $home !== '' ? $home : trim($m[1]);
                $away = $away !== '' ? $away : trim($m[3]);
            }
        }
        if ($home === '' || $away === '') return null;

        $date = $ev['dateEvent'] ?? '';
        $time = substr((string)($ev['strTime'] ?? '00:00:00'), 0, 8) ?: '00:00:00';
        if ($date === '') return null;

        $hs = $ev['intHomeScore'] ?? null;
        $as = $ev['intAwayScore'] ?? null;

        $homeTeamId = (string)($ev['idHomeTeam'] ?? '');
        $awayTeamId = (string)($ev['idAwayTeam'] ?? '');
        $homeBadge  = $this->normBadge($ev['strHomeTeamBadge'] ?? null)
                   ?: $this->normBadge($ev['strThumb'] ?? null)
                   ?: $this->lookupTeamBadge($homeTeamId);
        $awayBadge  = $this->normBadge($ev['strAwayTeamBadge'] ?? null)
                   ?: $this->lookupTeamBadge($awayTeamId);

        return [
            'home_name'    => $home,
            'away_name'    => $away,
            'home_short'   => $this->shortName($home),
            'away_short'   => $this->shortName($away),
            'home_badge'   => $homeBadge,
            'away_badge'   => $awayBadge,
            'datetime'     => trim($date . ' ' . $time),
            'home_score'   => ($hs === null || $hs === '') ? null : (int)$hs,
            'away_score'   => ($as === null || $as === '') ? null : (int)$as,
            'api_event_id' => (string)($ev['idEvent'] ?? ''),
        ];
    }

    protected function shortName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        $last  = end($parts) ?: $name;
        return strtoupper(mb_substr($last, 0, 3));
    }
}
