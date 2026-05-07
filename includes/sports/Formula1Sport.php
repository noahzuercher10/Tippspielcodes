<?php
require_once __DIR__ . '/SportApi.php';

/**
 * Formel 1.
 *
 * F1 hat keine zwei Teams pro "Match" sondern Rennen.
 * Wir mappen jedes Rennen so:
 *   home_name = "<GP-Name> - Sieger"
 *   away_name = "<GP-Name> - Podium 2."
 * Die Saison wird vom Base ueber eventsseason.php geholt.
 */
class Formula1Sport extends SportApi
{
    protected function parseEvent(array $ev): ?array
    {
        if (empty($ev['idEvent'])) return null;

        $eventName = trim((string)($ev['strEvent'] ?? 'Grand Prix'));
        $home = $eventName . ' - Sieger';
        $away = $eventName . ' - Podium 2.';

        $date = $ev['dateEvent'] ?? '';
        $time = substr((string)($ev['strTime'] ?? '15:00:00'), 0, 8) ?: '15:00:00';
        if ($date === '') return null;

        return [
            'home_name'    => $home,
            'away_name'    => $away,
            'home_short'   => 'WIN',
            'away_short'   => 'P2',
            'datetime'     => trim($date . ' ' . $time),
            'home_score'   => null,   // F1-Resultate trägt der Admin manuell ein
            'away_score'   => null,
            'api_event_id' => (string)$ev['idEvent'],
        ];
    }
}
