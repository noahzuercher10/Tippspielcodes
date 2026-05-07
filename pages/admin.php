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
  <p style="color:var(--muted);margin-top:0">
    Eine neue Sportart braucht den Namen einer PHP-Klasse unter
    <code>includes/sports/</code> (z.B. <code>FootballSport</code>),
    die von <code>SportApi</code> erbt.
  </p>
  <div class="form-grid">
    <input id="sp-name" placeholder="Name (z.B. Volleyball)">
    <select id="sp-type">
      <option value="team">Team-Sport</option>
      <option value="single">Einzel-Sport</option>
    </select>
    <input id="sp-class" placeholder="API-Klasse (z.B. FootballSport)">
    <button class="btn primary" id="sp-add">Hinzufuegen</button>
  </div>
</section>

<section class="card">
  <h2>Liga / Turnier hinzufuegen</h2>
  <div class="form-grid">
    <select id="lg-sport"></select>
    <input id="lg-name"  placeholder="Liga-Name">
    <input id="lg-season" placeholder="Saison (z.B. 2025-2026)">
    <input id="lg-apiid"  placeholder="TheSportsDB-ID (z.B. 4328)">
    <button class="btn primary" id="lg-add">Hinzufuegen</button>
  </div>
</section>

<section class="card">
  <h2>Liga aus TheSportsDB synchronisieren</h2>
  <p style="color:var(--muted);margin-top:0">
    Spiele werden zwar bei jedem Laden automatisch nachgeholt - hier
    kannst du es manuell auf jetzt zwingen.
  </p>
  <div class="form-grid">
    <select id="sync-sport"></select>
    <select id="sync-league" disabled></select>
    <button class="btn primary" id="sync-go">Diese Liga jetzt syncen</button>
    <button class="btn" id="sync-all">ALLE Ligen jetzt syncen</button>
  </div>
  <div id="sync-report" style="margin-top:10px;color:var(--muted);font-size:13px"></div>
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

<script src="/Tippspiel/js/admin.js?v=2"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
