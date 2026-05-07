<?php
require_once __DIR__ . '/SportApi.php';

/**
 * Basketball - NBA / EuroLeague Kurzformen.
 */
class BasketballSport extends SportApi
{
    private static array $aliases = [
        'la lakers' => 'LAL', 'los angeles lakers' => 'LAL',
        'boston celtics' => 'BOS', 'golden state warriors' => 'GSW',
        'miami heat' => 'MIA', 'denver nuggets' => 'DEN',
        'milwaukee bucks' => 'MIL', 'philadelphia 76ers' => 'PHI',
        'real madrid' => 'RMA', 'fc barcelona' => 'BAR',
        'olympiacos piraeus' => 'OLY',
    ];

    protected function shortName(string $name): string
    {
        $key = mb_strtolower($name);
        if (isset(self::$aliases[$key])) return self::$aliases[$key];
        return parent::shortName($name);
    }
}
