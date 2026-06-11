<?php
require_once __DIR__ . '/SportApi.php';

/**
 * Tennis – Grand Slams via TheSportsDB
 *
 * Primäre Strategie: eventsday.php?d=DATE&s=Tennis
 *   → gibt ALLE Tennis-Events eines Tages zurück.
 *   → wird nach Turnier-Keywords gefiltert (Roland Garros, Wimbledon, …)
 *
 * Fallback (Saisonübersicht): alte Drei-Strategie-Methode bleibt erhalten.
 */
class TennisSport extends SportApi
{
    /** Keywords pro Turniername (lowercase) */
    private const KEYWORDS = [
        'roland garros'   => ['french open', 'roland garros', 'roland', 'french', 'paris'],
        'french open'     => ['french open', 'roland garros', 'roland', 'french', 'paris'],
        'wimbledon'       => ['wimbledon', 'all england', 'london'],
        'australian open' => ['australian open', 'australian', 'ausopen', 'melbourne'],
        'us open'         => ['us open', 'usopen', 'flushing', 'new york'],
    ];

    /** Suchbegriffe für searchleagues / searchevents (Fallback) */
    private const SEARCH_TERMS = [
        'roland garros'   => ['Roland Garros', 'French Open'],
        'french open'     => ['French Open', 'Roland Garros'],
        'wimbledon'       => ['Wimbledon'],
        'australian open' => ['Australian Open'],
        'us open'         => ['US Open'],
    ];

    private const ATP_TOUR_ID = '4464';

    // ----------------------------------------------------------------
    // Haupt-Einstiegspunkt: Spiele eines Tages
    // ----------------------------------------------------------------

    /**
     * Überschreibt die Basisklasse: holt Tennis-Events per eventsday.php.
     */
    public function getMatchesForDay(int $leagueId, string $date): array
    {
        $this->syncDay($leagueId, $date);

        $stmt = db()->prepare(
            'SELECT m.* FROM matches m
              WHERE m.league_id = ? AND DATE(m.match_datetime) = ?
              ORDER BY m.match_datetime'
        );
        $stmt->execute([$leagueId, $date]);
        return $stmt->fetchAll();
    }

    // ----------------------------------------------------------------
    // ensureFresh: synct heutige + nahe Tage + Saisonübersicht
    // ----------------------------------------------------------------

    public function ensureFresh(int $leagueId, bool $force = false): void
    {
        $row = db()->prepare('SELECT api_id, season, last_sync FROM leagues WHERE id = ?');
        $row->execute([$leagueId]);
        $league = $row->fetch();
        if (!$league || !$league['api_id']) return;

        $stale = $force
              || !$league['last_sync']
              || (time() - strtotime($league['last_sync'])) > $this->cacheTtlSeconds;

        if (!$stale) return;

        // Synct: gestern, heute + nächste 6 Tage (8 Aufrufe × ~120ms ≈ 1s)
        $today = date('Y-m-d');
        for ($i = -1; $i <= 6; $i++) {
            $d = date('Y-m-d', strtotime("$today +$i days"));
            $this->syncDay($leagueId, $d, $force);
            if ($i < 6) usleep(120000);
        }

        // Saisonübersicht-Daten (im Hintergrund, Fehler ignorieren)
        try {
            $this->syncLeague($leagueId, (string)$league['api_id'], (string)($league['season'] ?? ''));
        } catch (Throwable $ignored) {}

        db()->prepare('UPDATE leagues SET last_sync = NOW() WHERE id = ?')->execute([$leagueId]);
    }

    // ----------------------------------------------------------------
    // syncDay: ein Datum via eventsday.php
    // ----------------------------------------------------------------

    private function syncDay(int $leagueId, string $date, bool $force = false): void
    {
        $pdo = db();

        if (!$force) {
            // Diesen Tag überspringen wenn schon Daten vorhanden
            $chk = $pdo->prepare(
                'SELECT COUNT(*) FROM matches WHERE league_id = ? AND DATE(match_datetime) = ?'
            );
            $chk->execute([$leagueId, $date]);
            if ((int)$chk->fetchColumn() > 0) return;
        }

        $keywords = $this->keywordsForLeague($leagueId);

        // --- Strategie A: eventsday.php?d=DATE&s=Tennis ---
        $data   = $this->tsdbCall('eventsday.php?d=' . urlencode($date) . '&s=Tennis');
        $events = $data['events'] ?? [];

        // --- Strategie B: ohne Sport-Filter, dann manuell filtern ---
        if (empty($events)) {
            $data2  = $this->tsdbCall('eventsday.php?d=' . urlencode($date));
            $all    = $data2['events'] ?? [];
            $events = array_values(array_filter($all, function ($ev) {
                return str_contains(strtolower($ev['strSport'] ?? ''), 'tennis');
            }));
        }

        $now   = time();
        $maxTs = strtotime($this->maxDate . ' 23:59:59');

        foreach ($events as $ev) {
            if (!$this->eventMatchesKeywords($ev, $keywords)) continue;
            $n = $this->parseEvent($ev);
            if (!$n) continue;
            $ts = strtotime($n['datetime']);
            if ($ts === false || $ts > $maxTs) continue;

            if ($n['home_score'] !== null && $n['away_score'] !== null) {
                $mid = $this->upsertFinished($leagueId, $n);
                if ($mid) evaluate_match($mid);
            } else {
                $this->upsertUpcoming($leagueId, $n);
            }
        }
    }

    // ----------------------------------------------------------------
    // syncLeague (Fallback / Saisonübersicht)
    // ----------------------------------------------------------------

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

        $keywords = $this->keywordsForLeague($leagueId);

        if (!str_starts_with($apiLeagueId, 'search:')) {
            // Normale Sync
            if ($season !== '') $add($this->fetchSeasonEvents($apiLeagueId, $season), 'season');
            $add($this->fetchUpcomingEvents($apiLeagueId), 'next');
            $add($this->fetchPastEvents($apiLeagueId),     'past');
        } else {
            $term     = trim(substr($apiLeagueId, 7));
            $termKey  = strtolower($term);
            $searches = self::SEARCH_TERMS[$termKey] ?? [$term];

            // Strategie 1: Liga-ID über searchleagues
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

            // Strategie 2: ATP-Tour-Filter
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
    // Helpers
    // ----------------------------------------------------------------

    private function keywordsForLeague(int $leagueId): array
    {
        $row = db()->prepare('SELECT api_id FROM leagues WHERE id = ?');
        $row->execute([$leagueId]);
        $apiId  = (string)($row->fetchColumn() ?? '');
        $term   = strtolower(str_starts_with($apiId, 'search:') ? trim(substr($apiId, 7)) : $apiId);
        return self::KEYWORDS[$term] ?? [strtolower($term)];
    }

    private function leagueMatchesKeywords(array $league, array $keywords): bool
    {
        $hay = strtolower(implode(' ', array_filter([
            $league['strLeague']          ?? null,
            $league['strLeagueAlternate'] ?? null,
            $league['strSport']           ?? null,
            $league['strCountry']         ?? null,
        ])));
        foreach ($keywords as $kw) {
            if ($kw !== '' && str_contains($hay, $kw)) return true;
        }
        return false;
    }

    private function eventMatchesKeywords(array $ev, array $keywords): bool
    {
        $hay = strtolower(implode(' ', array_filter([
            $ev['strEvent']    ?? null,
            $ev['strFilename'] ?? null,
            $ev['strLeague']   ?? null,
            $ev['strSeason']   ?? null,
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
