<?php
$active = 'sports';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="card">
  <h2>Tippen</h2>

  <div class="dropdowns">
    <label for="sport" class="dd">Sportart
      <select id="sport"><option value="">– wählen –</option></select>
    </label>
    <label id="league-wrap" for="league" class="dd" style="display:none">Liga / Turnier
      <select id="league"><option value="">– wählen –</option></select>
    </label>
    <label id="day-wrap" for="day" class="dd" style="display:none">Tag
      <input type="date" id="day" value="<?= date('Y-m-d') ?>">
    </label>
    <label id="group-wrap" for="group" class="dd" style="display:none">Gruppe (optional)
      <select id="group"><option value="">Ohne Gruppe</option></select>
    </label>
    <span class="balance" id="balance" style="display:none"></span>
    <button id="refresh-btn" class="btn" style="display:none;align-self:flex-end">Aktualisieren</button>
  </div>

  <div class="tip-progress-label" id="tip-progress-label" style="margin-top:14px"></div>
  <div class="tip-progress"><div id="tip-progress-bar" style="width:0%"></div></div>

  <div id="matches"></div>
</section>
<script src="/Tippspiel/js/sports.js?v=6"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
