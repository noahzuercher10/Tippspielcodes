<?php
require_once __DIR__ . '/SportApi.php';

class TennisSport extends SportApi
{
    /**
     * Tennis hat keine Runden wie Fussball – wir überspringen
     * die eventsround-Iteration komplett und nutzen nur:
     *  1) eventsseason  (bis zu ~50 Spiele der Saison)
     *  2) eventsnext    (nächste anstehende Matches)
     *  3) eventspast    (letzte Ergebnisse)
     *
     * Das vermeidet 50 × API-Calls × 150 ms = ~8 Sekunden Timeout.
     */
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

        if ($season !== '') {
            $add($this->fetchSeasonEvents($apiLeagueId, $season), 'season');
        }
        $add($this->fetchUpcomingEvents($apiLeagueId), 'next');
        $add($this->fetchPastEvents($apiLeagueId), 'past');

        $imported = 0; $updated = 0; $seen = 0;
        $now      = time();
        $maxTs    = strtotime($this->maxDate . ' 23:59:59');
        $checkExist = $pdo->prepare('SELECT id FROM matches WHERE api_event_id = ?');

        foreach ($merged as $ev) {
            $seen++;
            $n = $this->parseEvent($ev);
            if (!$n) continue;
            $ts = strtotime($n['datetime']);
            if ($ts === false || $ts > $maxTs) continue;

            $isPast = $ts < $now;
            if ($isPast) {
                $checkExist->execute([$n['api_event_id']]);
                if (!$checkExist->fetchColumn()) continue;
            }

            if ($n['home_score'] !== null && $n['away_score'] !== null) {
                $mid = $this->upsertFinished($leagueId, $n);
                if ($mid) { evaluate_match($mid); $updated++; }
            } else {
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
            if (preg_match('/^(.*?)\s+(vs\.?|vs|gegen|-)\s+(.*)$/i',
                           (string)$ev['strEvent'], $m)) {
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

        return [
            'home_name'    => $home,
            'away_name'    => $away,
            'home_short'   => $this->shortName($home),
            'away_short'   => $this->shortName($away),
            'home_badge'   => $this->normBadge($ev['strHomeTeamBadge'] ?? null),
            'away_badge'   => $this->normBadge($ev['strAwayTeamBadge'] ?? null),
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
