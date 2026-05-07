<?php
/**
 * Abstrakte Basis-Klasse fuer alle Sportarten.
 *
 * Vererbung:
 *   SportApi (abstract)
 *      |-- FootballSport
 *      |-- IceHockeySport
 *      |-- BasketballSport
 *      |-- TennisSport
 *      `-- Formula1Sport
 *
 * Alle Subklassen nutzen die kostenlose TheSportsDB-API
 * (https://www.thesportsdb.com/free_sports_api - API-Key "3" = free public).
 *
 * Idee:
 *  - Es werden keine Teams in der DB gespeichert.
 *  - Spiele werden bei Bedarf aus der API gezogen und in `matches` gecacht.
 *  - Pro Tag werden nur die Spiele dieses Tages angezeigt.
 *  - Beim Sync wird der GANZE Saison-Spielplan (eventsseason.php) geholt,
 *    nicht nur die naechsten/letzten 15 Spiele.
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../functions.php';

abstract class SportApi
{
    protected int    $sportId;
    protected string $sportName;

    /** Cache-Frist in Sekunden (default: 30 min) */
    protected int $cacheTtlSeconds = 1800;

    /** Letzter API-Fehler (fuer Diagnose) */
    public ?string $lastError = null;

    public function __construct(int $sportId, string $sportName)
    {
        $this->sportId   = $sportId;
        $this->sportName = $sportName;
    }

    public function getSportId(): int   { return $this->sportId; }
    public function getSportName(): string { return $this->sportName; }

    /**
     * Liefert alle Spiele einer Liga an einem bestimmten Tag.
     */
    public function getMatchesForDay(int $leagueId, string $date): array
    {
        $this->ensureFresh($leagueId);

        $stmt = db()->prepare(
            'SELECT m.*
             FROM matches m
             WHERE m.league_id = ?
               AND DATE(m.match_datetime) = ?
             ORDER BY m.match_datetime'
        );
        $stmt->execute([$leagueId, $date]);
        return $stmt->fetchAll();
    }

    /**
     * Synchronisiert die Liga mit der API, falls noch nie oder
     * laenger als $cacheTtlSeconds zurueck synchronisiert wurde.
     */
    public function ensureFresh(int $leagueId, bool $force = false): void
    {
        $row = db()->prepare('SELECT api_id, season, last_sync FROM leagues WHERE id = ?');
        $row->execute([$leagueId]);
        $league = $row->fetch();
        if (!$league || !$league['api_id']) return;

        $stale = $force
              || !$league['last_sync']
              || (time() - strtotime($league['last_sync'])) > $this->cacheTtlSeconds;

        if ($stale) {
            $this->syncLeague($leagueId, (string)$league['api_id'], (string)($league['season'] ?? ''));
        }
    }

    /**
     * Holt die Spiele einer Liga und speichert sie in `matches`.
     *
     * Strategie:
     *  1. Versuche eventsseason.php fuer die GANZE Saison
     *     (gibt typischerweise 100-380 Spiele zurueck).
     *  2. Falls das nichts liefert: Fallback auf
     *     eventsnextleague + eventspastleague (max. 30 Spiele).
     */
    public function syncLeague(int $leagueId, string $apiLeagueId, string $season = ''): array
    {
        $pdo      = db();
        $imported = 0; $updated = 0; $seen = 0;
        $source   = '';

        // -------- 1) Ganze Saison versuchen --------
        $events = [];
        if ($season !== '') {
            $events = $this->fetchSeasonEvents($apiLeagueId, $season);
            if ($events) $source = "eventsseason.php?s=$season";
        }

        // -------- 2) Fallback wenn leer --------
        if (!$events) {
            $events = array_merge(
                $this->fetchUpcomingEvents($apiLeagueId),
                $this->fetchPastEvents($apiLeagueId)
            );
            if ($events) $source = 'eventsnext+eventspast';
        }

        foreach ($events as $ev) {
            $seen++;
            $n = $this->parseEvent($ev);
            if (!$n) continue;
            // Mit Resultat -> als finished + auswerten
            if ($n['home_score'] !== null && $n['away_score'] !== null) {
                $matchId = $this->upsertFinished($leagueId, $n);
                if ($matchId) {
                    evaluate_match($matchId);
                    $updated++;
                }
            } else {
                $imported += $this->upsertUpcoming($leagueId, $n) ? 1 : 0;
            }
        }

        // last_sync vermerken
        $pdo->prepare('UPDATE leagues SET last_sync = NOW() WHERE id = ?')
            ->execute([$leagueId]);

        return [
            'source'     => $source,
            'imported'   => $imported,
            'updated'    => $updated,
            'seen'       => $seen,
            'last_error' => $this->lastError,
        ];
    }

    /**
     * Hooks - koennen ueberschrieben werden.
     */

    /** Komplette Saison (alle Spiele auf einmal). */
    protected function fetchSeasonEvents(string $apiLeagueId, string $season): array
    {
        if ($season === '') return [];
        $data = $this->tsdbCall(
            'eventsseason.php?id=' . urlencode($apiLeagueId)
            . '&s=' . urlencode($season)
        );
        return $data['events'] ?? [];
    }

    protected function fetchUpcomingEvents(string $apiLeagueId): array
    {
        $data = $this->tsdbCall('eventsnextleague.php?id=' . urlencode($apiLeagueId));
        return $data['events'] ?? [];
    }

    protected function fetchPastEvents(string $apiLeagueId): array
    {
        $data = $this->tsdbCall('eventspastleague.php?id=' . urlencode($apiLeagueId));
        return $data['events'] ?? [];
    }

    /**
     * Wandelt ein TheSportsDB-Event in unser internes Format um.
     */
    protected function parseEvent(array $ev): ?array
    {
        if (empty($ev['idEvent'])) return null;
        $home = trim((string)($ev['strHomeTeam'] ?? ''));
        $away = trim((string)($ev['strAwayTeam'] ?? ''));
        if ($home === '' || $away === '') return null;

        $date = $ev['dateEvent'] ?? '';
        $time = $ev['strTime']   ?? '00:00:00';
        if ($date === '') return null;
        // Time kann "HH:MM:SS" oder "HH:MM:SS+00:00" sein -> nur die ersten 8 Zeichen nehmen
        $time = substr($time, 0, 8) ?: '00:00:00';
        $dt   = trim($date . ' ' . $time);

        $hs = $ev['intHomeScore'] ?? null;
        $as = $ev['intAwayScore'] ?? null;
        return [
            'home_name'    => $home,
            'away_name'    => $away,
            'home_short'   => $this->shortName($home),
            'away_short'   => $this->shortName($away),
            'datetime'     => $dt,
            'home_score'   => ($hs === null || $hs === '') ? null : (int)$hs,
            'away_score'   => ($as === null || $as === '') ? null : (int)$as,
            'api_event_id' => (string)$ev['idEvent'],
        ];
    }

    /** Default: erste 3 Buchstaben in Grossbuchstaben. */
    protected function shortName(string $name): string
    {
        return strtoupper(mb_substr(preg_replace('/\s+/', '', $name), 0, 3));
    }

    /** -------- DB Helpers -------- */

    protected function upsertUpcoming(int $leagueId, array $n): bool
    {
        $pdo = db();
        $st = $pdo->prepare('SELECT id FROM matches WHERE api_event_id = ?');
        $st->execute([$n['api_event_id']]);
        $existing = (int)$st->fetchColumn();

        if ($existing) {
            $pdo->prepare(
                'UPDATE matches
                    SET league_id=?, home_name=?, away_name=?,
                        home_short=?, away_short=?, match_datetime=?
                  WHERE id=?'
            )->execute([
                $leagueId, $n['home_name'], $n['away_name'],
                $n['home_short'], $n['away_short'], $n['datetime'], $existing
            ]);
            return false;
        }

        $pdo->prepare(
            'INSERT INTO matches
              (league_id, home_name, away_name, home_short, away_short,
               match_datetime, status, api_event_id)
             VALUES (?,?,?,?,?,?,"upcoming",?)'
        )->execute([
            $leagueId, $n['home_name'], $n['away_name'],
            $n['home_short'], $n['away_short'], $n['datetime'], $n['api_event_id']
        ]);
        return true;
    }

    protected function upsertFinished(int $leagueId, array $n): int
    {
        $pdo = db();
        $st = $pdo->prepare('SELECT id FROM matches WHERE api_event_id = ?');
        $st->execute([$n['api_event_id']]);
        $existing = (int)$st->fetchColumn();

        if ($existing) {
            $pdo->prepare(
                'UPDATE matches
                    SET home_name=?, away_name=?, home_short=?, away_short=?,
                        home_score=?, away_score=?, status="finished",
                        match_datetime=?
                  WHERE id=?'
            )->execute([
                $n['home_name'], $n['away_name'], $n['home_short'], $n['away_short'],
                $n['home_score'], $n['away_score'], $n['datetime'], $existing
            ]);
            return $existing;
        }

        $pdo->prepare(
            'INSERT INTO matches
              (league_id, home_name, away_name, home_short, away_short,
               match_datetime, home_score, away_score, status, api_event_id)
             VALUES (?,?,?,?,?,?,?,?,"finished",?)'
        )->execute([
            $leagueId, $n['home_name'], $n['away_name'],
            $n['home_short'], $n['away_short'], $n['datetime'],
            $n['home_score'], $n['away_score'], $n['api_event_id']
        ]);
        return (int)$pdo->lastInsertId();
    }

    /**
     * HTTP-Call gegen TheSportsDB v1 (free key = 3).
     * Versucht zuerst cURL, faellt zurueck auf file_get_contents.
     */
    protected function tsdbCall(string $endpoint): ?array
    {
        $url = 'https://www.thesportsdb.com/api/v1/json/3/' . $endpoint;
        $raw = $this->httpGet($url);
        if ($raw === null) return null;
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $this->lastError = 'Ungueltiges JSON von ' . $url;
            return null;
        }
        return $data;
    }

    protected function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_USERAGENT      => 'TippspielApp/1.0 (ZbW 2608)',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);
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
            $ctx = stream_context_create([
                'http' => ['timeout' => 15, 'user_agent' => 'TippspielApp/1.0'],
                'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
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
