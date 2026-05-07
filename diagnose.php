<?php
/**
 * Diagnose-Tool fuer das Tippspiel.
 * Direkt aufrufen: http://localhost/Tippspiel/diagnose.php
 *           oder: http://localhost/Tippspiel/diagnose.php?api_test=1&league_id=3
 */
header('Content-Type: text/html; charset=utf-8');

echo "<!doctype html><html><head><meta charset=utf-8>";
echo "<title>Tippspiel Diagnose</title>";
echo "<style>body{background:#0e1116;color:#dee5ef;font-family:system-ui,sans-serif;padding:24px;line-height:1.5}";
echo "h2{color:#4f8cff;margin-top:32px;border-bottom:1px solid #2c3447;padding-bottom:6px}";
echo "pre{background:#181c25;padding:12px;border-radius:8px;overflow:auto;max-height:340px}";
echo ".ok{color:#19c37d}.err{color:#ef4d4d}.warn{color:#f4c542}";
echo "table{border-collapse:collapse;margin:8px 0}td,th{padding:4px 12px;border:1px solid #2c3447}";
echo "a{color:#4f8cff}";
echo "</style></head><body>";
echo "<h1>Tippspiel - DB &amp; API Diagnose</h1>";

// 1) DB-Verbindung
echo "<h2>1. Datenbank-Verbindung</h2>";
try {
    require_once __DIR__ . '/config/db.php';
    $pdo = db();
    echo "<p class=ok>OK - Verbindung steht.</p>";
    $info = $pdo->query('SELECT VERSION() AS v, DATABASE() AS d, USER() AS u')->fetch(PDO::FETCH_ASSOC);
    echo "<pre>MariaDB/MySQL: " . htmlspecialchars($info['v']) . "\n";
    echo "Aktuelle DB:   " . htmlspecialchars($info['d'] ?: '(keine)') . "\n";
    echo "Verbunden als: " . htmlspecialchars($info['u']) . "</pre>";
} catch (Throwable $e) {
    echo '<p class=err>FEHLER: ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

// 2) Tabellen
echo "<h2>2. Vorhandene Tabellen</h2>";
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "<pre>" . htmlspecialchars(implode("\n", $tables)) . "</pre>";

// 3) Sports-Inhalt
if (in_array('sports', $tables, true)) {
    echo "<h2>3. Inhalt der <code>sports</code></h2>";
    $rows = $pdo->query('SELECT * FROM sports ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        echo '<table><tr>';
        foreach (array_keys($rows[0]) as $k) echo '<th>' . htmlspecialchars($k) . '</th>';
        echo '</tr>';
        foreach ($rows as $r) {
            echo '<tr>';
            foreach ($r as $v) echo '<td>' . htmlspecialchars((string)$v) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else echo '<p class=err>Tabelle leer.</p>';
}

// 4) Ligen
if (in_array('leagues', $tables, true)) {
    echo "<h2>4. Ligen (mit api_id)</h2>";
    $lgs = $pdo->query(
        'SELECT l.id, s.name AS sport, l.name, l.season, l.api_id, l.last_sync
         FROM leagues l JOIN sports s ON s.id = l.sport_id
         ORDER BY s.id, l.id'
    )->fetchAll(PDO::FETCH_ASSOC);
    echo '<table><tr><th>id</th><th>Sport</th><th>Liga</th><th>Saison</th><th>api_id</th><th>last_sync</th><th>Test</th></tr>';
    foreach ($lgs as $l) {
        echo '<tr><td>' . htmlspecialchars((string)$l['id']) . '</td>';
        echo '<td>' . htmlspecialchars($l['sport']) . '</td>';
        echo '<td>' . htmlspecialchars($l['name']) . '</td>';
        echo '<td>' . htmlspecialchars((string)$l['season']) . '</td>';
        echo '<td>' . htmlspecialchars((string)$l['api_id']) . '</td>';
        echo '<td>' . htmlspecialchars((string)$l['last_sync']) . '</td>';
        echo '<td><a href="?api_test=1&league_id=' . (int)$l['id'] . '">testen</a></td></tr>';
    }
    echo '</table>';
}

// 5) PHP-Umfeld
echo "<h2>5. PHP-Umfeld (fuer API-Calls)</h2>";
echo "<pre>PHP-Version:        " . PHP_VERSION . "\n";
echo "cURL:               " . (function_exists('curl_init') ? 'JA' : 'NEIN') . "\n";
echo "allow_url_fopen:    " . (ini_get('allow_url_fopen') ? 'JA' : 'NEIN') . "\n";
echo "openssl:            " . (extension_loaded('openssl') ? 'JA' : 'NEIN') . "\n";
echo "Default Timezone:   " . date_default_timezone_get() . "\n";
echo "Server-Zeit:        " . date('Y-m-d H:i:s') . "</pre>";

// 6) Login-Status
echo "<h2>6. Login-Status</h2>";
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) {
    echo '<p class=ok>Eingeloggt als user_id=' . (int)$_SESSION['user_id'] . '</p>';
} else {
    echo '<p class=warn>NICHT eingeloggt - api/sports.php und api/matches.php liefern HTTP 401.</p>';
    echo '<p>Geh auf <a href="index.php">Login</a>.</p>';
}

// 7) API-Live-Test (wenn ?api_test=1)
$leagueIdTest = (int)($_GET['league_id'] ?? 0);
$doApiTest    = !empty($_GET['api_test']) && $leagueIdTest > 0;

echo "<h2>7. Live-API-Test</h2>";
if (!$doApiTest) {
    echo '<p>Klicke oben in der Liga-Tabelle auf <em>testen</em>, um TheSportsDB live abzufragen.</p>';
} else {
    require_once __DIR__ . '/includes/sports/SportFactory.php';
    $api = SportFactory::forLeagueId($leagueIdTest);
    if (!$api) {
        echo '<p class=err>Sport-Klasse nicht gefunden fuer league_id=' . $leagueIdTest . '</p>';
    } else {
        $r = $pdo->prepare('SELECT api_id, name FROM leagues WHERE id = ?');
        $r->execute([$leagueIdTest]);
        $L = $r->fetch();
        echo '<p>Liga <b>' . htmlspecialchars($L['name']) . '</b> (api_id=' . htmlspecialchars($L['api_id']) . ')</p>';

        // Direkter Roh-Call
        echo '<h3>Roh-Antwort von TheSportsDB (eventsnextleague):</h3>';
        $url = 'https://www.thesportsdb.com/api/v1/json/3/eventsnextleague.php?id=' . urlencode($L['api_id']);
        echo '<p>URL: <code>' . htmlspecialchars($url) . '</code></p>';

        $raw = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_USERAGENT => 'TippspielDiagnose/1.0',
            ]);
            $raw = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);
            echo '<p>HTTP <b>' . (int)$http . '</b> ' . ($err ? '(' . htmlspecialchars($err) . ')' : '') . '</p>';
        }
        if ($raw === null || $raw === false) {
            echo '<p class=err>Kein Body von cURL bekommen.</p>';
        } else {
            echo '<pre>' . htmlspecialchars(substr($raw, 0, 1500)) . (strlen($raw) > 1500 ? "\n... (gekuerzt)" : '') . '</pre>';
        }

        // syncLeague forciert ausloesen
        echo '<h3>Forcierter Sync via SportApi-Klasse:</h3>';
        try {
            $stats = $api->syncLeague($leagueIdTest, (string)$L['api_id']);
            echo '<pre>' . htmlspecialchars(json_encode($stats, JSON_PRETTY_PRINT)) . '</pre>';
        } catch (Throwable $e) {
            echo '<p class=err>SYNC-FEHLER: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }

        // Was steht jetzt in der matches-Tabelle fuer diese Liga?
        echo '<h3>Spiele in <code>matches</code> fuer diese Liga (max. 20):</h3>';
        $ms = $pdo->prepare(
            'SELECT id, home_name, away_name, match_datetime, status, home_score, away_score
             FROM matches WHERE league_id = ? ORDER BY match_datetime LIMIT 20'
        );
        $ms->execute([$leagueIdTest]);
        $rows = $ms->fetchAll();
        if (!$rows) {
            echo '<p class=err>Tabelle ist nach Sync immer noch leer - der API-Call schlug vermutlich fehl.</p>';
        } else {
            echo '<table><tr><th>ID</th><th>Datum/Zeit</th><th>Heim</th><th>Auswärts</th><th>Status</th><th>Score</th></tr>';
            foreach ($rows as $m) {
                echo '<tr><td>' . (int)$m['id'] . '</td>';
                echo '<td>' . htmlspecialchars($m['match_datetime']) . '</td>';
                echo '<td>' . htmlspecialchars($m['home_name']) . '</td>';
                echo '<td>' . htmlspecialchars($m['away_name']) . '</td>';
                echo '<td>' . htmlspecialchars($m['status']) . '</td>';
                echo '<td>' . htmlspecialchars((string)$m['home_score']) . ':' . htmlspecialchars((string)$m['away_score']) . '</td></tr>';
            }
            echo '</table>';
        }
    }
}

echo "</body></html>";
