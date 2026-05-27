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
 * Sync-Strategie (in dieser Reihenfolge):
 *  1. eventsseason.php?id=X&s=SAISON       (kann durch Free-API auf 15 begrenzt sein)
 *  2. eventsround.php?id=X&s=SAISON&r=N    (fuer N=1..MAX_ROUNDS, gibt KOMPLETTE Saison)
 *  3. eventsnext + eventspast              (Notfall-Fallback)
 *
 * Alle Events werden auf <= MAX_DATE gefiltert (Standard 2026-08-10).
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../functions.php';

abstract class SportApi
{
    protected int    $sportId;
    protected string $sportName;

    /** Cache-Frist in Sekunden (default: 30 min) */
    protected int $cacheTtlSeconds = 600;

    /** Max. zu betrachtende Runde beim Iterieren von eventsround.php */
    protected int $maxRounds = 50;

    /** Maximale Match-Datum-Grenze beim Sync */
    public string $maxDate = '2026-08-10';

    /** Letzter API-Fehler (fuer Diagnose) */
    public ?string $lastError = null;

    public function __construct(int $sportId, string $sportName)
    {
        $this->sportId   = $sportId;
        $this->sportName = $sportName;
    }

    public function getSportId(): int { return $this->sportId; }
    public function getSportName(): string { return $this->sportName; }

    /** Spiele einer Liga an einem bestimmten Tag. */
    public function getMatchesForDay(int $leagueId, string $date): array
    {
        $this->ensureFresh($leagueId);

        $stmt = db()->prepare(
            'SELECT m.* FROM matches m
              WHERE m.league_id = ?
                AND DATE(m.match_datetime) = ?
              ORDER BY m.match_datetime'
        );
        $stmt->execute([$leagueId, $date]);
        return $stmt->fetchAll();
    }

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
     * Holt die Spiele einer Liga mit allen verfuegbaren Endpoints
     * und speichert sie in `matches` (max. bis $this->maxDate).
     */
    public function syncLeague(int $leagueId, string $apiLeagueId, string $season = ''): array
    {
        $pdo      = db();
        $imported = 0; $updated = 0; $seen = 0; $rounds = 0;
        $sources  = [];

        // dedup nach idEvent
        $merged = [];
        $add = function(array $events, string $src) use (&$merged, &$sources, &$rounds) {
            $cnt = 0;
            foreach ($events as $ev) {
                $id = $ev['idEvent'] ?? null;
                if (!$id) continue;
                if (!isset($merged[$id])) { $merged[$id] = $ev; $cnt++; }
            }
            if ($cnt > 0) $sources[] = "$src=$cnt";
        };

        // -------- 1) eventsseason --------
        if ($season !== '') {
            $add($this->fetchSeasonEvents($apiLeagueId, $season), 'season');
        }

        // -------- 2) Iteriere ueber Runden --------
        if ($season !== '') {
            for ($r = 1; $r <= $this->maxRounds; $r++) {
                $list = $this->fetchRoundEvents($apiLeagueId, $season, $r);
                if (!$list) {
                    // 3 Leer-Runden hintereinander -> Schluss
                    if (++$rounds >= 3) break;
                    continue;
                }
                $rounds = 0;
                $add($list, "r$r");
                usleep(150000); // 0.15s Pause damit wir TheSportsDB nicht ueberrennen
            }
        }

        // -------- 3) Zusaetzlich IMMER eventsnext + eventspast hinzunehmen
        //          (faengt Pokal-Runden, Knockouts, Friendlies usw. ab,
        //          die eventsround nicht kennt). Past nur fuer Resultat-Updates.
        $add($this->fetchUpcomingEvents($apiLeagueId), 'next');
        $add($this->fetchPastEvents($apiLeagueId),     'past');

        // -------- Verarbeiten --------
        // - max-Datum wird gehalten
        // - Vergangene Spiele duerfen NUR aktualisiert werden (fuer Resultate);
        //   neue past-Events werden nicht eingefuegt -> "nur Zukunft anzeigen".
        $now   = time();
        $maxTs = strtotime($this->maxDate . ' 23:59:59');
        $checkExist = $pdo->prepare('SELECT id FROM matches WHERE api_event_id = ?');
        foreach ($merged as $ev) {
            $seen++;
            $n = $this->parseEvent($ev);
            if (!$n) continue;
            $ts = strtotime($n['datetime']);
            if ($ts === false || $ts > $maxTs) continue;

            $isPast = $ts < $now;
            if ($isPast) {
                // Nur weiter verarbeiten wenn das Spiel bereits in der DB liegt
                // (dann brauchen wir das Resultat).
                $checkExist->execute([$n['api_event_id']]);
                if (!$checkExist->fetchColumn()) continue;
            }

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

        $pdo->prepare('UPDATE leagues SET last_sync = NOW() WHERE id = ?')
            ->execute([$leagueId]);

        return [
            'source'     => implode(', ', $sources) ?: '(kein Treffer)',
            'imported'   => $imported,
            'updated'    => $updated,
            'seen'       => $seen,
            'last_error' => $this->lastError,
        ];
    }

    /** Hooks - können überschrieben werden. */

    protected function fetchSeasonEvents(string $apiLeagueId, string $season): array
    {
        if ($season === '') return [];
        $data = $this->tsdbCall('eventsseason.php?id=' . urlencode($apiLeagueId)
                              . '&s=' . urlencode($season));
        return $data['events'] ?? [];
    }

    protected function fetchRoundEvents(string $apiLeagueId, string $season, int $round): array
    {
        $data = $this->tsdbCall('eventsround.php?id=' . urlencode($apiLeagueId)
                              . '&r=' . $round
                              . '&s=' . urlencode($season));
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

    protected function parseEvent(array $ev): ?array
    {
        if (empty($ev['idEvent'])) return null;
        $home = trim((string)($ev['strHomeTeam'] ?? ''));
        $away = trim((string)($ev['strAwayTeam'] ?? ''));
        if ($home === '' || $away === '') return null;

        $date = $ev['dateEvent'] ?? '';
        if ($date === '') return null;
        $time = substr((string)($ev['strTime'] ?? '00:00:00'), 0, 8) ?: '00:00:00';

        $hs = $ev['intHomeScore'] ?? null;
        $as = $ev['intAwayScore'] ?? null;
        return [
            'home_name'    => $home,
            'away_name'    => $away,
            'home_short'   => $this->shortName($home),
            'away_short'   => $this->shortName($away),
            'datetime'     => trim($date . ' ' . $time),
            'home_score'   => ($hs === null || $hs === '') ? null : (int)$hs,
            'away_score'   => ($as === null || $as === '') ? null : (int)$as,
            'api_event_id' => (string)$ev['idEvent'],
        ];
    }

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
                'UPDATE matches SET league_id=?, home_name=?, away_name=?,
                    home_short=?, away_short=?, match_datetime=? WHERE id=?'
            )->execute([$leagueId, $n['home_name'], $n['away_name'],
                        $n['home_short'], $n['away_short'], $n['datetime'], $existing]);
            return false;
        }
        $pdo->prepare(
            'INSERT INTO matches (league_id, home_name, away_name, home_short, away_short,
                                  match_datetime, status, api_event_id)
             VALUES (?,?,?,?,?,?,"upcoming",?)'
        )->execute([$leagueId, $n['home_name'], $n['away_name'],
                    $n['home_short'], $n['away_short'], $n['datetime'], $n['api_event_id']]);
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
                'UPDATE matches SET home_name=?, away_name=?, home_short=?, away_short=?,
                    home_score=?, away_score=?, status="finished", match_datetime=?
                  WHERE id=?'
            )->execute([$n['home_name'], $n['away_name'], $n['home_short'], $n['away_short'],
                        $n['home_score'], $n['away_score'], $n['datetime'], $existing]);
            return $existing;
        }
        $pdo->prepare(
            'INSERT INTO matches (league_id, home_name, away_name, home_short, away_short,
                                  match_datetime, home_score, away_score, status, api_event_id)
             VALUES (?,?,?,?,?,?,?,?,"finished",?)'
        )->execute([$leagueId, $n['home_name'], $n['away_name'],
                    $n['home_short'], $n['away_short'], $n['datetime'],
                    $n['home_score'], $n['away_score'], $n['api_event_id']]);
        return (int)$pdo->lastInsertId();
    }

    /** HTTP-Call (cURL bevorzugt, file_get_contents als Fallback). */
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

    protected function httpGet(string $url, array $headers = []): ?string
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
            if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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
                'http' => ['timeout'=>15, 'user_agent'=>'TippspielApp/1.0'],
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
