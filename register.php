<?php
require_once __DIR__ . '/includes/auth.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $r = register(
        trim($_POST['username'] ?? ''),
        trim($_POST['email']    ?? ''),
        $_POST['password']      ?? '',
        trim($_POST['first_name'] ?? ''),
        trim($_POST['last_name']  ?? '')
    );
    if ($r['ok']) {
        $_SESSION['user_id'] = $r['id'];
        header('Location: /Tippspiel/pages/home.php');
        exit;
    }
    $err = $r['error'];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Tippspiel - Registrieren</title>
<link rel="stylesheet" href="/Tippspiel/css/style.css?v=5">
</head>
<body class="auth-body">
  <div class="auth-card">
    <h1>Registrieren</h1>

    <?php if ($err): ?>
      <div class="alert error"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <div class="row">
        <label>Vorname    <input name="first_name" required></label>
        <label>Nachname   <input name="last_name"  required></label>
      </div>
      <label>Benutzername <input name="username"   required></label>
      <label>E-Mail       <input type="email" name="email" required></label>
      <label>Passwort     <input type="password" name="password" minlength="6" required></label>
      <button class="btn primary" type="submit">Konto erstellen</button>
    </form>

    <p class="sub">Schon ein Konto? <a href="/Tippspiel/index.php">Login</a></p>
  </div>
</body>
</html>
