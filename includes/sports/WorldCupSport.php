<?php
require_once __DIR__ . '/FootballSport.php';
require_once __DIR__ . '/../../config/api_keys.php';

/**
 * FIFA Weltmeisterschaft – kombiniert TheSportsDB + football-data.org.
 *
 * Ligadatenbank:
 *   - TheSportsDB-Liga-ID für FIFA WM (z.B. "4453")
 *   - football-data.org Competition-Code "WC" (erfordert API-Key)
 *
 * Tipp-Mechanik: identisch mit Fussball (Heim/Auswärts Tore).
 * Badges: TheSportsDB liefert Nationalflaggen / Wappen.
 */
class WorldCupSport extends FootballSport
{
    /** Kurzcodes für Nationalteams */
    private static array $nationAliases = [
        'germany'            => 'GER', 'deutschland'        => 'GER',
        'france'             => 'FRA', 'frankreich'         => 'FRA',
        'spain'              => 'ESP', 'spanien'            => 'ESP',
        'brazil'             => 'BRA', 'brasilien'          => 'BRA',
        'argentina'          => 'ARG', 'argentinien'        => 'ARG',
        'england'            => 'ENG', 'united states'      => 'USA',
        'portugal'           => 'POR', 'netherlands'        => 'NED',
        'niederlande'        => 'NED', 'italy'              => 'ITA',
        'italien'            => 'ITA', 'croatia'            => 'CRO',
        'kroatien'           => 'CRO', 'morocco'            => 'MAR',
        'japan'              => 'JPN', 'south korea'        => 'KOR',
        'australia'          => 'AUS', 'senegal'            => 'SEN',
        'mexico'             => 'MEX', 'canada'             => 'CAN',
        'switzerland'        => 'SUI', 'schweiz'            => 'SUI',
        'austria'            => 'AUT', 'österreich'         => 'AUT',
        'belgium'            => 'BEL', 'belgien'            => 'BEL',
        'poland'             => 'POL', 'ukraine'            => 'UKR',
        'denmark'            => 'DEN', 'dänemark'           => 'DEN',
        'sweden'             => 'SWE', 'schweden'           => 'SWE',
        'norway'             => 'NOR', 'wales'              => 'WAL',
        'saudi arabia'       => 'KSA', 'qatar'              => 'QAT',
        'ecuador'            => 'ECU', 'iran'               => 'IRN',
        'ghana'              => 'GHA', 'colombia'           => 'COL',
        'chile'              => 'CHI', 'uruguay'            => 'URU',
        'costa rica'         => 'CRC', 'cameroon'           => 'CMR',
        'nigeria'            => 'NGA', 'egypt'              => 'EGY',
        'new zealand'        => 'NZL',
    ];

    /**
     * Gibt den FIFA-Standardkürzel zurück (z.B. 'GER', 'BRA').
     * Prüft zuerst $nationAliases, Fallback auf die generische Methode.
     */
    protected function shortName(string $name): string
    {
        $key = mb_strtolower(trim($name));
        if (isset(self::$nationAliases[$key])) return self::$nationAliases[$key];
        // Fallback: Eltern-Kurzname
        return parent::shortName($name);
    }

    /**
     * Synct WM-Spiele aus:
     *  1. TheSportsDB + OpenLigaDB (via FootballSport::syncLeague)
     *  2. football-data.org Competition 'WC' (wenn API-Key vorhanden)
     */
    public function syncLeague(int $leagueId, string $apiLeagueId, string $season = ''): array
    {
        // TheSportsDB (+ OpenLigaDB wenn nötig)
        $res = parent::syncLeague($leagueId, $apiLeagueId, $season);

        // football-data.org WC wenn Key vorhanden
        $fdoKey = trim((string)(defined('FOOTBALL_DATA_ORG_KEY') ? FOOTBALL_DATA_ORG_KEY : ''));
        if ($fdoKey !== '') {
            $year = '';
            if (preg_match('/(\d{4})/', $season, $m)) $year = $m[1];
            if (!$year) $year = date('Y');
            $s = $this->syncFromFootballDataOrg($leagueId, 'WC', $year, $fdoKey);
            $res['source']   = ($res['source']   ?? '') . ', fdo-wc=' . $s['seen'];
            $res['imported'] = ($res['imported'] ?? 0) + $s['imported'];
            $res['updated']  = ($res['updated']  ?? 0) + $s['updated'];
            $res['seen']     = ($res['seen']     ?? 0) + $s['seen'];
        }

        return $res;
    }
}
