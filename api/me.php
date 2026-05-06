<?php
/** GET /api/me.php  -> aktueller User (fuer JS-Frontend) */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');
$u = require_login();
echo json_encode([
    'id'           => $u['id'],
    'username'     => $u['username'],
    'first_name'   => $u['first_name'],
    'last_name'    => $u['last_name'],
    'role'         => $u['role'],
    'points_total' => (int)$u['points_total'],
    'money_balance'=> (float)$u['money_balance'],
    'max_stake'    => max_stake((float)$u['money_balance']),
    'initials'     => initials($u),
]);
