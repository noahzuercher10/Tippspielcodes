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

<section class="card">
  <h2>Formel 1 – Punktemodus</h2>
  <p style="color:var(--muted);font-size:13px;margin:0 0 10px">Tippe die Top-10-Fahrer eines Rennens (P1–P10). Pro korrekt getipptem Fahrer auf der richtigen Position:</p>
  <table style="max-width:420px">
    <tr><td>Fahrer auf exakter Position</td><td><strong style="color:var(--primary)">2 Pkt</strong></td></tr>
    <tr><td>Fahrer falsch getippt</td><td><strong>0 Pkt</strong></td></tr>
    <tr><td>Max. pro Rennen (10 × 2)</td><td><strong>20 Pkt</strong></td></tr>
  </table>
  <p style="color:var(--muted);font-size:12px;margin:10px 0 0">Jeder Fahrer kann pro Rennen nur einmal ausgewählt werden.</p>
</section>

<section class="card">
  <h2>Formel 1 – Geldmodus (Podium)</h2>
  <p style="color:var(--muted);font-size:13px;margin:0 0 10px">Tippe die Top-3-Fahrer (Podium). Gesamteinsatz wird gleichmässig auf P1, P2 und P3 aufgeteilt.</p>
  <table style="max-width:500px">
    <tr><td>Fahrer auf genauer Position (P1/P2/P3)</td><td><strong style="color:var(--accent)">Einsatz/3 × 2</strong></td></tr>
    <tr><td>Fahrer im Podium, aber falsche Position</td><td><strong>Einsatz/3 × 1 <span style="color:var(--muted);font-size:12px">(nichts verloren)</span></strong></td></tr>
    <tr><td>Fahrer nicht im Podium</td><td><strong style="color:var(--danger)">Einsatz/3 verloren</strong></td></tr>
  </table>
  <p style="color:var(--muted);font-size:12px;margin:10px 0 0">Beispiel: Gesamteinsatz 300 → je 100 auf P1/P2/P3. Alle 3 exakt richtig = 600 zurück.</p>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
