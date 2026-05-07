<?php
/**
 * GET /api/sports.php
 * Liefert alle verfuegbaren Sportarten als JSON.
 * (Diese Datei war zuvor faelschlich eine HTML-Seite - das ist der Grund,
 *  warum das Sportarten-Dropdown leer war.)
 */
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
require_login();

$rows = db()->query(
    'SELECT id, name, type, api_class, icon
     FROM sports
     ORDER BY id'
)->fetchAll();

echo json_encode($rows);
