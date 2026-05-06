<?php
$active = 'groups';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="card">
  <h2>Meine Gruppen</h2>
  <div id="groups"></div>
</section>

<section class="card">
  <h2>Neue Gruppe erstellen</h2>
  <div class="form-grid">
    <input id="g-name"   placeholder="Gruppenname">
    <input id="g-code"   placeholder="Beitrittscode (optional)">
    <select id="g-mode">
      <option value="points">Punkte-Modus</option>
      <option value="money">Geld-Modus</option>
    </select>
    <select id="g-sport"><option value="">Sportart</option></select>
    <select id="g-league" disabled><option value="">Liga / Turnier</option></select>
  </div>
  <button id="g-create" class="btn primary" style="max-width:220px">Gruppe erstellen</button>
</section>

<section class="card">
  <h2>Einer Gruppe beitreten</h2>
  <div class="dropdowns">
    <input id="join-code" placeholder="Beitrittscode">
    <button id="join-btn" class="btn">Beitreten</button>
  </div>
</section>
<script src="/tippspiel/js/groups.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
