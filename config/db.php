<?php
/**
 * ============================================================
 * Datenbank-Verbindung (PDO) fuer die Tippspiel-App
 * ------------------------------------------------------------
 * Verwaltet eine einzige PDO-Instanz als Singleton.
 * Bei XAMPP-Standardinstallation funktioniert das ohne Aenderung.
 * Falls du dein MySQL anders konfiguriert hast, hier anpassen.
 * ============================================================
 */

// Verbindungsdaten (XAMPP-Defaults)
const DB_HOST = '127.0.0.1';
const DB_NAME = 'tippspiel';
const DB_USER = 'root';
const DB_PASS = '';                 // bei XAMPP standardmaessig leer
const DB_CHAR = 'utf8mb4';          // Unicode-faehig, Umlaute + Emojis

/**
 * Liefert die (einzige) PDO-Instanz zur Tippspiel-Datenbank.
 *
 * Wird via Singleton-Muster gecached: erster Aufruf baut die Verbindung
 * auf, alle folgenden Aufrufe geben dieselbe Instanz zurueck.
 *
 * @return PDO  Aktive Verbindung zur tippspiel-Datenbank
 */
function db(): PDO {
    static $pdo = null;             // statisch: lebt ueber Funktionsaufrufe hinweg
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHAR;
        $opt = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // Fehler werfen statt false zurueck
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,          // assoziative Arrays beim Lesen
            PDO::ATTR_EMULATE_PREPARES   => false,                     // echte Prepared Statements
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opt);
        } catch (PDOException $e) {
            // DB-Fehler ist fatal - lieber sofort sterben als kryptische Folgefehler
            http_response_code(500);
            die('DB-Fehler: ' . $e->getMessage());
        }
    }
    return $pdo;
}
