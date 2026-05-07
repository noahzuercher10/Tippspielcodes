<?php
require_once __DIR__ . '/SportApi.php';

/**
 * Eishockey - identisches API-Verhalten wie Fussball,
 * aber mit eigenen NHL/NL-Kurzformen.
 */
class IceHockeySport extends SportApi
{
    private static array $aliases = [
        'sc bern' => 'SCB',   'zsc lions' => 'ZSC',
        'ev zug' => 'EVZ',    'hc davos' => 'HCD',
        'toronto maple leafs' => 'TOR',
        'boston bruins' => 'BOS',
        'edmonton oilers' => 'EDM',
        'new york rangers' => 'NYR',
        'tampa bay lightning' => 'TBL',
    ];

    protected function shortName(string $name): string
    {
        $key = mb_strtolower($name);
        if (isset(self::$aliases[$key])) return self::$aliases[$key];
        return parent::shortName($name);
    }
}
