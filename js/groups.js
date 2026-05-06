(async () => {
  const list   = document.getElementById('groups');
  const sportSel  = document.getElementById('g-sport');
  const leagueSel = document.getElementById('g-league');
  const nameI  = document.getElementById('g-name');
  const codeI  = document.getElementById('g-code');
  const modeS  = document.getElementById('g-mode');

  async function refresh() {
    const groups = await Tippspiel.get('/tippspiel/api/groups.php');
    if (!groups.length) {
      list.innerHTML = '<p style="color:var(--muted)">Du bist noch in keiner Gruppe.</p>';
      return;
    }
    list.innerHTML = groups.map(g => `
      <details class="card" style="padding:12px;background:var(--surface-2)">
        <summary style="cursor:pointer">
          <strong>${g.name}</strong>
          <span class="badge ${g.mode}">${g.mode}</span>
          &nbsp;${g.sport_name} · ${g.league_name}
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
        const id = d.querySelector('[data-detail]').dataset.detail;
        const target = d.querySelector('[data-detail]');
        if (target.dataset.loaded) return;
        const detail = await Tippspiel.get('/tippspiel/api/groups.php?id=' + id);
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
        await Tippspiel.post('/tippspiel/api/groups.php', { action:'leave', group_id: btn.dataset.leave });
        Tippspiel.toast('Gruppe verlassen', 'ok'); refresh();
      });
    });
  }

  // Create-Form
  (async () => {
    const sports = await Tippspiel.get('/tippspiel/api/sports.php');
    sports.forEach(s => sportSel.add(new Option(s.name, s.id)));
  })();
  sportSel.addEventListener('change', async () => {
    leagueSel.innerHTML = '<option value="">Liga / Turnier</option>';
    leagueSel.disabled = !sportSel.value;
    if (!sportSel.value) return;
    const ls = await Tippspiel.get('/tippspiel/api/leagues.php?sport_id=' + sportSel.value);
    ls.forEach(l => leagueSel.add(new Option(l.season ? `${l.name} ${l.season}` : l.name, l.id)));
  });

  document.getElementById('g-create').addEventListener('click', async () => {
    try {
      const r = await Tippspiel.post('/tippspiel/api/groups.php', {
        action: 'create',
        name:   nameI.value.trim(),
        join_code: codeI.value.trim(),
        mode:   modeS.value,
        league_id: leagueSel.value
      });
      Tippspiel.toast('Gruppe erstellt - Code: ' + r.join_code, 'ok');
      nameI.value = ''; codeI.value = '';
      refresh();
    } catch (e) { Tippspiel.toast(e.message, 'error'); }
  });

  document.getElementById('join-btn').addEventListener('click', async () => {
    try {
      await Tippspiel.post('/tippspiel/api/groups.php', {
        action: 'join', join_code: document.getElementById('join-code').value.trim()
      });
      Tippspiel.toast('Beigetreten!', 'ok');
      refresh();
    } catch (e) { Tippspiel.toast(e.message, 'error'); }
  });

  refresh();
})();
