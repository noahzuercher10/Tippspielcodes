<?php
/**
 * GET /api/logout.php - Session beenden + zur Login-Seite redirecten.
 */
require_once __DIR__ . '/../includes/auth.php';
logout();
header('Location: /Tippspiel/index.php');
exit;
