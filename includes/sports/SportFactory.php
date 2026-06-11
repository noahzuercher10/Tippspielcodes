<?php
/**
 * ============================================================
 * SportFactory - erzeugt das richtige Sport-Objekt
 * ------------------------------------------------------------
 * Eine Liga liegt in der DB. Wir wissen ueber sport_id zu welcher
 * Sportart sie gehoert. Die sports-Tabelle hat eine Spalte
 * `api_class` (z.B. "FootballSport"). Mit dieser ID erzeugt die
 * Factory die passende Sportart-Klasse, die von SportApi erbt.
 *
 * Beispiel:
 *   $api = SportFactory::forLeagueId(2);
 *   // -> liefert ein FootballSport-Objekt fuer die Bundesliga
 *   $api->getMatchesForDay(2, '2026-05-09');
 * ============================================================
 */
require_once __DIR__ . '/SportApi.php';
require_once __DIR__ . '/FootballSport.php';
require_once __DIR__ . '/IceHockeySport.php';
require_once __DIR__ . '/BasketballSport.php';
require_once __DIR__ . '/TennisSport.php';
require_once __DIR__ . '/Formula1Sport.php';
require_once __DIR__ . '/WorldCupSport.php';
require_once __DIR__ . '/../../config/db.php';

class SportFactory
{
    /**
     * Holt die Sportart anhand sport_id aus der DB und instanziiert
     * das passende Klassen-Objekt.
     */
    public static function forSportId(int $sportId): ?SportApi
    {
        $st = db()->prepare('SELECT id,name,api_class FROM sports WHERE id = ?');
        $st->execute([$sportId]);
        $row = $st->fetch();
        if (!$row) return null;
        return self::createFromRow($row);
    }

    /**
     * Wie forSportId(), aber komfortabler: nimmt eine league_id und
     * sucht die zugehoerige Sportart selbst. Wird hauptsaechlich von
     * api/matches.php benutzt.
     */
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

    /**
     * Interner Helper: aus einer DB-Row (mit api_class) ein
     * konkretes Sport-Objekt machen.
     */
    private static function createFromRow(array $row): ?SportApi
    {
        $cls = $row['api_class'] ?? '';
        // Sicherheit: Klasse muss existieren, sonst Null zurueck
        if (!$cls || !class_exists($cls)) return null;
        /** @var SportApi $obj */
        $obj = new $cls((int)$row['id'], (string)$row['name']);
        return $obj;
    }
}
