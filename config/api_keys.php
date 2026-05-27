<?php
/**
 * API-Keys (optional).
 *
 * Diese Keys schalten zusaetzliche Datenquellen frei. Wenn ein Key leer
 * bleibt, wird automatisch nur die Standardquelle (TheSportsDB) genutzt.
 *
 * 1) football-data.org   - https://www.football-data.org/client/register
 *    Free Tier: PL, La Liga, Serie A, Bundesliga, CL, Ligue 1
 *
 * 2) balldontlie.io      - https://app.balldontlie.io/
 *    Free Tier: NBA Spielplan + Resultate
 */

if (!defined('FOOTBALL_DATA_ORG_KEY')) {
    define('FOOTBALL_DATA_ORG_KEY', '6c58544c8e11483981f349a7e55aa4c9');
}

if (!defined('BALLDONTLIE_KEY')) {
    define('BALLDONTLIE_KEY', '');  // <-- optional: balldontlie.io NBA-Key hier
}
