(async () => {
  const post = (b) => Tippspiel.post('/Tippspiel/api/admin.php', b);
  const get  = (a) => Tippspiel.get('/Tippspiel/api/admin.php?action=' + a);

  // ---- stats ----
  const stats = await get('stats');
  document.getElementById('stats').innerHTML = `
    <div class="card" style="margin:0"><strong>Users</strong><div style="font-size:24px">${stats.users}</div></div>
    <div class="card" style="margin:0"><strong>Spiele</strong><div style="font-size:24px">${stats.matches}</div></div>
    <div class="card" style="margin:0"><strong>Tipps</strong><div style="font-size:24px">${stats.bets}</div></div>
    <div class="card" style="margin:0"><strong>Gruppen</strong><div style="font-size:24px">${stats.groups}</div></div>`;

  async function loadSports() {
    const sports = await Tippspiel.get('/Tippspiel/api/sports.php');
    ['lg-sport','tm-sport','m-sport'].forEach(id => {
      const sel = document.getElementById(id);
      sel.innerHTML = '<option value="">Sportart</option>';
      sports.forEach(s => sel.add(new Option(s.name, s.id)));
    });
  }
  loadSports();

  document.getElementById('sp-add').onclick = async () => {
    try {
      await post({ action:'add_sport',
        name: document.getElementById('sp-name').value.trim(),
        type: document.getElementById('sp-type').value });
      Tippspiel.toast('Sportart hinzugefuegt','ok');
      loadSports();
    } catch(e){ Tippspiel.toast(e.message,'error'); }
  };

  document.getElementById('lg-add').onclick = async () => {
    try {
      await post({ action:'add_league',
        sport_id: document.getElementById('lg-sport').value,
        name:     document.getElementById('lg-name').value.trim(),
        season:   document.getElementById('lg-season').value.trim() });
      Tippspiel.toast('Liga hinzugefuegt','ok');
    } catch(e){ Tippspiel.toast(e.message,'error'); }
  };

  document.getElementById('tm-add').onclick = async () => {
    try {
      await post({ action:'add_team',
        sport_id:   document.getElementById('tm-sport').value,
        name:       document.getElementById('tm-name').value.trim(),
        short_name: document.getElementById('tm-short').value.trim() });
      Tippspiel.toast('Team hinzugefuegt','ok');
    } catch(e){ Tippspiel.toast(e.message,'error'); }
  };

  const mSport  = document.getElementById('m-sport');
  const mLeague = document.getElementById('m-league');
  const mHome   = document.getElementById('m-home');
  const mAway   = document.getElementById('m-away');

  mSport.addEventListener('change', async () => {
    mLeague.innerHTML = '<option value="">Liga / Turnier</option>';
    mHome.innerHTML = '<option value="">Heimteam</option>';
    mAway.innerHTML = '<option value="">Auswaertsteam</option>';
    mLeague.disabled = mHome.disabled = mAway.disabled = !mSport.value;
    if (!mSport.value) return;
    const ls = await Tippspiel.get('/Tippspiel/api/leagues.php?sport_id=' + mSport.value);
    ls.forEach(l => mLeague.add(new Option(l.name, l.id)));

    const teams = await Tippspiel.get('/Tippspiel/api/admin.php?action=list_teams&sport_id=' + mSport.value);
    teams.forEach(t => {
      mHome.add(new Option(t.name, t.id));
      mAway.add(new Option(t.name, t.id));
    });
  });

  document.getElementById('m-add').onclick = async () => {
    try {
      await post({ action:'add_match',
        league_id:    mLeague.value,
        home_team_id: mHome.value,
        away_team_id: mAway.value,
        match_datetime: document.getElementById('m-dt').value.replace('T',' ') + ':00' });
      Tippspiel.toast('Spiel angelegt','ok'); refreshMatches();
    } catch(e){ Tippspiel.toast(e.message,'error'); }
  };

  async function refreshMatches() {
    const list = await get('list_matches');
    document.getElementById('m-list').innerHTML = list.map(m => `
      <tr>
        <td>${m.match_datetime}</td>
        <td>${m.league_name}</td>
        <td>${m.home_name}</td>
        <td>${m.away_name}</td>
        <td>
          <input type="number" min="0" style="width:50px" value="${m.home_score ?? ''}" id="hs-${m.id}">
          :
          <input type="number" min="0" style="width:50px" value="${m.away_score ?? ''}" id="as-${m.id}">
        </td>
        <td><span class="badge">${m.status}</span></td>
        <td><button class="btn small primary" data-set="${m.id}" style="width:auto">Auswerten</button></td>
      </tr>`).join('');
    document.querySelectorAll('[data-set]').forEach(btn => {
      btn.onclick = async () => {
        const id = btn.dataset.set;
        try {
          await post({
            action:'set_result', match_id: id,
            home_score: document.getElementById('hs-'+id).value,
            away_score: document.getElementById('as-'+id).value
          });
          Tippspiel.toast('Resultat gespeichert + Punkte verteilt','ok');
          refreshMatches();
        } catch(e){ Tippspiel.toast(e.message,'error'); }
      };
    });
  }
  refreshMatches();

  async function refreshUsers() {
    const us = await get('list_users');
    document.getElementById('u-list').innerHTML = us.map(u => {
      const broke = Number(u.money_balance) < 10;
      return `
      <tr>
        <td>${u.id}</td>
        <td>${u.first_name} ${u.last_name} (@${u.username})</td>
        <td><span class="badge">${u.role}</span></td>
        <td>${u.points_total}</td>
        <td>${Number(u.money_balance).toFixed(2)} ${broke ? '<span class="badge" style="background:#ef4d4d;color:#fff">pleite</span>' : ''}</td>
        <td>
          <input type="number" min="1" step="1" placeholder="Betrag"
                 id="gift-${u.id}" style="width:70px;background:var(--surface-2);color:var(--text);border:1px solid #2c3447;border-radius:6px;padding:4px">
          <button class="btn small primary" data-gift="${u.id}">Geld geben</button>
          ${u.role==='admin' ? '' : `<button class="btn danger small" data-del="${u.id}">Loeschen</button>`}
        </td>
      </tr>`;
    }).join('');

    document.querySelectorAll('[data-gift]').forEach(b => {
      b.onclick = async () => {
        const id  = b.dataset.gift;
        const amt = Number(document.getElementById('gift-'+id).value);
        if (!amt || amt <= 0) { Tippspiel.toast('Bitte Betrag eingeben','error'); return; }
        try {
          await post({ action:'gift_money', user_id:id, amount:amt });
          Tippspiel.toast('+'+amt.toFixed(2)+' verschenkt', 'ok');
          refreshUsers();
        } catch(e){ Tippspiel.toast(e.message,'error'); }
      };
    });

    document.querySelectorAll('[data-del]').forEach(b => {
      b.onclick = async () => {
        if (!confirm('User wirklich loeschen?')) return;
        try {
          await post({ action:'delete_user', user_id: b.dataset.del });
          Tippspiel.toast('Geloescht','ok'); refreshUsers();
        } catch(e){ Tippspiel.toast(e.message,'error'); }
      };
    });
  }
  refreshUsers();
})();
