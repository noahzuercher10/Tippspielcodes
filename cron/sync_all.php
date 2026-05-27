<?php
/**
 * Sync-Script fuer alle Ligen (CLI).
 * Aufgerufen vom Windows Task Scheduler 07:00 + 19:00.
 *
 * Param: --force = ignoriert den 30-Min-Cache und holt alles neu.
 * Beispiel:  php cron/sync_all.php --force
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/sports/SportFactory.php';

$force = in_array('--force', $argv ?? [], true);
$startedAt = date('Y-m-d H:i:s');
echo "[$startedAt] Tippspiel sync_all gestartet" . ($force ? ' (FORCE)' : '') . "\n";

$pdo = db();
$lgs = $pdo->query(
    'SELECT l.id, l.name, l.api_id, l.season, s.name AS sport
     FROM leagues l JOIN sports s ON s.id = l.sport_id
     WHERE l.api_id IS NOT NULL AND l.api_id != ""
     ORDER BY s.id, l.id'
)->fetchAll();

$totalImported = 0; $totalUpdated = 0;

foreach ($lgs as $lg) {
    $api = SportFactory::forLeagueId((int)$lg['id']);
    if (!$api) { echo "  SKIP {$lg['sport']} - {$lg['name']}: keine Klasse\n"; continue; }
    if ($force) {
        $pdo->prepare('UPDATE leagues SET last_sync = NULL WHERE id = ?')
            ->execute([(int)$lg['id']]);
    }
    try {
        $r = $api->syncLeague(
            (int)$lg['id'],
            (string)$lg['api_id'],
            (string)($lg['season'] ?? '')
        );
        $totalImported += (int)($r['imported'] ?? 0);
        $totalUpdated  += (int)($r['updated']  ?? 0);
        printf(
            "  OK   %-30s %-30s seen=%-4d new=%-4d eval=%-3d\n",
            $lg['sport'], $lg['name'],
            (int)$r['seen'], (int)$r['imported'], (int)$r['updated']
        );
        echo "       sources: " . ($r['source'] ?? '-') . "\n";
        if (!empty($r['last_error'])) echo "       Hinweis: {$r['last_error']}\n";
    } catch (Throwable $e) {
        echo "  ERR  {$lg['sport']} - {$lg['name']}: " . $e->getMessage() . "\n";
    }
    usleep(300000);
}

$endedAt = date('Y-m-d H:i:s');
echo "\n[$endedAt] Fertig. Total: $totalImported neue Spiele, $totalUpdated Resultate ausgewertet.\n";
