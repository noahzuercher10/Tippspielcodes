/* Sportarten / Tippen
   - Sportart-Dropdown immer sichtbar
   - Liga erscheint nach Sportauswahl
   - Datum + Gruppe erscheinen nach Liga-Auswahl
   - Punktemodus: exakter Tipp (Heim:Aus)
   - Geldmodus: nur Sieger / Unentschieden + Einsatz (10..500)
*/
(async () => {
  const sportSel  = document.getElementById('sport');
  const leagueSel = document.getElementById('league');
  const daySel    = document.getElementById('day');
  const groupSel  = document.getElementById('group');

  const leagueWrap = document.getElementById('league-wrap');
  const dayWrap    = document.getElementById('day-wrap');
  const groupWrap  = document.getElementById('group-wrap');
  const balanceEl  = document.getElementById('balance');
  const matchesEl  = document.getElementById('matches');
  const progressBar   = document.getElementById('tip-progress-bar');
  const progressLabel = document.getElementById('tip-progress-label');

  const sports = await Tippspiel.get('/Tippspiel/api/sports.php');
  sports.forEach(s => sportSel.add(new Option(s.name, s.id)));

  const myGroups = await Tippspiel.get('/Tippspiel/api/groups.php');
  function fillGroups() {
    const mode = Tippspiel.getMode();
    groupSel.innerHTML = '<option value="">Ohne Gruppe</option>';
    myGroups.filter(g => g.mode === mode).forEach(g =>
      groupSel.add(new Option(g.name, g.id)));
  }
  fillGroups();

  const me = await Tippspiel.get('/Tippspiel/api/me.php');
  function refreshBalance() {
    if (Tippspiel.getMode() === 'money') {
      balanceEl.style.display = 'inline-block';
      balanceEl.textContent =
        `Guthaben: ${me.money_balance.toFixed(2)} | max ${me.max_stake.toFixed(2)} pro Tipp`;
    } else {
      balanceEl.style.display = 'none';
    }
  }
  refreshBalance();
  document.addEventListener('mode-changed', () => {
    fillGroups(); refreshBalance(); loadMatches();
  });

  sportSel .addEventListener('change', onSportChange);
  leagueSel.addEventListener('change', onLeagueChange);
  daySel   .addEventListener('change', loadMatches);
  groupSel .addEventListener('change', loadMatches);

  async function onSportChange() {
    leagueWrap.style.display = 'none';
    dayWrap.style.display    = 'none';
    groupWrap.style.display  = 'none';
    leagueSel.innerHTML = '<option value="">-- Liga / Turnier waehlen --</option>';
    matchesEl.innerHTML = '';
    progressBar.style.width = '0%'; progressLabel.textContent = '';
    if (!sportSel.value) return;
    const ls = await Tippspiel.get('/Tippspiel/api/leagues.php?sport_id=' + sportSel.value);
    if (!ls.length) {
      Tippspiel.toast('Fuer diese Sportart sind noch keine Ligen vorhanden.', 'info');
      return;
    }
    ls.forEach(l => leagueSel.add(new Option(l.season ? `${l.name} ${l.season}` : l.name, l.id)));
    leagueWrap.style.display = 'inline-flex';
  }

  function onLeagueChange() {
    matchesEl.innerHTML = '';
    progressBar.style.width = '0%'; progressLabel.textContent = '';
    if (!leagueSel.value) {
      dayWrap.style.display = 'none'; groupWrap.style.display = 'none'; return;
    }
    dayWrap.style.display   = 'inline-flex';
    groupWrap.style.display = 'inline-flex';
    loadMatches();
  }

  async function loadMatches() {
    if (!leagueSel.value) { matchesEl.innerHTML = ''; return; }
    const params = new URLSearchParams({
      league_id: leagueSel.value,
      date:      daySel.value,
      mode:      Tippspiel.getMode(),
    });
    if (groupSel.value) params.set('group_id', groupSel.value);
    const data = await Tippspiel.get('/Tippspiel/api/matches.php?' + params);
    renderMatches(data);
  }

  function renderMatches({ matches, total_users }) {
    if (!matches.length) {
      matchesEl.innerHTML = '<p style="color:var(--muted)">Keine Spiele an diesem Tag.</p>';
      progressBar.style.width = '0%'; progressLabel.textContent = '';
      return;
    }
    const totalTips = matches.reduce((a, m) => a + Number(m.tip_count || 0), 0);
    const max = matches.length * (total_users || 1);
    const pct = max ? Math.round(totalTips / max * 100) : 0;
    progressBar.style.width = pct + '%';
    progressLabel.textContent = `Bereits ${totalTips} Tipps abgegeben (${pct} %)`;

    const mode = Tippspiel.getMode();
    matchesEl.innerHTML = matches.map(m => {
      const start  = new Date(m.match_datetime);
      const closed = m.status !== 'upcoming' || start.getTime() <= Date.now();
      const result = (m.home_score !== null && m.away_score !== null)
        ? `Resultat: ${m.home_score} : ${m.away_score}` : '';

      if (mode === 'points') {
        const tipH = m.my_bet ? m.my_bet.tip_home : '';
        const tipA = m.my_bet ? m.my_bet.tip_away : '';
        return `
          <div class="match" data-id="${m.id}">
            <div class="tname">${m.home_name}</div>
            <input type="number" min="0" class="tipH" value="${tipH ?? ''}" ${closed?'disabled':''}>
            <input type="number" min="0" class="tipA" value="${tipA ?? ''}" ${closed?'disabled':''}>
            <div class="tname away">${m.away_name}</div>
            <div>
              <div class="meta">${start.toLocaleString('de-CH')} ${result}</div>
              ${closed
                ? `<span class="badge">${m.status==='finished'?'beendet':'gestartet'}</span>`
                : `<button class="btn save small">Tipp speichern</button>`}
            </div>
          </div>`;
      } else {
        const tw    = m.my_bet ? m.my_bet.tip_winner : '';
        const stake = m.my_bet ? Number(m.my_bet.stake).toFixed(2) : '';
        const sel = (v) => tw === v ? 'selected' : '';
        return `
          <div class="match money" data-id="${m.id}">
            <div class="tname">${m.home_name}</div>
            <div class="winner-row">
              <select class="winner" ${closed?'disabled':''}>
                <option value="">-- Sieger waehlen --</option>
                <option value="home" ${sel('home')}>Heimsieg (${m.home_short || m.home_name})</option>
                <option value="draw" ${sel('draw')}>Unentschieden</option>
                <option value="away" ${sel('away')}>Auswaertssieg (${m.away_short || m.away_name})</option>
              </select>
              <input type="number" step="0.01" min="10" max="500"
                     class="stake" placeholder="Einsatz (10-500)" value="${stake}" ${closed?'disabled':''}>
            </div>
            <div class="tname away">${m.away_name}</div>
            <div>
              <div class="meta">${start.toLocaleString('de-CH')} ${result}</div>
              ${closed
                ? `<span class="badge">${m.status==='finished'?'beendet':'gestartet'}</span>`
                : `<button class="btn save small">Tipp speichern</button>`}
            </div>
          </div>`;
      }
    }).join('');

    matchesEl.querySelectorAll('.save').forEach(btn => {
      btn.addEventListener('click', () => saveTip(btn.closest('.match')));
    });
  }

  async function saveTip(row) {
    const mode = Tippspiel.getMode();
    let body;
    if (mode === 'points') {
      const tipH = Number(row.querySelector('.tipH').value);
      const tipA = Number(row.querySelector('.tipA').value);
      if (Number.isNaN(tipH) || Number.isNaN(tipA) || tipH<0 || tipA<0) {
        Tippspiel.toast('Bitte Heim- und Auswaertstore eingeben', 'error'); return;
      }
      body = { match_id:Number(row.dataset.id), mode, tip_home:tipH, tip_away:tipA,
               group_id: groupSel.value || null };
    } else {
      const tw    = row.querySelector('.winner').value;
      const stake = Number(row.querySelector('.stake').value);
      if (!['home','draw','away'].includes(tw)) {
        Tippspiel.toast('Bitte Sieger oder Unentschieden tippen', 'error'); return;
      }
      if (!stake || stake < 10 || stake > 500) {
        Tippspiel.toast('Einsatz zwischen 10 und 500', 'error'); return;
      }
      body = { match_id:Number(row.dataset.id), mode, tip_winner:tw, stake,
               group_id: groupSel.value || null };
    }
    try {
      await Tippspiel.post('/Tippspiel/api/bets.php', body);
      Tippspiel.toast('Tipp gespeichert!', 'ok');
      if (mode === 'money') {
        const me2 = await Tippspiel.get('/Tippspiel/api/me.php');
        Object.assign(me, me2); refreshBalance();
      }
    } catch (e) { Tippspiel.toast(e.message, 'error'); }
  }
})();
