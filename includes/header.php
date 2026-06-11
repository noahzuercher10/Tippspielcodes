<?php
require_once __DIR__ . '/auth.php';
$user = require_login();
$active = $active ?? '';
$themeClass = ($user['theme'] ?? 'light') === 'dark' ? 'dark' : '';

$avatarHtml = '';
if (!empty($user['profile_picture'])) {
    $picUrl = '/Tippspiel/' . htmlspecialchars($user['profile_picture']);
    $avatarHtml = '<img src="' . $picUrl . '" alt="Profilbild">';
} else {
    $avatarHtml = htmlspecialchars(initials($user));
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tippspiel</title>
<link rel="stylesheet" href="/Tippspiel/css/style.css?v=5">
<script src="/Tippspiel/js/app.js?v=5"></script>
</head>
<body class="<?= $themeClass ?>">

<header class="topbar">
  <a class="profile-link" href="/Tippspiel/pages/profile.php" title="Profil">
    <span class="avatar" data-initials="<?= htmlspecialchars(initials($user)) ?>"><?= $avatarHtml ?></span>
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
  <a href="/Tippspiel/pages/home.php"        class="<?= $active==='home'       ? 'active' : '' ?>">Start</a>
  <a href="/Tippspiel/pages/sports.php"      class="<?= $active==='sports'     ? 'active' : '' ?>">Tippen</a>
  <a href="/Tippspiel/pages/groups.php"      class="<?= $active==='groups'     ? 'active' : '' ?>">Gruppen</a>
  <a href="/Tippspiel/pages/leaderboard.php" class="<?= $active==='leaderboard'? 'active' : '' ?>">Rangliste</a>
<?php if ($user['role'] === 'admin'): ?>
    <a href="/Tippspiel/pages/admin.php"     class="<?= $active==='admin'      ? 'active' : '' ?>">Admin</a>
  <?php endif; ?>
  <a href="/Tippspiel/api/logout.php" class="logout">Logout</a>
</nav>

<main class="container">
