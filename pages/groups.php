<?php
$active = 'groups';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="card">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <h2 style="margin:0">Meine Gruppen</h2>
    <div class="dropdowns" style="margin:0">
      <button id="btn-create" class="btn primary">Gruppe erstellen</button>
      <button id="btn-join"   class="btn">Gruppe beitreten</button>
    </div>
  </div>
  <div id="groups" style="margin-top:14px"></div>
</section>

<!-- Modal: Erstellen -->
<dialog id="dlg-create" class="modal">
  <form method="dialog">
    <h3>Neue Gruppe erstellen</h3>
    <label>Gruppenname
      <input id="g-name" required>
    </label>
    <label>Sportart
      <select id="g-sport"><option value="">Sportart wählen</option></select>
    </label>
    <label>Liga / Turnier
      <select id="g-league" disabled><option value="">Erst Sportart wählen</option></select>
    </label>
    <label>Modus
      <select id="g-mode">
        <option value="points">Punkte-Modus</option>
        <option value="money">Geld-Modus</option>
      </select>
    </label>
    <label>Beitrittscode
      <input id="g-code" placeholder="leer = automatisch">
    </label>
    <div class="modal-actions">
      <button type="button" class="btn" id="g-cancel">Abbrechen</button>
      <button type="button" class="btn primary" id="g-submit">Erstellen</button>
    </div>
  </form>
</dialog>

<!-- Modal: Beitreten -->
<dialog id="dlg-join" class="modal">
  <form method="dialog">
    <h3>Einer Gruppe beitreten</h3>
    <label>Beitrittscode
      <input id="j-code" required placeholder="z.B. SINAN26">
    </label>
    <div class="modal-actions">
      <button type="button" class="btn" id="j-cancel">Abbrechen</button>
      <button type="button" class="btn primary" id="j-submit">Beitreten</button>
    </div>
  </form>
</dialog>

<script src="/Tippspiel/js/groups.js?v=2"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>