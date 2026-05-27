<?php
require_once __DIR__ . '/SportApi.php';

/**
 * Tennis – ATP Tour + Grand Slams
 * Unterstützt zwei api_id-Formate:
 *   - Zahl (TheSportsDB-Liga-ID): normaler Sync
 *   - "search:X": Suche via searchevents.php nach Turniername X
 */
class TennisSport extends SportApi
{
    public function syncLeague(int $leagueId, string $apiLeagueId, string $season = ''): array
    {
        $pdo     = db();
        $merged  = [];
        $sources = [];

        $add = function(array $events, string $src) use (&$merged, &$sources) {
            $cnt = 0;
            foreach ($events as $ev) {
                $id = $ev['idEvent'] ?? null;
                if (!$id) continue;
                if (!isset($merged[$id])) { $merged[$id] = $ev; $cnt++; }
            }
            if ($cnt > 0) $sources[] = "$src=$cnt";
        };

        // Grand Slam via Turniername-Suche
        if (str_starts_with($apiLeagueId, 'search:')) {
            $term = substr($apiLeagueId, 7);
            // Aktuelle Saison + vergangene Saison suchen
            foreach ([$season, (string)((int)$season - 1)] as $s) {
                if ($s === '') continue;
                $res = $this->tsdbCall('searchevents.php?e=' . urlencode($term) . '&s=' . urlencode($s));
                $add($res['event'] ?? [], "search:$term");
            }
            // Zusätzlich: direkte Suche ohne Saison (gibt aktuellste Events)
            $res2 = $this->tsdbCall('searchevents.php?e=' . urlencode($term));
            $add($res2['event'] ?? [], "searchAll:$term");
        } else {
            // Normale TheSportsDB-Sync (kein Runden-Iterator für Tennis)
            if ($season !== '') {
                $add($this->fetchSeasonEvents($apiLeagueId, $season), 'season');
            }
            $add($this->fetchUpcomingEvents($apiLeagueId), 'next');
            $add($this->fetchPastEvents($apiLeagueId),     'past');
        }

        $imported = 0; $updated = 0; $seen = 0;
        $now      = time();
        $maxTs    = strtotime($this->maxDate . ' 23:59:59');

        foreach ($merged as $ev) {
            $seen++;
            $n = $this->parseEvent($ev);
            if (!$n) continue;
            $ts = strtotime($n['datetime']);
            if ($ts === false || $ts > $maxTs) continue;

            $isPast = $ts < $now;
            if ($n['home_score'] !== null && $n['away_score'] !== null) {
                $mid = $this->upsertFinished($leagueId, $n);
                if ($mid) { evaluate_match($mid); $updated++; }
            } elseif (!$isPast) {
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
