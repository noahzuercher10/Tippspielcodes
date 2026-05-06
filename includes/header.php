<?php
require_once __DIR__ . '/auth.php';
$user = require_login();
$active = $active ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tippspiel</title>
<link rel="stylesheet" href="/tippspiel/css/style.css">
</head>
<body>
<header class="topbar">
  <a class="profile-link" href="/tippspiel/pages/profile.php" title="Profil">
    <span class="avatar"><?= htmlspecialchars(initials($user)) ?></span>
    <span class="username"><?= htmlspecialchars($user['username']) ?></span>
  </a>

  <h1 class="brand">Tippspiel</h1>

  <div class="mode-switch">
    <label for="mode">Modus</label>
    <select id="mode">
      <option value="points">Punkte</option>
      <option value="money">Imaginaeres Geld</option>
    </select>
  </div>
</header>

<nav class="mainnav">
  <a href="/tippspiel/pages/groups.php"      class="<?= $active==='groups'?'active':'' ?>">Gruppen</a>
  <a href="/tippspiel/pages/leaderboard.php" class="<?= $active==='leaderboard'?'active':'' ?>">Rangliste</a>
  <a href="/tippspiel/pages/sports.php"      class="<?= $active==='sports'?'active':'' ?>">Sportarten</a>
  <?php if ($user['role'] === 'admin'): ?>
    <a href="/tippspiel/pages/admin.php"     class="<?= $active==='admin'?'active':'' ?>">Admin</a>
  <?php endif; ?>
  <a href="/tippspiel/api/logout.php" class="logout">Logout</a>
</nav>

<main class="container">
