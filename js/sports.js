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
  const seasonBtn  = document.getElementById('season-btn');
  const progressBar   = document.getElementById('tip-progress-bar');
  const progressLabel = document.getElementById('tip-progress-label');
  let seasonMode = false;
  let currentSportClass = '';
  let currentLeagueName = '';

  const F1_DRIVERS = [
    'Alexander Albon', 'Ayumu Iwasa', 'Carlos Sainz', 'Charles Leclerc',
    'Esteban Ocon', 'Fernando Alonso', 'Felipe Drugovich', 'Franco Colapinto',
    'Frederik Vesti', 'Gabriel Bortoleto', 'George Russell', 'Isack Hadjar',
    'Jack Crawford', 'Jack Doohan', 'Jak Crawford', 'Kimi Antonelli',
    'Lance Stroll', 'Lando Norris', 'Lewis Hamilton', 'Liam Lawson',
    'Max Verstappen', 'Mick Schumacher', 'Nick Yelloly', 'Nico Hülkenberg',
    'Nyck de Vries', 'Oliver Bearman', 'Oscar Piastri', 'Patricio OWard',
    'Paul Aron', 'Pietro Fittipaldi', 'Pierre Gasly', 'Robert Shwartzman',
    'Ryo Hirakawa', 'Theo Pourchaire', 'Valtteri Bottas', 'Yuki Tsunoda',
  ].sort();

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
  if (seasonBtn) seasonBtn.addEventListener('click', () => {
    seasonMode = !seasonMode;
    seasonBtn.textContent = seasonMode ? 'Tagesansicht' : 'Saisonübersicht';
    loadMatches();
  });

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
    if (seasonBtn)  seasonBtn.style.display  = 'inline-block';
    seasonMode = false;
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
    if (seasonMode) params.set('all', '1');
    try {
      const data = await Tippspiel.get('/Tippspiel/api/matches.php?' + params);
      renderMatches(data, seasonMode);
    } catch (e) {
      Tippspiel.toast('Fehler: ' + e.message, 'error');
    }
  }

  function teamImg(badgeUrl, shortName, isSingle = false) {
    if (badgeUrl) {
      const cls = isSingle ? 'player-face' : 'team-badge';
      return `<img class="${cls}" src="${badgeUrl}" alt="${shortName}" onerror="this.style.display='none'">`;
    }
    return `<span class="team-badge-placeholder">${shortName || '?'}</span>`;
  }

  function renderMatches({ matches, total_users, hint, next_matches, sport_class, league_name }, isSeason = false) {
    currentSportClass = sport_class || '';
    currentLeagueName = league_name || '';

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

    const selectedSport = sports.find(s => s.id == sportSel.value);
    const isSingle = selectedSport && selectedSport.type === 'single';
    const now = Date.now();

    if (isSeason) {
      const byDate = {};
      matches.forEach(m => {
        const d = m.match_datetime.slice(0, 10);
        (byDate[d] = byDate[d] || []).push(m);
      });
      let html = '';
      for (const [d, ms] of Object.entries(byDate)) {
        const label = new Date(d).toLocaleDateString('de-CH', {weekday:'short', day:'numeric', month:'short'});
        html += `<div style="margin:14px 0 4px;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;font-weight:600">${label}</div>`;
        html += ms.map(m => renderMatchRow(m, isSingle, now)).join('');
      }
      matchesEl.innerHTML = html || '<p style="color:var(--muted);padding:16px 0">Keine Spiele vorhanden.</p>';
    } else {
      matchesEl.innerHTML = matches.map(m => renderMatchRow(m, isSingle, now)).join('');
    }

    // Save buttons
    matchesEl.querySelectorAll('.save').forEach(btn =>
      btn.addEventListener('click', () => saveTip(btn.closest('.match')))
    );

    // Tennis set picker: dynamic inputs when set result changes
    matchesEl.querySelectorAll('.set-result-sel').forEach(sel => {
      sel.addEventListener('change', () => updateSetInputs(sel));
      // Pre-fill from existing bet
      if (sel.value) updateSetInputs(sel);
    });
  }

  // ----------------------------------------------------------------
  // renderMatchRow – dispatches to sport-specific renderers
  // ----------------------------------------------------------------
  function renderMatchRow(m, isSingle, now) {
    const mode    = Tippspiel.getMode();
    const isF1     = currentSportClass === 'Formula1Sport';
    const isTennis = currentSportClass === 'TennisSport';
    const isBestOf5 = isTennis && /australian open|roland garros|wimbledon|us open/i.test(currentLeagueName);

    if (isF1 && mode === 'points') return renderF1Row(m, now);
    if (isTennis && mode === 'points') return renderTennisRow(m, isBestOf5, now);

    // ----- default (team sports + money mode) -----
    const start  = new Date(m.match_datetime);
    const closed = m.status !== 'upcoming' || start.getTime() <= now;
    const result = (m.home_score !== null && m.away_score !== null)
      ? `${m.home_score} : ${m.away_score}` : '';
    const homeImg = teamImg(m.home_badge, m.home_short || m.home_name.slice(0,3), isSingle);
    const awayImg = teamImg(m.away_badge, m.away_short || m.away_name.slice(0,3), isSingle);
    const timeStr = start.toLocaleTimeString('de-CH', {hour:'2-digit', minute:'2-digit'});

    let statusEl;
    if (m.status === 'finished' || (start.getTime() <= now && m.status !== 'upcoming')) {
      statusEl = result
        ? `<span class="result-score">${result}</span>`
        : `<span class="badge">Beendet</span>`;
    } else if (closed) {
      statusEl = `<span class="badge">Läuft</span>`;
    } else {
      statusEl = `<button class="btn save small">Speichern</button>`;
    }

    if (mode === 'points') {
      const tipH = m.my_bet ? m.my_bet.tip_home : '';
      const tipA = m.my_bet ? m.my_bet.tip_away : '';
      return `
        <div class="match" data-id="${m.id}">
          <div class="match-team">${homeImg}<span class="tname">${m.home_name}</span></div>
          <input type="number" min="0" class="tipH" value="${tipH ?? ''}" ${closed ? 'disabled' : ''}>
          <input type="number" min="0" class="tipA" value="${tipA ?? ''}" ${closed ? 'disabled' : ''}>
          <div class="match-team away"><span class="tname">${m.away_name}</span>${awayImg}</div>
          <div><div class="meta">${timeStr}</div>${statusEl}</div>
        </div>`;
    } else {
      const tw    = m.my_bet ? m.my_bet.tip_winner : '';
      const stake = m.my_bet ? Number(m.my_bet.stake).toFixed(2) : '';
      const sel   = v => tw === v ? 'selected' : '';
      return `
        <div class="match money" data-id="${m.id}">
          <div class="match-team">${homeImg}<span class="tname">${m.home_name}</span></div>
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
          <div class="match-team away"><span class="tname">${m.away_name}</span>${awayImg}</div>
          <div><div class="meta">${timeStr}</div>${statusEl}</div>
        </div>`;
    }
  }

  // ----------------------------------------------------------------
  // F1 row: driver dropdown per position
  // ----------------------------------------------------------------
  function renderF1Row(m, now) {
    const start  = new Date(m.match_datetime);
    const closed = m.status !== 'upcoming' || start.getTime() <= now;
    const timeStr = start.toLocaleTimeString('de-CH', {hour:'2-digit', minute:'2-digit'});

    const posLabels = { P1: 'Sieger', P2: '2. Platz', P3: '3. Platz' };
    const posLabel = posLabels[m.home_short] || m.home_short;

    // Extract race name (away_name holds the plain race name)
    const raceName = m.away_name || m.home_name.replace(/\s*–.*$/, '');

    // Result: driver in parentheses from home_name when finished
    const resultMatch = m.home_name.match(/\(([^)]+)\)\s*$/);
    const resultDriver = resultMatch ? resultMatch[1] : null;

    // Existing bet driver
    let currentDriver = '';
    if (m.my_bet?.extra_data) {
      try { currentDriver = JSON.parse(m.my_bet.extra_data)?.driver ?? ''; } catch {}
    }

    let actionEl;
    if (closed) {
      actionEl = resultDriver
        ? `<span class="result-score">${resultDriver}</span>`
        : `<span class="badge">${m.status === 'finished' ? 'Kein Ergebnis' : 'Läuft'}</span>`;
    } else {
      const opts = F1_DRIVERS.map(d =>
        `<option value="${d}" ${d === currentDriver ? 'selected' : ''}>${d}</option>`
      ).join('');
      actionEl = `
        <select class="driver-pick">
          <option value="">– Fahrer wählen –</option>
          ${opts}
        </select>
        <button class="btn save small" style="margin-top:4px">Speichern</button>`;
    }

    return `
      <div class="match f1" data-id="${m.id}">
        <div>
          <span class="f1-pos">${m.home_short}</span>
          <span style="font-size:13px;font-weight:600;margin-left:6px">${posLabel}</span>
          <div style="font-size:12px;color:var(--muted);margin-top:2px">${raceName}</div>
        </div>
        <div style="display:flex;flex-direction:column;gap:4px">${actionEl}</div>
        <div style="text-align:right"><div class="meta">${timeStr}</div>
          ${currentDriver && !closed ? `<div style="font-size:11px;color:var(--muted)">Gespeichert: ${currentDriver}</div>` : ''}
        </div>
      </div>`;
  }

  // ----------------------------------------------------------------
  // Tennis row: set score picker
  // ----------------------------------------------------------------
  function renderTennisRow(m, isBestOf5, now) {
    const start  = new Date(m.match_datetime);
    const closed = m.status !== 'upcoming' || start.getTime() <= now;
    const result = (m.home_score !== null && m.away_score !== null)
      ? `${m.home_score} : ${m.away_score}` : '';
    const timeStr = start.toLocaleTimeString('de-CH', {hour:'2-digit', minute:'2-digit'});
    const homeImg = teamImg(m.home_badge, m.home_short || m.home_name.slice(0,3), true);
    const awayImg = teamImg(m.away_badge, m.away_short || m.away_name.slice(0,3), true);

    let statusEl;
    if (m.status === 'finished' || (start.getTime() <= now && m.status !== 'upcoming')) {
      statusEl = result
        ? `<span class="result-score">${result}</span>`
        : `<span class="badge">Beendet</span>`;
    } else if (closed) {
      statusEl = `<span class="badge">Läuft</span>`;
    } else {
      statusEl = `<button class="btn save small">Speichern</button>`;
    }

    // Build set result options
    let setOptions;
    if (isBestOf5) {
      setOptions = ['3:0','3:1','3:2','2:3','1:3','0:3'].map(v =>
        `<option value="${v}">${v}</option>`).join('');
    } else {
      setOptions = ['2:0','2:1','1:2','0:2'].map(v =>
        `<option value="${v}">${v}</option>`).join('');
    }

    // Existing bet
    let existingSetResult = '';
    let existingSets = [];
    if (m.my_bet?.extra_data) {
      try {
        const ed = JSON.parse(m.my_bet.extra_data);
        existingSets = ed.sets || [];
        if (existingSets.length > 0) {
          let tipH = 0, tipA = 0;
          existingSets.forEach(s => { if (s[0] > s[1]) tipH++; else if (s[1] > s[0]) tipA++; });
          existingSetResult = `${tipH}:${tipA}`;
        }
      } catch {}
    }

    // Build set inputs HTML (pre-populated if existing bet)
    const setInputsHtml = buildSetInputsHtml(existingSetResult, existingSets, closed);

    let pickEl;
    if (closed) {
      pickEl = result ? `<span class="result-score">${result}</span>` : `<span class="badge">Beendet</span>`;
    } else {
      pickEl = `
        <div class="tennis-pick">
          <select class="set-result-sel" data-bo5="${isBestOf5 ? '1' : '0'}" ${closed ? 'disabled' : ''}>
            <option value="">– Satzverhältnis –</option>
            ${setOptions.replace(`value="${existingSetResult}"`, `value="${existingSetResult}" selected`)}
          </select>
          <div class="set-inputs">${setInputsHtml}</div>
        </div>`;
    }

    return `
      <div class="match tennis-pts" data-id="${m.id}">
        <div class="match-team">${homeImg}<span class="tname">${m.home_name}</span></div>
        <div>${pickEl}</div>
        <div class="match-team away"><span class="tname">${m.away_name}</span>${awayImg}</div>
        <div><div class="meta">${timeStr}</div>${statusEl}</div>
      </div>`;
  }

  function buildSetInputsHtml(setResult, existingSets, disabled) {
    if (!setResult) return '';
    const [h, a] = setResult.split(':').map(Number);
    const total = h + a;
    if (!total) return '';
    let html = '';
    for (let i = 0; i < total; i++) {
      const sv = existingSets[i] ? existingSets[i][0] : '';
      const av = existingSets[i] ? existingSets[i][1] : '';
      html += `<div class="set-inputs-row">
        <span>Satz ${i+1}:</span>
        <input type="number" min="0" max="7" class="set-h" value="${sv}" ${disabled ? 'disabled' : ''}>
        <span>:</span>
        <input type="number" min="0" max="7" class="set-a" value="${av}" ${disabled ? 'disabled' : ''}>
      </div>`;
    }
    return html;
  }

  function updateSetInputs(sel) {
    const container = sel.closest('.tennis-pick').querySelector('.set-inputs');
    const setResult  = sel.value;
    container.innerHTML = buildSetInputsHtml(setResult, [], false);
  }

  // ----------------------------------------------------------------
  // saveTip
  // ----------------------------------------------------------------
  async function saveTip(row) {
    const mode     = Tippspiel.getMode();
    const matchId  = Number(row.dataset.id);
    const groupId  = groupSel.value || null;
    const isF1     = currentSportClass === 'Formula1Sport';
    const isTennis = currentSportClass === 'TennisSport';
    let body;

    if (mode === 'points' && isF1) {
      const driver = row.querySelector('.driver-pick')?.value ?? '';
      if (!driver) { Tippspiel.toast('Bitte einen Fahrer auswählen.', 'error'); return; }
      body = { match_id: matchId, mode, tip_home: 0, tip_away: 0,
               extra_data: { driver }, group_id: groupId };

    } else if (mode === 'points' && isTennis) {
      const setResultSel = row.querySelector('.set-result-sel');
      if (!setResultSel?.value) { Tippspiel.toast('Bitte Satzverhältnis wählen.', 'error'); return; }
      const [tipH, tipA] = setResultSel.value.split(':').map(Number);
      const setHInputs = row.querySelectorAll('.set-h');
      const setAInputs = row.querySelectorAll('.set-a');
      const sets = [];
      for (let i = 0; i < setHInputs.length; i++) {
        const sh = parseInt(setHInputs[i].value);
        const sa = parseInt(setAInputs[i].value);
        if (isNaN(sh) || isNaN(sa) || sh < 0 || sa < 0) {
          Tippspiel.toast(`Satz ${i+1}: Bitte gültige Scores eingeben.`, 'error'); return;
        }
        sets.push([sh, sa]);
      }
      if (sets.length !== tipH + tipA) {
        Tippspiel.toast('Anzahl der Sätze stimmt nicht.', 'error'); return;
      }
      body = { match_id: matchId, mode, tip_home: tipH, tip_away: tipA,
               extra_data: { sets }, group_id: groupId };

    } else if (mode === 'points') {
      const tipH = Number(row.querySelector('.tipH').value);
      const tipA = Number(row.querySelector('.tipA').value);
      if (Number.isNaN(tipH) || Number.isNaN(tipA) || tipH < 0 || tipA < 0) {
        Tippspiel.toast('Bitte Heim- und Auswärtstore eingeben.', 'error'); return;
      }
      body = { match_id: matchId, mode, tip_home: tipH, tip_away: tipA, group_id: groupId };

    } else {
      const tw    = row.querySelector('.winner').value;
      const stake = Number(row.querySelector('.stake').value);
      if (!['home','draw','away'].includes(tw)) {
        Tippspiel.toast('Bitte Sieger wählen.', 'error'); return;
      }
      if (!stake || stake < 10 || stake > 500) {
        Tippspiel.toast('Einsatz zwischen 10 und 500.', 'error'); return;
      }
      body = { match_id: matchId, mode, tip_winner: tw, stake, group_id: groupId };
    }

    try {
      await Tippspiel.post('/Tippspiel/api/bets.php', body);
      Tippspiel.toast('Tipp gespeichert!', 'ok');
      if (mode === 'money') {
        const me2 = await Tippspiel.get('/Tippspiel/api/me.php');
        Object.assign(me, me2); refreshBalance();
      }
      loadMatches();
    } catch (e) { Tippspiel.toast(e.message, 'error'); }
  }
})();
