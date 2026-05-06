/* Sportarten / Tippen */
(async () => {
  const sportSel  = document.getElementById('sport');
  const leagueSel = document.getElementById('league');
  const daySel    = document.getElementById('day');
  const groupSel  = document.getElementById('group');
  const balanceEl = document.getElementById('balance');
  const matchesEl = document.getElementById('matches');
  const progressBar   = document.getElementById('tip-progress-bar');
  const progressLabel = document.getElementById('tip-progress-label');

  // Sportarten laden
  const sports = await Tippspiel.get('/tippspiel/api/sports.php');
  sports.forEach(s => sportSel.add(new Option(s.name, s.id)));

  // Eigene Gruppen laden
  const myGroups = await Tippspiel.get('/tippspiel/api/groups.php');
  myGroups.forEach(g => groupSel.add(new Option(`${g.name} (${g.mode})`, g.id)));

  // Modus-Anzeige (Guthaben)
  const me = await Tippspiel.get('/tippspiel/api/me.php');
  function refreshBalance() {
    if (Tippspiel.getMode() === 'money') {
      balanceEl.style.display = 'inline-block';
      balanceEl.textContent = `Guthaben: ${me.money_balance.toFixed(2)} (max ${me.max_stake.toFixed(2)} pro Tipp)`;
    } else {
      balanceEl.style.display = 'none';
    }
  }
  refreshBalance();
  document.addEventListener('mode-changed', () => { refreshBalance(); loadMatches(); });

  sportSel .addEventListener('change', loadLeagues);
  leagueSel.addEventListener('change', loadMatches);
  daySel   .addEventListener('change', loadMatches);
  groupSel .addEventListener('change', loadMatches);

  async function loadLeagues() {
    leagueSel.innerHTML = '<option value="">Liga / Turnier</option>';
    leagueSel.disabled = !sportSel.value;
    matchesEl.innerHTML = '';
    if (!sportSel.value) return;
    const ls = await Tippspiel.get('/tippspiel/api/leagues.php?sport_id=' + sportSel.value);
    ls.forEach(l => leagueSel.add(new Option(l.season ? `${l.name} ${l.season}` : l.name, l.id)));
  }

  async function loadMatches() {
    if (!leagueSel.value) { matchesEl.innerHTML = ''; return; }
    const params = new URLSearchParams({
      league_id: leagueSel.value,
      date:      daySel.value,
      mode:      Tippspiel.getMode(),
    });
    if (groupSel.value) params.set('group_id', groupSel.value);

    const data = await Tippspiel.get('/tippspiel/api/matches.php?' + params);
    renderMatches(data);
  }

  function renderMatches({ matches, total_users }) {
    if (!matches.length) {
      matchesEl.innerHTML = '<p style="color:var(--muted)">Keine Spiele an diesem Tag.</p>';
      progressBar.style.width = '0%';
      progressLabel.textContent = '';
      return;
    }
    // Tippquote: tippte mind. 1 Spiel des Tages
    const totalTips = matches.reduce((a, m) => a + Number(m.tip_count || 0), 0);
    const max = matches.length * (total_users || 1);
    const pct = max ? Math.round(totalTips / max * 100) : 0;
    progressBar.style.width = pct + '%';
    progressLabel.textContent = `Bereits ${totalTips} Tipps abgegeben (${pct} %)`;

    matchesEl.innerHTML = matches.map(m => {
      const start = new Date(m.match_datetime);
      const closed = m.status !== 'upcoming' || start.getTime() <= Date.now();
      const tipH = m.my_bet ? m.my_bet.tip_home : '';
      const tipA = m.my_bet ? m.my_bet.tip_away : '';
      const stake = m.my_bet ? m.my_bet.stake : '';
      const result = (m.home_score !== null && m.away_score !== null)
        ? `Resultat: ${m.home_score} : ${m.away_score}` : '';
      return `
        <div class="match" data-id="${m.id}">
          <div class="tname">${m.home_name}</div>
          <input type="number" min="0" class="tipH" value="${tipH}" ${closed?'disabled':''}>
          <input type="number" min="0" class="tipA" value="${tipA}" ${closed?'disabled':''}>
          <div class="tname away">${m.away_name}</div>
          <div>
            <div style="font-size:11px;color:var(--muted)">${start.toLocaleString('de-CH')} ${result}</div>
            ${Tippspiel.getMode()==='money'
              ? `<input type="number" step="0.01" min="0" placeholder="Einsatz" class="stake" value="${stake||''}" ${closed?'disabled':''}>`
              : ''}
            ${closed
              ? `<span class="badge">${m.status==='finished'?'beendet':'gestartet'}</span>`
              : `<button class="btn save small">Tipp speichern</button>`}
          </div>
        </div>`;
    }).join('');

    matchesEl.querySelectorAll('.save').forEach(btn => {
      btn.addEventListener('click', () => saveTip(btn.closest('.match')));
    });
  }

  async function saveTip(row) {
    const tipH = Number(row.querySelector('.tipH').value);
    const tipA = Number(row.querySelector('.tipA').value);
    const stakeEl = row.querySelector('.stake');
    const body = {
      match_id: Number(row.dataset.id),
      mode:     Tippspiel.getMode(),
      tip_home: tipH,
      tip_away: tipA,
      stake:    stakeEl ? Number(stakeEl.value || 0) : 0,
      group_id: groupSel.value || null,
    };
    if (Number.isNaN(tipH) || Number.isNaN(tipA) || tipH<0 || tipA<0) {
      Tippspiel.toast('Ungueltiger Tipp', 'error'); return;
    }
    try {
      await Tippspiel.post('/tippspiel/api/bets.php', body);
      Tippspiel.toast('Tipp gespeichert!', 'ok');
      // Guthaben neu laden im Geldmodus
      if (body.mode === 'money') {
        const me2 = await Tippspiel.get('/tippspiel/api/me.php');
        Object.assign(me, me2); refreshBalance();
      }
    } catch (e) {
      Tippspiel.toast(e.message, 'error');
    }
  }
})();
