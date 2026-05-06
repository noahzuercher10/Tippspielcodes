<?php
$active = 'leaderboard';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="card">
  <h2>Rangliste</h2>
  <p style="color:var(--muted)">Wechsle den Modus oben rechts, um zwischen Punkte- und Geld-Rangliste zu wechseln.</p>
  <table>
    <thead><tr><th>#</th><th>User</th><th id="score-th">Punkte</th></tr></thead>
    <tbody id="lb-body"></tbody>
  </table>
</section>
<script>
(async () => {
  async function load() {
    const mode = Tippspiel.getMode();
    document.getElementById('score-th').textContent = mode === 'money' ? 'Guthaben' : 'Punkte';
    const rows = await Tippspiel.get('/tippspiel/api/leaderboard.php?mode=' + mode);
    document.getElementById('lb-body').innerHTML = rows.map((r,i)=>`
      <tr>
        <td>${i+1}</td>
        <td>${r.first_name} ${r.last_name} (@${r.username})</td>
        <td>${mode==='money' ? Number(r.score).toFixed(2) : r.score}</td>
      </tr>`).join('');
  }
  load();
  document.addEventListener('mode-changed', load);
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
