<?php
$active = 'admin';
require_once __DIR__ . '/../includes/auth.php';
$user = require_admin();      // strikt nur Admin
require_once __DIR__ . '/../includes/header.php';
?>
<section class="card">
  <h2>Admin-Dashboard</h2>
  <div id="stats" class="form-grid"></div>
</section>

<section class="card">
  <h2>Sportart hinzufuegen</h2>
  <div class="form-grid">
    <input id="sp-name" placeholder="Name (z.B. Volleyball)">
    <select id="sp-type"><option value="team">Team-Sport</option><option value="single">Einzel-Sport</option></select>
    <button class="btn primary" id="sp-add">Hinzufuegen</button>
  </div>
</section>

<section class="card">
  <h2>Liga / Turnier hinzufuegen</h2>
  <div class="form-grid">
    <select id="lg-sport"></select>
    <input id="lg-name"  placeholder="Liga-Name">
    <input id="lg-season" placeholder="Saison (z.B. 2025/26)">
    <button class="btn primary" id="lg-add">Hinzufuegen</button>
  </div>
</section>

<section class="card">
  <h2>Team hinzufuegen</h2>
  <div class="form-grid">
    <select id="tm-sport"></select>
    <input id="tm-name"  placeholder="Teamname">
    <input id="tm-short" placeholder="Kuerzel">
    <button class="btn primary" id="tm-add">Hinzufuegen</button>
  </div>
</section>

<section class="card">
  <h2>Spiel anlegen</h2>
  <div class="form-grid">
    <select id="m-sport"></select>
    <select id="m-league" disabled></select>
    <select id="m-home"   disabled></select>
    <select id="m-away"   disabled></select>
    <input  id="m-dt" type="datetime-local">
    <button class="btn primary" id="m-add">Spiel anlegen</button>
  </div>
</section>

<section class="card">
  <h2>Resultate eintragen / Punkte berechnen</h2>
  <table>
    <thead><tr><th>Datum</th><th>Liga</th><th>Heim</th><th>Gast</th><th>Resultat</th><th>Status</th><th></th></tr></thead>
    <tbody id="m-list"></tbody>
  </table>
</section>

<section class="card">
  <h2>Benutzerverwaltung</h2>
  <table>
    <thead><tr><th>ID</th><th>User</th><th>Rolle</th><th>Punkte</th><th>Geld</th><th></th></tr></thead>
    <tbody id="u-list"></tbody>
  </table>
</section>

<script src="/Tippspiel/js/admin.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
