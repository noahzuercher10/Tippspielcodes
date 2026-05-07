<?php
/**
 * Factory: erzeugt das richtige SportApi-Objekt anhand eines
 * sports-DB-Datensatzes oder einer Sport-ID.
 *
 * Beispiel:
 *   $api = SportFactory::forSportId(1);    // -> FootballSport
 *   $api->getMatchesForDay($leagueId, '2026-05-07');
 */
require_once __DIR__ . '/SportApi.php';
require_once __DIR__ . '/FootballSport.php';
require_once __DIR__ . '/IceHockeySport.php';
require_once __DIR__ . '/BasketballSport.php';
require_once __DIR__ . '/TennisSport.php';
require_once __DIR__ . '/Formula1Sport.php';
require_once __DIR__ . '/../../config/db.php';

class SportFactory
{
    public static function forSportId(int $sportId): ?SportApi
    {
        $st = db()->prepare('SELECT id,name,api_class FROM sports WHERE id = ?');
        $st->execute([$sportId]);
        $row = $st->fetch();
        if (!$row) return null;
        return self::createFromRow($row);
    }

    public static function forLeagueId(int $leagueId): ?SportApi
    {
        $st = db()->prepare(
            'SELECT s.id, s.name, s.api_class
             FROM leagues l JOIN sports s ON s.id = l.sport_id
             WHERE l.id = ?'
        );
        $st->execute([$leagueId]);
        $row = $st->fetch();
        if (!$row) return null;
        return self::createFromRow($row);
    }

    private static function createFromRow(array $row): ?SportApi
    {
        $cls = $row['api_class'] ?? '';
        if (!$cls || !class_exists($cls)) return null;
        /** @var SportApi $obj */
        $obj = new $cls((int)$row['id'], (string)$row['name']);
        return $obj;
    }
}
