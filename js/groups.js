/* Gruppen: Liste + Modal-Dialoge fuer Erstellen/Beitreten */
(async () => {
  const list = document.getElementById('groups');
  const dlgC = document.getElementById('dlg-create');
  const dlgJ = document.getElementById('dlg-join');

  document.getElementById('btn-create').onclick = () => dlgC.showModal();
  document.getElementById('btn-join').onclick   = () => dlgJ.showModal();
  document.getElementById('g-cancel').onclick   = () => dlgC.close();
  document.getElementById('j-cancel').onclick   = () => dlgJ.close();

  const sportSel  = document.getElementById('g-sport');
  const leagueSel = document.getElementById('g-league');

  const sports = await Tippspiel.get('/Tippspiel/api/sports.php');
  sports.forEach(s => sportSel.add(new Option(s.name, s.id)));

  sportSel.addEventListener('change', async () => {
    leagueSel.innerHTML = '<option value="">Liga / Turnier</option>';
    leagueSel.disabled = !sportSel.value;
    if (!sportSel.value) return;
    const ls = await Tippspiel.get('/Tippspiel/api/leagues.php?sport_id=' + sportSel.value);
    ls.forEach(l => leagueSel.add(new Option(l.season ? `${l.name} ${l.season}` : l.name, l.id)));
  });

  document.getElementById('g-submit').onclick = async () => {
    try {
      const r = await Tippspiel.post('/Tippspiel/api/groups.php', {
        action: 'create',
        name:      document.getElementById('g-name').value.trim(),
        join_code: document.getElementById('g-code').value.trim(),
        mode:      document.getElementById('g-mode').value,
        league_id: leagueSel.value,
      });
      Tippspiel.toast('Gruppe erstellt - Code: ' + r.join_code, 'ok');
      dlgC.close();
      refresh();
    } catch (e) { Tippspiel.toast(e.message, 'error'); }
  };

  document.getElementById('j-submit').onclick = async () => {
    try {
      await Tippspiel.post('/Tippspiel/api/groups.php', {
        action: 'join',
        join_code: document.getElementById('j-code').value.trim(),
      });
      Tippspiel.toast('Beigetreten!', 'ok');
      dlgJ.close();
      refresh();
    } catch (e) { Tippspiel.toast(e.message, 'error'); }
  };

  async function refresh() {
    const groups = await Tippspiel.get('/Tippspiel/api/groups.php');
    if (!groups.length) {
      list.innerHTML = '<p style="color:var(--muted)">Du bist noch in keiner Gruppe. Druecke oben auf "Gruppe erstellen" oder "Gruppe beitreten".</p>';
      return;
    }
    list.innerHTML = groups.map(g => `
      <details class="card" style="padding:12px;background:var(--surface-2)">
        <summary style="cursor:pointer">
          <strong>${g.name}</strong>
          <span class="badge ${g.mode}">${g.mode}</span>
          &nbsp;${g.sport_name} - ${g.league_name}
          <span class="badge">${g.member_count} Mitglieder</span>
          <span class="badge">Code: ${g.join_code}</span>
        </summary>
        <div data-detail="${g.id}" style="margin-top:10px"></div>
        <button class="btn small danger" data-leave="${g.id}" style="margin-top:8px">Verlassen</button>
      </details>
    `).join('');

    list.querySelectorAll('details').forEach(d => {
      d.addEventListener('toggle', async () => {
        if (!d.open) return;
        const target = d.querySelector('[data-detail]');
        if (target.dataset.loaded) return;
        const id     = target.dataset.detail;
        const detail = await Tippspiel.get('/Tippspiel/api/groups.php?id=' + id);
        target.dataset.loaded = '1';
        const col = detail.mode === 'money' ? 'money' : 'points';
        target.innerHTML = `
          <table>
            <thead><tr><th>#</th><th>User</th><th>${col === 'money' ? 'Geld' : 'Punkte'}</th></tr></thead>
            <tbody>
              ${detail.members.map((m,i)=>`
                <tr><td>${i+1}</td>
                    <td>${m.first_name} ${m.last_name} (@${m.username})</td>
                    <td>${col==='money' ? Number(m.money).toFixed(2) : m.points}</td></tr>`).join('')}
            </tbody>
          </table>`;
      });
    });

    list.querySelectorAll('[data-leave]').forEach(btn => {
      btn.addEventListener('click', async () => {
        if (!confirm('Gruppe wirklich verlassen?')) return;
        await Tippspiel.post('/Tippspiel/api/groups.php', { action:'leave', group_id: btn.dataset.leave });
        Tippspiel.toast('Gruppe verlassen', 'ok'); refresh();
      });
    });
  }

  refresh();
})();
