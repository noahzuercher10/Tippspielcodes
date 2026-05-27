/**
 * ============================================================
 * Sportarten-Seite: Sportart -> Liga -> Tag waehlen + Spiele tippen
 * ------------------------------------------------------------
 * Ablauf im UI:
 *  1. Sportart-Dropdown ist immer sichtbar (gefuellt aus api/sports.php)
 *  2. Sobald eine Sportart gewaehlt ist -> Liga-Dropdown sichtbar
 *  3. Sobald eine Liga gewaehlt ist -> Datum + Gruppen-Dropdown + Refresh
 *  4. Spiele werden gerendert (Punktemodus oder Geldmodus je nach Setting)
 * ============================================================
 */
(async () => {
  // ---- DOM-Referenzen ----
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

  // ---- 1) Sportarten laden + Dropdown fuellen ----
  let sports = [];
  try {
    sports = await Tippspiel.get('/Tippspiel/api/sports.php');
  } catch (e) {
    // API-Fehler sichtbar machen (z.B. 401 Not authenticated)
    console.error('sports.php Fehler:', e);
    Tippspiel.toast('API-Fehler beim Laden der Sportarten: ' + e.message, 'error');
    return;
  }
  if (!Array.isArray(sports) || sports.length === 0) {
    Tippspiel.toast('Keine Sportarten in der DB. Bitte SQL neu importieren.', 'error');
    return;
  }
  sports.forEach(s => sportSel.add(new Option(s.name, s.id)));

  // ---- 2) Eigene Gruppen laden + Gruppen-Dropdown fuellen (je nach Modus) ----
  const myGroups = await Tippspiel.get('/Tippspiel/api/groups.php');
  function fillGroups() {
    const mode = Tippspiel.getMode();
    groupSel.innerHTML = '<option value="">Ohne Gruppe</option>';
    // nur Gruppen anzeigen, die dem aktuellen Modus entsprechen
    myGroups.filter(g => g.mode === mode).forEach(g =>
      groupSel.add(new Option(g.name, g.id)));
  }
  fillGroups();

  // ---- 3) User-Daten + Guthaben-Anzeige im Geldmodus ----
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

  // Wenn der User oben den Modus wechselt: alles neu laden
  document.addEventListener('mode-changed', () => {
    fillGroups(); refreshBalance(); loadMatches();
  });

  // ---- Event-Listener ----
  sportSel .addEventListener('change', onSportChange);
  leagueSel.addEventListener('change', onLeagueChange);
  daySel   .addEventListener('change', () => loadMatches());
  groupSel .addEventListener('change', () => loadMatches());
  if (refreshBtn) refreshBtn.addEventListener('click', () => loadMatches(true));

  /** Sportart wurde gewechselt -> Liga-Dropdown neu fuellen. */
  async function onSportChange() {
    leagueWrap.style.display = 'none';
    dayWrap.style.display    = 'none';
    groupWrap.style.display  = 'none';
    if (refreshBtn) refreshBtn.style.display = 'none';
    leagueSel.innerHTML = '<option value="">-- Liga / Turnier wählen --</option>';
    matchesEl.innerHTML = '';
    progressBar.style.width = '0%'; progressLabel.textContent = '';
    if (!sportSel.value) return;
    const ls = await Tippspiel.get('/Tippspiel/api/leagues.php?sport_id=' + sportSel.value);
    if (!ls.length) {
      Tippspiel.toast('Für diese Sportart sind noch keine Ligen vorhanden.', 'info');
      return;
    }
    ls.forEach(l => leagueSel.add(new Option(l.season ? `${l.name} ${l.season}` : l.name, l.id)));
    leagueWrap.style.display = 'inline-flex';
  }

  /** Liga wurde gewechselt -> Datum + Gruppe + Refresh-Button anzeigen, Spiele laden. */
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

  /**
   * Laedt die Spiele fuer Liga + Datum vom Server.
   * @param {boolean} force - bei true wird ?force=1 angehaengt, was den
   *   30-Min-Cache umgeht und live von der API neu zieht.
   */
  async function loadMatches(force = false) {
    if (!leagueSel.value) { matchesEl.innerHTML = ''; return; }
    if (force) {
      matchesEl.innerHTML = '<p style="color:var(--muted)">Lade frisch von der Sport-API...</p>';
      Tippspiel.toast('Synchronisiere...', 'info');
    }
    const params = new URLSearchParams({
      league_id: leagueSel.value,
      date:      daySel.value,
      mode:      Tippspiel.getMode(),
    });
    if (groupSel.value) params.set('group_id', groupSel.value);
    if (force) params.set('force', '1');
    try {
      const data = await Tippspiel.get('/Tippspiel/api/matches.php?' + params);
      renderMatches(data);
      if (force) Tippspiel.toast('Sync fertig.', 'ok');
    } catch (e) {
      Tippspiel.toast('Fehler: ' + e.message, 'error');
    }
  }

  /**
   * Rendert die Spiele in #matches.
   * Bei leerer Liste wird ein Hinweis + grosser "Aktualisieren"-Button
   * + naechste-5-Spiele-Liste angezeigt.
   */
  function renderMatches({ matches, total_users, hint, next_matches }) {
    // --- Leerer Tag ---
    if (!matches.length) {
      let html = '<div style="text-align:center;padding:30px 0">';
      html += `<p style="color:var(--muted);font-size:16px">${hint || 'Keine Spiele an diesem Tag.'}</p>`;
      // Grosser blauer Refresh-Button mittig
      html += '<button id="empty-refresh" class="btn primary" style="margin:10px auto 18px;font-size:15px;padding:10px 24px">';
      html += 'Jetzt frisch von der Sport-API laden';
      html += '</button>';
      // Liste der naechsten 5 Spiele (kommt vom Server)
      if (Array.isArray(next_matches) && next_matches.length) {
        html += '<div style="margin-top:18px;text-align:left;max-width:640px;margin-left:auto;margin-right:auto">';
        html += '<h4 style="color:var(--muted);font-size:13px;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">Nächste Spiele dieser Liga</h4>';
        html += '<ul style="color:var(--muted);font-size:14px;line-height:1.9;padding-left:18px;list-style:none">';
        for (const n of next_matches) {
          const dt = new Date(n.match_datetime).toLocaleString('de-CH');
          html += `<li>📅 <b>${dt}</b> — ${n.home_name} vs. ${n.away_name}</li>`;
        }
        html += '</ul></div>';
      }
      html += '</div>';
      matchesEl.innerHTML = html;
      // Click-Handler fuer den dynamisch erstellten Button
      const btn = document.getElementById('empty-refresh');
      if (btn) btn.addEventListener('click', () => loadMatches(true));
      progressBar.style.width = '0%'; progressLabel.textContent = '';
      return;
    }

    // --- Spiele rendern + Tipp-Fortschritt anzeigen ---
    const totalTips = matches.reduce((a, m) => a + Number(m.tip_count || 0), 0);
    const max = matches.length * (total_users || 1);
    const pct = max ? Math.round(totalTips / max * 100) : 0;
    progressBar.style.width = pct + '%';
    progressLabel.textContent = `Bereits ${totalTips} Tipps abgegeben (${pct} %)`;

    const mode = Tippspiel.getMode();
    matchesEl.innerHTML = matches.map(m => {
      const start  = new Date(m.match_datetime);
      // Spiele die schon angepfiffen oder beendet sind -> Inputs disabled
      const closed = m.status !== 'upcoming' || start.getTime() <= Date.now();
      const result = (m.home_score !== null && m.away_score !== null)
        ? `Resultat: ${m.home_score} : ${m.away_score}` : '';

      if (mode === 'points') {
        // ----- Punktemodus: zwei Number-Inputs fuer Heim/Auswaerts -----
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
        // ----- Geldmodus: Sieger-Dropdown + Einsatz-Input -----
        const tw    = m.my_bet ? m.my_bet.tip_winner : '';
        const stake = m.my_bet ? Number(m.my_bet.stake).toFixed(2) : '';
        const sel = (v) => tw === v ? 'selected' : '';
        return `
          <div class="match money" data-id="${m.id}">
            <div class="tname">${m.home_name}</div>
            <div class="winner-row">
              <select class="winner" ${closed?'disabled':''}>
                <option value="">-- Sieger wählen --</option>
                <option value="home" ${sel('home')}>Heimsieg (${m.home_short || m.home_name})</option>
                <option value="draw" ${sel('draw')}>Unentschieden</option>
                <option value="away" ${sel('away')}>Auswärtssieg (${m.away_short || m.away_name})</option>
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

    // "Tipp speichern"-Buttons verdrahten
    matchesEl.querySelectorAll('.save').forEach(btn => {
      btn.addEventListener('click', () => saveTip(btn.closest('.match')));
    });
  }

  /**
   * Speichert einen Tipp via POST /api/bets.php.
   * Holt die Werte aus dem .match-Div, validiert sie clientseitig
   * (Doppel-Pruefung passiert serverseitig auch).
   */
  async function saveTip(row) {
    const mode = Tippspiel.getMode();
    let body;
    if (mode === 'points') {
      const tipH = Number(row.querySelector('.tipH').value);
      const tipA = Number(row.querySelector('.tipA').value);
      if (Number.isNaN(tipH) || Number.isNaN(tipA) || tipH<0 || tipA<0) {
        Tippspiel.toast('Bitte Heim- und Auswärtstore eingeben', 'error'); return;
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
      // Im Geldmodus wurde Guthaben abgezogen -> neu laden + anzeigen
      if (mode === 'money') {
        const me2 = await Tippspiel.get('/Tippspiel/api/me.php');
        Object.assign(me, me2); refreshBalance();
      }
    } catch (e) { Tippspiel.toast(e.message, 'error'); }
  }
})();
