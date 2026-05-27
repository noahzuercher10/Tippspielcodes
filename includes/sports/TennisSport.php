<?php
require_once __DIR__ . '/SportApi.php';

/**
 * ============================================================
 * Tennis - Einzelsport (ATP Tour)
 * ------------------------------------------------------------
 * Erbt von SportApi und benutzt damit die gleiche TheSportsDB-
 * Sync-Strategie wie alle anderen Sportarten.
 *
 * Besonderheit Tennis:
 *  TheSportsDB liefert ATP-Spiele teilweise mit nur EINEM
 *  Spieler im Eventnamen statt zwei. Daher ueberschreiben wir
 *  parseEvent() und versuchen den Eventnamen "Spieler A vs Spieler B"
 *  zu parsen, falls strHomeTeam/strAwayTeam leer sind.
 * ============================================================
 */
class TennisSport extends SportApi
{
    /**
     * Wandelt ein TheSportsDB-Event in das interne Format um.
     * Override gegenueber Parent: zusaetzliches Fallback-Parsing
     * fuer Tennis-typische Eventnamen.
     */
    protected function parseEvent(array $ev): ?array
    {
        // Standard-Felder aus TheSportsDB
        $home = trim((string)($ev['strHomeTeam'] ?? ''));
        $away = trim((string)($ev['strAwayTeam'] ?? ''));

        // Fallback: aus Eventnamen "Spieler A vs Spieler B" parsen,
        // falls die strHomeTeam/strAwayTeam-Felder leer sind
        if (($home === '' || $away === '') && !empty($ev['strEvent'])) {
            if (preg_match('/^(.*?)\s+(vs\.?|vs|gegen|-)\s+(.*)$/i',
                            (string)$ev['strEvent'], $m)) {
                $home = $home !== '' ? $home : trim($m[1]);
                $away = $away !== '' ? $away : trim($m[3]);
            }
        }
        // Wenn immer noch kein Match: skip
        if ($home === '' || $away === '') return null;

        // Datum + Zeit zusammenbauen
        $date = $ev['dateEvent'] ?? '';
        $time = $ev['strTime']   ?? '00:00:00';
        if ($date === '') return null;

        // Resultate (optional)
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

    /**
     * Bei Tennis nehmen wir den Nachnamen als Kurzform (z.B. "FED", "NAD").
     * Override gegenueber Parent (der die ersten 3 Buchstaben nimmt).
     */
    protected function shortName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        $last  = end($parts) ?: $name;
        return strtoupper(mb_substr($last, 0, 3));
    }
}
