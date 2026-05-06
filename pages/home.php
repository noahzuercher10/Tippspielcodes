<?php
$active = 'home';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="card">
  <h2>Willkommen, <?= htmlspecialchars($user['first_name']) ?>!</h2>
  <p>
    Waehle oben rechts deinen <strong>Modus</strong> (Punkte oder imaginaeres Geld) und
    nutze das Menue, um Gruppen zu verwalten, die Rangliste anzuschauen oder
    auf Spiele in den verschiedenen Sportarten zu tippen.
  </p>

  <div class="form-grid" style="margin-top:14px">
    <div class="card" style="margin:0">
      <strong>Punkte gesamt</strong>
      <div style="font-size:28px;color:var(--primary);font-weight:700">
        <?= (int)$user['points_total'] ?>
      </div>
    </div>
    <div class="card" style="margin:0">
      <strong>Imaginaeres Guthaben</strong>
      <div style="font-size:28px;color:var(--accent);font-weight:700">
        <?= number_format((float)$user['money_balance'], 2, '.', "'") ?>
      </div>
    </div>
    <div class="card" style="margin:0">
      <strong>Rolle</strong>
      <div style="font-size:20px;font-weight:700;text-transform:uppercase">
        <?= htmlspecialchars($user['role']) ?>
      </div>
    </div>
  </div>
</section>

<section class="card">
  <h2>Punktesystem (Punktemodus)</h2>
  <ul>
    <li>Genau richtiger Tipp = <strong>10 Punkte</strong></li>
    <li>Richtiger Sieger / Unentschieden = <strong>5 Punkte</strong></li>
    <li>Richtige Anzahl Heimtore = <strong>1 Punkt</strong></li>
    <li>Richtige Anzahl Auswaertstore = <strong>1 Punkt</strong></li>
    <li>Richtige Tordifferenz (nur bei richtigem Sieger-Tipp) = <strong>3 Punkte</strong></li>
  </ul>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
