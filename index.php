<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) {
    header('Location: /Tippspiel/pages/home.php');
    exit;
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login($_POST['username'] ?? '', $_POST['password'] ?? '')) {
        header('Location: /Tippspiel/pages/home.php');
        exit;
    }
    $err = 'Login fehlgeschlagen.';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Tippspiel - Login</title>
<link rel="stylesheet" href="/Tippspiel/css/style.css?v=5">
</head>
<body class="auth-body">
  <div class="auth-card">
    <h1>Tippspiel</h1>
    <p class="sub">Bitte einloggen</p>

    <?php if ($err): ?>
      <div class="alert error"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <label>Benutzername oder E-Mail
        <input type="text" name="username" required>
      </label>
      <label>Passwort
        <input type="password" name="password" required>
      </label>
      <button type="submit" class="btn primary">Login</button>
    </form>

    <p class="sub">Noch kein Konto? <a href="/Tippspiel/register.php">Jetzt registrieren</a></p>
  </div>
</body>
</html>
