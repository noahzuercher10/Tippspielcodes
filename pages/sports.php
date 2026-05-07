<?php
$active = 'sports';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="card">
  <h2>Sportarten &amp; Tippen</h2>
  <p style="color:var(--muted);margin-top:0">
    Wähle zuerst eine Sportart. Sobald eine Sportart ausgewählt ist,
    erscheint das Dropdown für die Liga / das Turnier.
  </p>

  <div class="dropdowns">
    <label for="sport" class="dd">Sportart
      <select id="sport"><option value="">-- Sportart wählen --</option></select>
    </label>

    <label id="league-wrap" for="league" class="dd" style="display:none">Liga / Turnier
      <select id="league"><option value="">-- Liga / Turnier wählen --</option></select>
    </label>

    <label id="day-wrap" for="day" class="dd" style="display:none">Tag
      <input type="date" id="day" value="<?= date('Y-m-d') ?>">
    </label>

    <label id="group-wrap" for="group" class="dd" style="display:none">In Gruppe (optional)
      <select id="group"><option value="">Ohne Gruppe</option></select>
    </label>

    <span class="balance" id="balance" style="display:none"></span>
  </div>

  <div class="tip-progress-label" id="tip-progress-label" style="margin-top:14px"></div>
  <div class="tip-progress"><div id="tip-progress-bar" style="width:0%"></div></div>

  <div id="matches"></div>
</section>
<script src="/Tippspiel/js/sports.js?v=3"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>