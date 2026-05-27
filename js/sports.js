(async () => {
  const sportSel   = document.getElementById('sport');
  const leagueSel  = document.getElementById('league');
  const daySel     = document.getElementById('day');
  const groupSel   = document.getElementById('group');
  const leagueWrap = document.getElementById('league-wrap');
  const dayWrap    = document.getElementById('day-wrap');
  const groupWrap  = document.getElementById('group-wrap');
  const balanceEl  = document.getElementById('balance');
  const matchesEl  = document.getElementById('matches');
  const refreshBtn = document.getElementById('refresh-btn');
  const progressBar   = document.getElementById('tip-progress-bar');
  const progressLabel = document.getElementById('tip-progress-label');

  // Sportarten laden
  let sports = [];
  try {
    sports = await Tippspiel.get('/Tippspiel/api/sports.php');
  } catch (e) {
    Tippspiel.toast('API-Fehler: ' + e.message, 'error');
    return;
  }
  if (!Array.isArray(sports) || !sports.length) {
    matchesEl.innerHTML = '<p style="color:var(--muted)">Keine Sportarten. Bitte SQL neu importieren.</p>';
    return;
  }
  sports.forEach(s => sportSel.add(new Option(s.name, s.id)));

  // Gruppen laden
  const myGroups = await Tippspiel.get('/Tippspiel/api/groups.php');
  function fillGroups() {
    const mode = Tippspiel.getMode();
    groupSel.innerHTML = '<option value="">Ohne Gruppe</option>';
    myGroups.filter(g => g.mode === mode).forEach(g =>
      groupSel.add(new Option(g.name, g.id)));
  }
  fillGroups();

  // User-Daten
  const me = await Tippspiel.get('/Tippspiel/api/me.php');
  function refreshBalance() {
    if (Tippspiel.getMode() === 'money') {
      balanceEl.style.display = 'inline-block';
      balanceEl.textContent = `${me.money_balance.toFixed(2)} | max ${me.max_stake.toFixed(2)}`;
    } else {
      balanceEl.style.display = 'none';
    }
  }
  refreshBalance();

  document.addEventListener('mode-changed', () => { fillGroups(); refreshBalance(); loadMatches(); });
  sportSel.addEventListener('change', onSportChange);
  leagueSel.addEventListener('change', onLeagueChange);
  daySel.addEventListener('change', () => loadMatches());
  groupSel.addEventListener('change', () => loadMatches());
  if (refreshBtn) refreshBtn.addEventListener('click', () => loadMatches(true));

  async function onSportChange() {
    leagueWrap.style.display = 'none';
    dayWrap.style.display    = 'none';
    groupWrap.style.display  = 'none';
    if (refreshBtn) refreshBtn.style.display = 'none';
    leagueSel.innerHTML = '<option value="">– wählen –</option>';
    matchesEl.innerHTML = '';
    progressBar.style.width = '0%'; progressLabel.textContent = '';
    if (!sportSel.value) return;
    const ls = await Tippspiel.get('/Tippspiel/api/leagues.php?sport_id=' + sportSel.value);
    if (!ls.length) {
      Tippspiel.toast('Keine Ligen für diese Sportart.', 'info');
      return;
    }
    ls.forEach(l => leagueSel.add(new Option(l.season ? `${l.name} ${l.season}` : l.name, l.id)));
    leagueWrap.style.display = 'inline-flex';
  }

  function onLeagueChange() {
    matchesEl.innerHTML = '';
    progressBar.style.width = '0%'; progressLabel.textContent = '';
    if (!leagueSel.value) {
      dayWrap.style.display = 'none'; groupWrap.style.display = 'none';
      if (refreshBtn) refreshBtn.style.display = 'none';
      return;
    }
    dayWrap.style.display   = 'inline-flex';
    groupWrap.style.display = 'inline-flex';
    if (refreshBtn) refreshBtn.style.display = 'inline-block';
    loadMatches();
  }

  async function loadMatches(force = false) {
    if (!leagueSel.value) { matchesEl.innerHTML = ''; return; }
    if (force) {
      matchesEl.innerHTML = '<p style="color:var(--muted);padding:16px 0">Lade von der API...</p>';
    }
    const params = new URLSearchParams({
      league_id: leagueSel.value,
      date: daySel.value,
      mode: Tippspiel.getMode(),
    });
    if (groupSel.value) params.set('group_id', groupSel.value);
    if (force) params.set('force', '1');
    try {
      const data = await Tippspiel.get('/Tippspiel/api/matches.php?' + params);
      renderMatches(data);
    } catch (e) {
      Tippspiel.toast('Fehler: ' + e.message, 'error');
    }
  }

  // Hilfsfunktion: Wappen-/Gesicht-HTML erzeugen
  function teamImg(badgeUrl, shortName, isSingle = false) {
    if (badgeUrl) {
      const cls = isSingle ? 'player-face' : 'team-badge';
      return `<img class="${cls}" src="${badgeUrl}" alt="${shortName}" onerror="this.style.display='none'">`;
    }
    return `<span class="team-badge-placeholder">${shortName || '?'}</span>`;
  }

  function renderMatches({ matches, total_users, hint, next_matches }) {
    if (!matches.length) {
      let html = '<div style="text-align:center;padding:28px 0">';
      html += `<p style="color:var(--muted)">${hint || 'Keine Spiele an diesem Tag.'}</p>`;
      html += '<button id="empty-refresh" class="btn primary" style="margin:8px auto">Von API laden</button>';
      if (Array.isArray(next_matches) && next_matches.length) {
        html += '<div style="margin-top:16px;text-align:left;max-width:600px;margin-left:auto;margin-right:auto">';
        html += '<p style="color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px">Nächste Spiele</p>';
        html += '<ul style="list-style:none;padding:0;margin:0">';
        for (const n of next_matches) {
          const dt = new Date(n.match_datetime).toLocaleString('de-CH');
          html += `<li style="padding:5px 0;border-bottom:1px solid var(--border);font-size:14px">
            <span style="color:var(--muted)">${dt}</span> &nbsp;
            <strong>${n.home_name}</strong> – <strong>${n.away_name}</strong>
          </li>`;
        }
        html += '</ul></div>';
      }
      html += '</div>';
      matchesEl.innerHTML = html;
      document.getElementById('empty-refresh')?.addEventListener('click', () => loadMatches(true));
      progressBar.style.width = '0%'; progressLabel.textContent = '';
      return;
    }

    const totalTips = matches.reduce((a, m) => a + Number(m.tip_count || 0), 0);
    const max = matches.length * (total_users || 1);
    const pct = max ? Math.round(totalTips / max * 100) : 0;
    progressBar.style.width = pct + '%';
    progressLabel.textContent = `${totalTips} Tipps abgegeben (${pct}%)`;

    // Prüfen ob es sich um Einzelsport handelt (Tennis / F1)
    const selectedSport = sports.find(s => s.id == sportSel.value);
    const isSingle = selectedSport && selectedSport.type === 'single';

    const mode = Tippspiel.getMode();
    matchesEl.innerHTML = matches.map(m => {
      const start  = new Date(m.match_datetime);
      const closed = m.status !== 'upcoming' || start.getTime() <= Date.now();
      const result = (m.home_score !== null && m.away_score !== null)
        ? `${m.home_score} : ${m.away_score}` : '';

      const homeImg = teamImg(m.home_badge, m.home_short || m.home_name.slice(0,3), isSingle);
      const awayImg = teamImg(m.away_badge, m.away_short || m.away_name.slice(0,3), isSingle);

      const statusBadge = closed
        ? `<span class="badge">${m.status === 'finished' ? 'Beendet' : 'Gestartet'}</span>`
        : `<button class="btn save small">Speichern</button>`;

      if (mode === 'points') {
        const tipH = m.my_bet ? m.my_bet.tip_home : '';
        const tipA = m.my_bet ? m.my_bet.tip_away : '';
        return `
          <div class="match" data-id="${m.id}">
            <div class="match-team">
              ${homeImg}
              <span class="tname">${m.home_name}</span>
            </div>
            <input type="number" min="0" class="tipH" value="${tipH ?? ''}" ${closed ? 'disabled' : ''}>
            <input type="number" min="0" class="tipA" value="${tipA ?? ''}" ${closed ? 'disabled' : ''}>
            <div class="match-team away">
              <span class="tname">${m.away_name}</span>
              ${awayImg}
            </div>
            <div>
              <div class="meta">${start.toLocaleTimeString('de-CH', {hour:'2-digit',minute:'2-digit'})} ${result ? '· '+result : ''}</div>
              ${statusBadge}
            </div>
          </div>`;
      } else {
        const tw    = m.my_bet ? m.my_bet.tip_winner : '';
        const stake = m.my_bet ? Number(m.my_bet.stake).toFixed(2) : '';
        const sel   = v => tw === v ? 'selected' : '';
        return `
          <div class="match money" data-id="${m.id}">
            <div class="match-team">
              ${homeImg}
              <span class="tname">${m.home_name}</span>
            </div>
            <div class="winner-row">
              <select class="winner" ${closed ? 'disabled' : ''}>
                <option value="">– Sieger –</option>
                <option value="home" ${sel('home')}>Heimsieg</option>
                <option value="draw" ${sel('draw')}>Unentschieden</option>
                <option value="away" ${sel('away')}>Auswärtssieg</option>
              </select>
              <input type="number" step="0.01" min="10" max="500"
                     class="stake" placeholder="Einsatz" value="${stake}" ${closed ? 'disabled' : ''}>
            </div>
            <div class="match-team away">
              <span class="tname">${m.away_name}</span>
              ${awayImg}
            </div>
            <div>
              <div class="meta">${start.toLocaleTimeString('de-CH', {hour:'2-digit',minute:'2-digit'})} ${result ? '· '+result : ''}</div>
              ${statusBadge}
            </div>
          </div>`;
      }
    }).join('');

    matchesEl.querySelectorAll('.save').forEach(btn =>
      btn.addEventListener('click', () => saveTip(btn.closest('.match')))
    );
  }

  async function saveTip(row) {
    const mode = Tippspiel.getMode();
    let body;
    if (mode === 'points') {
      const tipH = Number(row.querySelector('.tipH').value);
      const tipA = Number(row.querySelector('.tipA').value);
      if (Number.isNaN(tipH) || Number.isNaN(tipA) || tipH < 0 || tipA < 0) {
        Tippspiel.toast('Bitte Heim- und Auswärtstore eingeben.', 'error'); return;
      }
      body = { match_id: Number(row.dataset.id), mode, tip_home: tipH, tip_away: tipA,
               group_id: groupSel.value || null };
    } else {
      const tw    = row.querySelector('.winner').value;
      const stake = Number(row.querySelector('.stake').value);
      if (!['home','draw','away'].includes(tw)) {
        Tippspiel.toast('Bitte Sieger wählen.', 'error'); return;
      }
      if (!stake || stake < 10 || stake > 500) {
        Tippspiel.toast('Einsatz zwischen 10 und 500.', 'error'); return;
      }
      body = { match_id: Number(row.dataset.id), mode, tip_winner: tw, stake,
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
