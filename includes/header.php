<?php
require_once __DIR__ . '/auth.php';
$user = require_login();
$active = $active ?? '';
$bg = $user['background_image']
    ? '/Tippspiel/' . htmlspecialchars($user['background_image'])
    : null;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tippspiel</title>
<link rel="stylesheet" href="/Tippspiel/css/style.css">
<!-- app.js MUSS vor allen Page-Scripts laden, sonst ist Tippspiel undefiniert -->
<script src="/Tippspiel/js/app.js?v=2"></script>
<?php if ($bg): ?>
<style>
  body { background-image: linear-gradient(rgba(14,17,22,.85), rgba(14,17,22,.92)),
                            url("<?= $bg ?>");
         background-size: cover; background-attachment: fixed; background-position: center; }
</style>
<?php endif; ?>
</head>
<body>
<header class="topbar">
  <a class="profile-link" href="/Tippspiel/pages/profile.php" title="Profil">
    <span class="avatar"><?= htmlspecialchars(initials($user)) ?></span>
    <span class="username"><?= htmlspecialchars($user['username']) ?></span>
  </a>

  <h1 class="brand">Tippspiel</h1>

  <div class="mode-switch">
    <label for="mode">Modus</label>
    <select id="mode">
      <option value="points">Punkte</option>
      <option value="money">Imaginäres Geld</option>
    </select>
  </div>
</header>

<nav class="mainnav">
  <a href="/Tippspiel/pages/groups.php"      class="<?= $active==='groups'      ? 'active' : '' ?>">Gruppen</a>
  <a href="/Tippspiel/pages/leaderboard.php" class="<?= $active==='leaderboard' ? 'active' : '' ?>">Rangliste</a>
  <a href="/Tippspiel/pages/sports.php"      class="<?= $active==='sports'      ? 'active' : '' ?>">Sportarten</a>
  <?php if ($user['role'] === 'admin'): ?>
    <a href="/Tippspiel/pages/admin.php"     class="<?= $active==='admin'       ? 'active' : '' ?>">Admin</a>
  <?php endif; ?>
  <a href="/Tippspiel/api/logout.php" class="logout">Logout</a>
</nav>

<main class="container">
