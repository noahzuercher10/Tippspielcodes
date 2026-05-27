<?php
/**
 * ============================================================
 * Page: Rangliste
 * ------------------------------------------------------------
 * Globale Top-100 nach Punkten oder Geld.
 *
 * Setzt $active fuer das Hervorheben des aktiven Nav-Links und
 * includiert dann den globalen Header/Footer.
 * ============================================================
 */
$active = 'leaderboard';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="card">
  <h2>Rangliste</h2>
  <div class="lb-tabs" id="lb-tabs"></div>

  <h3 id="lb-title" style="margin:16px 0 8px;font-size:15px;color:var(--muted)">Globale Rangliste</h3>
  <table>
    <thead><tr><th>#</th><th>User</th><th id="score-th">Punkte</th></tr></thead>
    <tbody id="lb-body"></tbody>
  </table>
</section>

<script>
(async () => {
  const tabsEl   = document.getElementById('lb-tabs');
  const titleEl  = document.getElementById('lb-title');
  const scoreTh  = document.getElementById('score-th');
  const bodyEl   = document.getElementById('lb-body');

  let activeTab = 'global';

  function buildTabs(groups) {
    let html = `<button class="lb-tab ${activeTab==='global'?'active':''}" data-tab="global">Global</button>`;
    groups.forEach(g => {
      html += `<button class="lb-tab ${activeTab==g.id?'active':''}" data-tab="${g.id}">
                 ${g.name}
                 <span class="badge ${g.mode}">${g.mode}</span>
               </button>`;
    });
    tabsEl.innerHTML = html;
    tabsEl.querySelectorAll('[data-tab]').forEach(btn => {
      btn.onclick = () => { activeTab = btn.dataset.tab; load(); };
    });
  }

  async function load() {
    const mode = Tippspiel.getMode();
    scoreTh.textContent = mode === 'money' ? 'Guthaben' : 'Punkte';

    const groups = await Tippspiel.get('/Tippspiel/api/groups.php');
    const myGroups = groups.filter(g => g.mode === mode);
    buildTabs(myGroups);

    let rows;
    if (activeTab === 'global') {
      titleEl.textContent = 'Globale Rangliste (' + (mode==='money'?'Geld':'Punkte') + ')';
      rows = await Tippspiel.get('/Tippspiel/api/leaderboard.php?mode=' + mode);
    } else {
      const detail = await Tippspiel.get('/Tippspiel/api/groups.php?id=' + activeTab);
      titleEl.textContent = 'Gruppe: ' + detail.name;
      rows = detail.members.map(m => ({
        username:   m.username,
        first_name: m.first_name,
        last_name:  m.last_name,
        score: mode === 'money' ? Number(m.money) : Number(m.points),
      }));
    }

    const rankClass = i => i===0?'rank-1':i===1?'rank-2':i===2?'rank-3':'';
    bodyEl.innerHTML = rows.map((r,i)=>`
      <tr>
        <td class="${rankClass(i)}">${i+1}</td>
        <td>${r.first_name} ${r.last_name} <span style="color:var(--muted)">@${r.username}</span></td>
        <td>${mode==='money' ? Number(r.score).toFixed(2) : r.score}</td>
      </tr>`).join('');
  }

  document.addEventListener('mode-changed', () => { activeTab = 'global'; load(); });
  load();
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>