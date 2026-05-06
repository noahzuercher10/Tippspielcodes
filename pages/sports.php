<?php
$active = 'sports';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="card">
  <h2>Sportarten &amp; Tippen</h2>

  <div class="dropdowns">
    <select id="sport"><option value="">Sportart waehlen</option></select>
    <select id="league" disabled><option value="">Liga / Turnier</option></select>
    <input type="date" id="day" value="<?= date('Y-m-d') ?>">
    <select id="group" title="In Gruppe tippen (optional)">
      <option value="">Ohne Gruppe</option>
    </select>
    <span class="balance" id="balance" style="display:none"></span>
  </div>

  <div class="tip-progress-label" id="tip-progress-label" style="margin-top:14px"></div>
  <div class="tip-progress"><div id="tip-progress-bar" style="width:0%"></div></div>

  <div id="matches"></div>
</section>
<script src="/tippspiel/js/sports.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
