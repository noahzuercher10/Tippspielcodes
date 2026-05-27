<?php
$active = 'home';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="card">
  <h2>Hallo, <?= htmlspecialchars($user['first_name']) ?>!</h2>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="label">Punkte gesamt</div>
      <div class="value" style="color:var(--primary)"><?= (int)$user['points_total'] ?></div>
    </div>
    <div class="stat-card">
      <div class="label">Guthaben</div>
      <div class="value" style="color:var(--accent)"><?= number_format((float)$user['money_balance'], 2, '.', "'") ?></div>
    </div>
  </div>
</section>

<section class="card">
  <h2>Punktesystem</h2>
  <table style="max-width:420px">
    <tr><td>Exakter Tipp</td><td><strong>10 Pkt</strong></td></tr>
    <tr><td>Richtiger Sieger / Unentschieden</td><td><strong>5 Pkt</strong></td></tr>
    <tr><td>Richtige Heimtore</td><td><strong>1 Pkt</strong></td></tr>
    <tr><td>Richtige Auswärtstore</td><td><strong>1 Pkt</strong></td></tr>
    <tr><td>Richtige Tordifferenz (bei richtigem Sieger)</td><td><strong>3 Pkt</strong></td></tr>
  </table>
</section>

<section class="card">
  <h2>Geldmodus</h2>
  <table style="max-width:420px">
    <tr><td>Startkapital</td><td><strong>2'500</strong></td></tr>
    <tr><td>Einsatz pro Spiel</td><td><strong>10 – 500</strong></td></tr>
    <tr><td>Richtig getippt</td><td><strong>Einsatz × 2</strong></td></tr>
    <tr><td>Falsch getippt</td><td><strong>Einsatz verloren</strong></td></tr>
  </table>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
