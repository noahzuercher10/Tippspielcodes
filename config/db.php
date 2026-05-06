<?php
/**
 * Datenbank-Verbindung (PDO)
 * --------------------------
 * Anpassen falls dein MySQL anders heisst (XAMPP-Standard funktioniert direkt).
 */

const DB_HOST = '127.0.0.1';
const DB_NAME = 'tippspiel';
const DB_USER = 'root';
const DB_PASS = '';     // bei XAMPP standardmaessig leer
const DB_CHAR = 'utf8mb4';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHAR;
        $opt = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opt);
        } catch (PDOException $e) {
            http_response_code(500);
            die('DB-Fehler: ' . $e->getMessage());
        }
    }
    return $pdo;
}
