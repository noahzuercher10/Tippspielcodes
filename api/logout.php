<?php
require_once __DIR__ . '/../includes/auth.php';
logout();
header('Location: /tippspiel/index.php');
exit;
