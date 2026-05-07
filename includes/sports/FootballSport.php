<?php
require_once __DIR__ . '/SportApi.php';

/**
 * Fussball - Standard-Implementation reicht.
 * Nur die Kuerzel sind hier sportspezifisch.
 */
class FootballSport extends SportApi
{
    /** Bekannte Vereine bekommen ihre offiziellen Kuerzel. */
    private static array $aliases = [
        'fc bayern munich' => 'FCB',  'bayern munich' => 'FCB',
        'borussia dortmund' => 'BVB', 'rb leipzig' => 'RBL',
        'bayer leverkusen' => 'B04',  'manchester city' => 'MCI',
        'manchester united' => 'MUN', 'liverpool' => 'LIV',
        'arsenal' => 'ARS',           'chelsea' => 'CHE',
        'real madrid' => 'RMA',       'fc barcelona' => 'BAR',
        'atletico madrid' => 'ATM',   'fc basel' => 'BAS',
        'fc zurich' => 'FCZ',         'bsc young boys' => 'YB',
        'fc st. gallen' => 'FCSG',    'fc luzern' => 'FCL',
        'servette fc' => 'SER',
    ];

    protected function shortName(string $name): string
    {
        $key = mb_strtolower($name);
        if (isset(self::$aliases[$key])) return self::$aliases[$key];
        return parent::shortName($name);
    }
}
