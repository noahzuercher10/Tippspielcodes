<?php
require_once __DIR__ . '/SportApi.php';

/**
 * Tennis (Einzelsport).
 *
 * TheSportsDB liefert ATP-Spiele teilweise mit nur einem Spieler im
 * Eventnamen. Daher wird parseEvent() ueberschrieben, damit auch
 * Events mit "VS" im Eventnamen verarbeitet werden koennen.
 */
class TennisSport extends SportApi
{
    protected function parseEvent(array $ev): ?array
    {
        $home = trim((string)($ev['strHomeTeam'] ?? ''));
        $away = trim((string)($ev['strAwayTeam'] ?? ''));

        // Fallback: Eventname kommt als "Spieler A vs Spieler B"
        if (($home === '' || $away === '') && !empty($ev['strEvent'])) {
            if (preg_match('/^(.*?)\s+(vs\.?|vs|gegen|-)\s+(.*)$/i',
                            (string)$ev['strEvent'], $m)) {
                $home = $home !== '' ? $home : trim($m[1]);
                $away = $away !== '' ? $away : trim($m[3]);
            }
        }
        if ($home === '' || $away === '') return null;

        $date = $ev['dateEvent'] ?? '';
        $time = $ev['strTime']   ?? '00:00:00';
        if ($date === '') return null;

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
            'api_event_id' => (string)($ev['idEvent'] ?? ''),
        ];
    }

    /** Tennis: Nachname des Spielers als Kuerzel. */
    protected function shortName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        $last  = end($parts) ?: $name;
        return strtoupper(mb_substr($last, 0, 3));
    }
}
