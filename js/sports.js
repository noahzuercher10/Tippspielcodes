/**
 * sports.js – Tippen-Seite Controller
 *
 * Verantwortlich für:
 *   - Dropdown-Kaskade: Sportart → Liga → Tag → Gruppe
 *   - Laden und Rendern aller Spielkarten via api/matches.php
 *   - Tipp-Eingabe und Speichern via api/bets.php
 *   - Standard-Matches (Fussball, Eishockey, Basketball)
 *   - Tennis-Spezialansicht mit Satz-Picker (Best of 3 / Best of 5)
 *   - Formel-1-Rennkarten: Top-10-Fahrer (Punkte) / Podium (Geld)
 *   - Fortschrittsbalken: Anzahl abgegebener Tipps in der Liga
 *   - Saisonübersicht-Modus (alle Spiele einer Liga auf einmal)
 *
 * Abhängigkeiten: app.js (Tippspiel-Namespace) muss vorher geladen sein.
 */
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
  let seasonMode = true;   // Standard: alle Spiele der Saison anzeigen
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

  const myGroups = await Tippspiel.get('/Tippspiel/api/groups.php');
  /**
   * Befüllt das Gruppen-Dropdown gefiltert nach aktivem Modus UND
   * gewählter Liga. Nur Gruppen die beides matchen erscheinen.
   * Stellt die vorherige Auswahl wieder her wenn noch gültig.
   */
  function fillGroups() {
    const mode     = Tippspiel.getMode();
    const leagueId = leagueSel.value;
    const prev     = groupSel.value;
    groupSel.innerHTML = '<option value="">Ohne Gruppe</option>';
    myGroups
      .filter(g => g.mode === mode && (!leagueId || String(g.league_id) === leagueId))
      .forEach(g => groupSel.add(new Option(g.name, g.id)));
    // Vorherige Auswahl beibehalten wenn noch vorhanden
    if (prev && [...groupSel.options].some(o => o.value === prev)) groupSel.value = prev;
  }
  fillGroups();

  const me = await Tippspiel.get('/Tippspiel/api/me.php');
  /** Zeigt/versteckt die Guthaben-Anzeige je nach aktivem Modus. */
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
  daySel.addEventListener('change', () => {
    const today = new Date().toISOString().slice(0, 10);
    loadMatches(daySel.value >= today);
  });
  groupSel.addEventListener('change', () => loadMatches());
  if (refreshBtn) refreshBtn.addEventListener('click', () => loadMatches(true));
  if (seasonBtn) {
    seasonBtn.textContent = 'Tagesansicht'; // Start im Saison-Modus
    seasonBtn.addEventListener('click', () => {
      seasonMode = !seasonMode;
      seasonBtn.textContent = seasonMode ? 'Tagesansicht' : 'Saisonübersicht';
      loadMatches();
    });
  }

  /** Reagiert auf Sportart-Wechsel: lädt Liga-Dropdown, blendet Rest aus. */
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
    if (!ls.length) { Tippspiel.toast('Keine Ligen für diese Sportart.', 'info'); return; }
    ls.forEach(l => leagueSel.add(new Option(l.season ? `${l.name} ${l.season}` : l.name, l.id)));
    leagueWrap.style.display = 'inline-flex';
  }

  /** Reagiert auf Liga-Wechsel: aktualisiert Gruppen-Filter und lädt Spiele. */
  function onLeagueChange() {
    matchesEl.innerHTML = '';
    progressBar.style.width = '0%'; progressLabel.textContent = '';
    fillGroups(); // Gruppen nach gewählter Liga filtern
    if (!leagueSel.value) {
      dayWrap.style.display = 'none'; groupWrap.style.display = 'none';
      if (refreshBtn) refreshBtn.style.display = 'none';
      return;
    }
    dayWrap.style.display   = 'inline-flex';
    groupWrap.style.display = 'inline-flex';
    if (refreshBtn) refreshBtn.style.display = 'inline-block';
    if (seasonBtn) {
      seasonBtn.style.display = 'inline-block';
      seasonMode = true;                        // bei neuer Liga immer Saisonansicht
      seasonBtn.textContent = 'Tagesansicht';
    }
    loadMatches();
  }

  /**
   * Holt Spieldaten von api/matches.php und übergibt sie an renderMatches().
   * @param {boolean} force  true = Cache umgehen, direkt von API laden
   */
  async function loadMatches(force = false) {
    if (!leagueSel.value) { matchesEl.innerHTML = ''; return; }
    if (force) matchesEl.innerHTML = '<p style="color:var(--muted);padding:16px 0">Lade von der API…</p>';
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

  /**
   * Gibt ein <img>-Tag zurück wenn eine Badge-URL vorhanden ist,
   * sonst einen Platzhalter-Span mit dem Kurznamen.
   * @param {string|null} badgeUrl  URL zum Teambild/Spielerfoto
   * @param {string} shortName      Kürzel (z.B. 'FCB', 'VER')
   * @param {boolean} isSingle      true bei Einzelsport (Tennis, F1) → player-face CSS
   */
  function teamImg(badgeUrl, shortName, isSingle = false) {
    if (badgeUrl) {
      const cls = isSingle ? 'player-face' : 'team-badge';
      return `<img class="${cls}" src="${badgeUrl}" alt="${shortName}" onerror="this.style.display='none'">`;
    }
    return `<span class="team-badge-placeholder">${shortName || '?'}</span>`;
  }

  // ----------------------------------------------------------------
  // renderMatches – Haupt-Einstiegspunkt nach API-Antwort
  // ----------------------------------------------------------------
  /**
   * Rendert alle Spielkarten in matchesEl. Entscheidet anhand von
   * sport_class ob F1-, Tennis- oder Standard-Karten gezeichnet werden.
   * Aktualisiert auch den Fortschrittsbalken.
   * @param {Object} data          Antwort von api/matches.php
   * @param {boolean} isSeason     true = Saisonübersicht (alle Spiele)
   */
  function renderMatches({ matches, total_users, hint, next_matches, sport_class, league_name }, isSeason = false) {
    currentSportClass = sport_class || '';
    currentLeagueName = league_name || '';

    if (!matches || !matches.length) {
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

    // Dedup by id (safety net)
    const seen = new Set();
    const unique = matches.filter(m => { if (seen.has(m.id)) return false; seen.add(m.id); return true; });

    const totalTips = unique.reduce((a, m) => a + Number(m.tip_count || 0), 0);
    // Detect F1 by sport_class OR by match data (away_short='F1' / home_short=P1..P10)
    const isF1 = currentSportClass === 'Formula1Sport'
        || (unique.length > 0 && (unique[0].away_short === 'F1' || /^P\d+$/.test(unique[0].home_short || '')));
    // For F1, count unique races (not sub-positions) for progress
    const denominator = isF1
      ? ([...new Set(unique.map(m => m.away_name))].length * (total_users || 1))
      : (unique.length * (total_users || 1));
    const pct = denominator ? Math.round(totalTips / denominator * 100) : 0;
    progressBar.style.width = pct + '%';
    progressLabel.textContent = `${totalTips} Tipps abgegeben (${pct}%)`;

    const selectedSport = sports.find(s => s.id == sportSel.value);
    const isSingle = selectedSport && selectedSport.type === 'single';
    const now = Date.now();

    let html = '';
    if (isSeason) {
      if (isF1) {
        html = renderF1All(unique, now);
      } else {
        const byDate = {};
        unique.forEach(m => {
          const d = m.match_datetime.slice(0, 10);
          (byDate[d] = byDate[d] || []).push(m);
        });
        for (const [d, ms] of Object.entries(byDate)) {
          const label = new Date(d).toLocaleDateString('de-CH', {weekday:'short', day:'numeric', month:'short'});
          html += `<div style="margin:14px 0 4px;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;font-weight:600">${label}</div>`;
          html += ms.map(m => renderMatchRow(m, isSingle, now)).join('');
        }
        html = html || '<p style="color:var(--muted);padding:16px 0">Keine Spiele vorhanden.</p>';
      }
    } else {
      html = isF1
        ? renderF1All(unique, now)
        : unique.map(m => renderMatchRow(m, isSingle, now)).join('');
    }
    matchesEl.innerHTML = html;

    // Save buttons (standard matches)
    matchesEl.querySelectorAll('.save:not(.f1-save)').forEach(btn =>
      btn.addEventListener('click', () => saveTip(btn.closest('.match')))
    );
    // Save buttons (F1 race cards)
    matchesEl.querySelectorAll('.f1-save').forEach(btn =>
      btn.addEventListener('click', () => saveF1Race(btn.closest('.f1-race-card')))
    );
    // F1: Fahrer nur einmal wählbar – deaktiviert bereits gewählte Optionen in anderen Slots
    matchesEl.querySelectorAll('.f1-race-card').forEach(card => {
      const selects = card.querySelectorAll('select.driver-pick');
      selects.forEach(sel => sel.addEventListener('change', () => syncF1DriverSelects(card)));
      syncF1DriverSelects(card); // initialer Sync für vorausgefüllte Werte
    });
    // Tennis set picker
    matchesEl.querySelectorAll('.set-result-sel').forEach(sel => {
      sel.addEventListener('change', () => updateSetInputs(sel));
      if (sel.value) updateSetInputs(sel);
    });
  }

  // ----------------------------------------------------------------
  // F1: alle Rennen als gruppierte Karten rendern
  // ----------------------------------------------------------------
  /**
   * Gruppiert F1-Sub-Matches (P1–P10) nach Rennen (away_name)
   * und rendert pro Rennen eine renderF1RaceCard().
   * @param {Array} matches  Alle F1-Matches der Liga
   * @param {number} now     Aktueller Timestamp (ms) für "gesperrt"-Check
   */
  function renderF1All(matches, now) {
    const mode = Tippspiel.getMode();
    const numSlots = mode === 'money' ? 3 : 10;

    // Group by race name (away_name), sort by datetime
    const races = {};
    matches.forEach(m => {
      const race = m.away_name || m.home_name.replace(/\s*–.*$/, '');
      if (!races[race]) races[race] = { datetime: m.match_datetime, positions: {} };
      races[race].positions[m.home_short] = m;
    });

    // Sort races by datetime
    const sorted = Object.entries(races).sort((a, b) =>
      new Date(a[1].datetime) - new Date(b[1].datetime)
    );

    return sorted.map(([raceName, { datetime, positions }]) =>
      renderF1RaceCard(raceName, positions, numSlots, now)
    ).join('') || '<p style="color:var(--muted);padding:16px 0">Keine F1-Daten vorhanden. Klicke auf "Aktualisieren".</p>';
  }

  /**
   * Rendert eine F1-Rennkarte mit Positions-Slots (P1–P10 oder P1–P3).
   * Geschlossene Rennen (Status finished / Zeit abgelaufen) zeigen das
   * Ergebnis mit Fahrerfoto; offene zeigen Fahrer-Dropdowns.
   * @param {string} raceName   Name des Rennens (z.B. "Monaco Grand Prix")
   * @param {Object} positions  Map { 'P1': matchObj, 'P2': matchObj, … }
   * @param {number} numSlots   10 (Punkte) oder 3 (Geld)
   * @param {number} now        Aktueller Timestamp in ms
   */
  function renderF1RaceCard(raceName, positions, numSlots, now) {
    const mode  = Tippspiel.getMode();
    // Reference match for datetime/status
    const ref   = positions['P1'] || Object.values(positions)[0];
    if (!ref) return '';
    const start  = new Date(ref.match_datetime);
    const closed = ref.status !== 'upcoming' || start.getTime() <= now;
    const timeStr = start.toLocaleDateString('de-CH', {day:'2-digit',month:'2-digit',year:'numeric'})
                  + ' ' + start.toLocaleTimeString('de-CH', {hour:'2-digit',minute:'2-digit'});

    // hasBet muss VOR footerHtml definiert sein
    const hasBet = Object.values(positions).some(p => p?.my_bet);

    // Gesamteinsatz: P1-Stake × 3 (da jede Position stake/3 bekommt)
    let existingStake = '';
    if (mode === 'money') {
      const p1Stake = Number(positions['P1']?.my_bet?.stake ?? 0);
      if (p1Stake > 0) existingStake = (p1Stake * numSlots).toFixed(2);
    }

    // Build position slots (P1…P{numSlots})
    let slotsHtml = '';
    const allFinished = Object.values(positions).some(m => m.status === 'finished' || m.home_score !== null);

    for (let i = 1; i <= numSlots; i++) {
      const posKey = 'P' + i;
      const m = positions[posKey];
      if (!m && closed) {
        slotsHtml += `<div class="f1-slot"><span class="f1-pos">${posKey}</span><span style="color:var(--muted);font-size:13px">–</span></div>`;
        continue;
      }

      // Fahrer-Ergebnis aus home_name: "Monaco GP – 1. Platz (Max Verstappen)"
      const resultMatch = m?.home_name?.match(/\(([^)]+)\)\s*$/);
      const resultDriver = resultMatch ? resultMatch[1] : null;

      // Fahrerfoto (gespeichert in home_badge nach Rennergebnis)
      const driverPhoto = m?.home_badge
        ? `<img class="f1-driver-face" src="${m.home_badge}" alt="${resultDriver || ''}" onerror="this.style.display='none'">`
        : '';

      // Gespeicherter Tipp
      let existingPick = '';
      if (m?.my_bet?.extra_data) {
        try { existingPick = JSON.parse(m.my_bet.extra_data)?.driver ?? ''; } catch {}
      }

      if (closed || !m) {
        const val = resultDriver || (m?.status === 'finished' ? '–' : 'Offen');
        const tipMatch = existingPick && resultDriver
          ? (existingPick.toLowerCase() === resultDriver.toLowerCase() ? ' style="color:var(--accent)"' : ' style="color:var(--danger)"')
          : '';
        slotsHtml += `<div class="f1-slot">
          <span class="f1-pos">${posKey}</span>
          ${driverPhoto}
          <span style="font-size:13px;${resultDriver ? 'font-weight:600' : 'color:var(--muted)'}"${tipMatch}>${val}</span>
          ${existingPick && !resultDriver ? `<span style="font-size:11px;color:var(--muted);margin-left:4px">✓ ${existingPick}</span>` : ''}
        </div>`;
      } else {
        const matchId = m ? m.id : '';
        const opts = F1_DRIVERS.map(d =>
          `<option value="${d}" ${d === existingPick ? 'selected' : ''}>${d}</option>`
        ).join('');
        slotsHtml += `<div class="f1-slot" data-match-id="${matchId}" data-pos="${posKey}">
          <span class="f1-pos">${posKey}</span>
          <select class="driver-pick">
            <option value="">– Fahrer –</option>
            ${opts}
          </select>
        </div>`;
      }
    }

    // Footer: stake (money) + save button
    let footerHtml = '';
    if (!closed) {
      const savedCls = hasBet ? ' saved' : '';
      if (mode === 'money') {
        const stakeLabel = numSlots === 3
          ? `Gesamteinsatz CHF (aufgeteilt auf P1–P3)`
          : `Einsatz CHF`;
        footerHtml = `
          <input type="number" step="1" min="30" max="1500" class="stake f1-stake"
                 placeholder="${stakeLabel}" value="${existingStake}">
          <button class="btn primary small f1-save${savedCls}">Speichern</button>`;
      } else {
        footerHtml = `<button class="btn primary small f1-save${savedCls}">Speichern</button>`;
      }
    }

    return `<div class="f1-race-card${hasBet ? ' tipped' : ''}">
      <div class="f1-race-header">
        <span class="f1-race-name">${raceName}</span>
        <span class="meta">${timeStr}</span>
        ${closed && !allFinished ? '<span class="badge">Läuft</span>' : ''}
      </div>
      <div class="f1-slots">${slotsHtml}</div>
      ${footerHtml ? `<div class="f1-footer">${footerHtml}</div>` : ''}
    </div>`;
  }

  // ----------------------------------------------------------------
  // renderMatchRow – Standard-Spielkarte (Fussball / Eishockey / Basketball)
  // ----------------------------------------------------------------
  /**
   * Gibt das HTML einer einzelnen Spielkarte zurück.
   * Punkte-Modus: zwei Zahleneingaben (Heim/Auswärts).
   * Geld-Modus: Sieger-Select + Einsatz-Input.
   * @param {Object}  m         Match-Objekt aus api/matches.php
   * @param {boolean} isSingle  true bei Einzelsport (andere Badge-CSS)
   * @param {number}  now       Aktueller Timestamp in ms
   */
  function renderMatchRow(m, isSingle, now) {
    const mode      = Tippspiel.getMode();
    const isTennis  = currentSportClass === 'TennisSport';
    const isBestOf5 = isTennis && /australian open|roland garros|wimbledon|us open/i.test(currentLeagueName);

    if (isTennis && mode === 'points') return renderTennisRow(m, isBestOf5, now);

    const start     = new Date(m.match_datetime);
    const closed    = m.status !== 'upcoming' || start.getTime() <= now;
    const hasResult = m.home_score !== null && m.away_score !== null;
    const result    = hasResult ? `${m.home_score} : ${m.away_score}` : null;
    const homeImg   = teamImg(m.home_badge, m.home_short || m.home_name.slice(0,3), isSingle);
    const awayImg   = teamImg(m.away_badge, m.away_short || m.away_name.slice(0,3), isSingle);
    const timeStr   = start.toLocaleTimeString('de-CH', {hour:'2-digit', minute:'2-digit'});

    // --- Vergangenes / laufendes Spiel: Read-only Ergebniskarte ---
    if (closed) {
      let myTipText = null;
      let tipClass  = 'tip-pending';
      if (m.my_bet) {
        if (mode === 'points') {
          myTipText = `${m.my_bet.tip_home} : ${m.my_bet.tip_away}`;
          if (hasResult) {
            tipClass = (Number(m.my_bet.tip_home) === Number(m.home_score) &&
                        Number(m.my_bet.tip_away) === Number(m.away_score))
              ? 'tip-correct' : 'tip-wrong';
          }
        } else {
          myTipText = m.my_bet.tip_winner === 'home' ? 'Heimsieg'
                    : m.my_bet.tip_winner === 'away' ? 'Auswärtssieg' : 'Unentschieden';
          if (hasResult) {
            const actual = Number(m.home_score) > Number(m.away_score) ? 'home'
                         : Number(m.home_score) < Number(m.away_score) ? 'away' : 'draw';
            tipClass = m.my_bet.tip_winner === actual ? 'tip-correct' : 'tip-wrong';
          }
        }
      }
      const running = !hasResult && m.status !== 'finished';
      return `
        <div class="match past${m.my_bet ? ' tipped' : ''}" data-id="${m.id}">
          <div class="match-team">${homeImg}<span class="tname">${m.home_name}</span></div>
          <div class="match-result">
            ${result ? `<span class="result-score">${result}</span>` : `<span class="badge">${running ? 'Läuft' : 'Beendet'}</span>`}
            ${myTipText ? `<span class="my-tip ${tipClass}">Tipp: ${myTipText}</span>` : ''}
          </div>
          <div class="match-team away"><span class="tname">${m.away_name}</span>${awayImg}</div>
          <div class="meta">${timeStr}</div>
        </div>`;
    }

    // --- Offenes Spiel: Tipp-Eingabe ---
    if (mode === 'points') {
      const tipH = m.my_bet ? m.my_bet.tip_home : '';
      const tipA = m.my_bet ? m.my_bet.tip_away : '';
      return `
        <div class="match${m.my_bet ? ' tipped' : ''}" data-id="${m.id}">
          <div class="match-team">${homeImg}<span class="tname">${m.home_name}</span></div>
          <input type="number" min="0" class="tipH" value="${tipH ?? ''}">
          <input type="number" min="0" class="tipA" value="${tipA ?? ''}">
          <div class="match-team away"><span class="tname">${m.away_name}</span>${awayImg}</div>
          <div><div class="meta">${timeStr}</div><button class="btn save small${m.my_bet ? ' saved' : ''}">Speichern</button></div>
        </div>`;
    } else {
      const tw    = m.my_bet ? m.my_bet.tip_winner : '';
      const stake = m.my_bet ? Number(m.my_bet.stake).toFixed(2) : '';
      const sel   = v => tw === v ? 'selected' : '';
      return `
        <div class="match money${m.my_bet ? ' tipped' : ''}" data-id="${m.id}">
          <div class="match-team">${homeImg}<span class="tname">${m.home_name}</span></div>
          <div class="winner-row">
            <select class="winner">
              <option value="">– Sieger –</option>
              <option value="home" ${sel('home')}>Heimsieg</option>
              <option value="draw" ${sel('draw')}>Unentschieden</option>
              <option value="away" ${sel('away')}>Auswärtssieg</option>
            </select>
            <input type="number" step="0.01" min="10" max="500"
                   class="stake" placeholder="Einsatz" value="${stake}">
          </div>
          <div class="match-team away"><span class="tname">${m.away_name}</span>${awayImg}</div>
          <div><div class="meta">${timeStr}</div><button class="btn save small${m.my_bet ? ' saved' : ''}">Speichern</button></div>
        </div>`;
    }
  }

  // ----------------------------------------------------------------
  // Tennis-Spielkarte mit Satz-Picker
  // ----------------------------------------------------------------
  /**
   * Rendert eine Tennis-Spielkarte mit Satzverhältnis-Dropdown
   * und dynamischen Satz-Score-Inputs.
   * @param {Object}  m          Match-Objekt
   * @param {boolean} isBestOf5  true bei Grand Slams (3:0..3:2), false = Best of 3
   * @param {number}  now        Aktueller Timestamp in ms
   */
  function renderTennisRow(m, isBestOf5, now) {
    const start     = new Date(m.match_datetime);
    const closed    = m.status !== 'upcoming' || start.getTime() <= now;
    const hasResult = m.home_score !== null && m.away_score !== null;
    const result    = hasResult ? `${m.home_score} : ${m.away_score}` : null;
    const timeStr   = start.toLocaleTimeString('de-CH', {hour:'2-digit', minute:'2-digit'});
    const homeImg   = teamImg(m.home_badge, m.home_short || m.home_name.slice(0,3), true);
    const awayImg   = teamImg(m.away_badge, m.away_short || m.away_name.slice(0,3), true);

    // --- Vergangenes / laufendes Spiel: Read-only Ergebniskarte ---
    if (closed) {
      let myTipText = null;
      let tipClass  = 'tip-pending';
      if (m.my_bet?.extra_data) {
        try {
          const ed   = JSON.parse(m.my_bet.extra_data);
          const sets = ed.sets || [];
          if (sets.length) {
            let tipH = 0, tipA = 0;
            sets.forEach(s => { if (s[0] > s[1]) tipH++; else if (s[1] > s[0]) tipA++; });
            myTipText = `${tipH}:${tipA}`;
            if (hasResult) {
              tipClass = (tipH === Number(m.home_score) && tipA === Number(m.away_score))
                ? 'tip-correct' : 'tip-wrong';
            }
          }
        } catch {}
      }
      const running = !hasResult && m.status !== 'finished';
      return `
        <div class="match past${m.my_bet ? ' tipped' : ''}" data-id="${m.id}">
          <div class="match-team">${homeImg}<span class="tname">${m.home_name}</span></div>
          <div class="match-result">
            ${result ? `<span class="result-score">${result}</span>` : `<span class="badge">${running ? 'Läuft' : 'Beendet'}</span>`}
            ${myTipText ? `<span class="my-tip ${tipClass}">Tipp: ${myTipText}</span>` : ''}
          </div>
          <div class="match-team away"><span class="tname">${m.away_name}</span>${awayImg}</div>
          <div class="meta">${timeStr}</div>
        </div>`;
    }

    // --- Offenes Spiel: Satz-Picker ---
    let setOptions;
    if (isBestOf5) {
      setOptions = ['3:0','3:1','3:2','2:3','1:3','0:3'].map(v => `<option value="${v}">${v}</option>`).join('');
    } else {
      setOptions = ['2:0','2:1','1:2','0:2'].map(v => `<option value="${v}">${v}</option>`).join('');
    }

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

    const setInputsHtml = buildSetInputsHtml(existingSetResult, existingSets, false);
    const optsHtml = setOptions.replace(`value="${existingSetResult}"`, `value="${existingSetResult}" selected`);
    const pickEl = `<div class="tennis-pick">
        <select class="set-result-sel" data-bo5="${isBestOf5 ? '1' : '0'}">
          <option value="">– Satzverhältnis –</option>
          ${optsHtml}
        </select>
        <div class="set-inputs">${setInputsHtml}</div>
      </div>`;

    return `
      <div class="match tennis-pts${m.my_bet ? ' tipped' : ''}" data-id="${m.id}">
        <div class="match-team">${homeImg}<span class="tname">${m.home_name}</span></div>
        <div>${pickEl}</div>
        <div class="match-team away"><span class="tname">${m.away_name}</span>${awayImg}</div>
        <div><div class="meta">${timeStr}</div><button class="btn save small${m.my_bet ? ' saved' : ''}">Speichern</button></div>
      </div>`;
  }

  /**
   * Baut die Satz-Score-Inputs für Tennis (ein Input-Paar pro Satz).
   * @param {string}   setResult    z.B. "2:1" – bestimmt Anzahl der Sätze
   * @param {Array}    existingSets Vorhandene Satz-Werte [[sh,sa], …]
   * @param {boolean}  disabled     true wenn Spiel gesperrt/beendet
   */
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

  /** Aktualisiert die Satz-Inputs wenn der User ein neues Satzverhältnis wählt. */
  function updateSetInputs(sel) {
    const container = sel.closest('.tennis-pick').querySelector('.set-inputs');
    container.innerHTML = buildSetInputsHtml(sel.value, [], false);
  }

  // ----------------------------------------------------------------
  // saveTip – Standard-Tipp speichern (Fussball / Basketball / Tennis)
  // ----------------------------------------------------------------
  /**
   * Liest die Eingaben aus einem Match-Row-Element und sendet den
   * Tipp an api/bets.php. Aktualisiert anschliessend die Anzeige.
   * @param {HTMLElement} row  .match-Element der Spielkarte
   */
  async function saveTip(row) {
    const mode     = Tippspiel.getMode();
    const matchId  = Number(row.dataset.id);
    const groupId  = groupSel.value || null;
    const isTennis = currentSportClass === 'TennisSport';
    let body;

    if (mode === 'points' && isTennis) {
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
          Tippspiel.toast(`Satz ${i+1}: Gültige Scores eingeben.`, 'error'); return;
        }
        sets.push([sh, sa]);
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

  // ----------------------------------------------------------------
  // syncF1DriverSelects – deaktiviert bereits gewählte Fahrer in
  // anderen Slots derselben Rennkarte (jeder Fahrer nur 1x wählbar)
  // ----------------------------------------------------------------
  function syncF1DriverSelects(card) {
    const selects = [...card.querySelectorAll('select.driver-pick')];
    const picked  = new Set(selects.map(s => s.value).filter(v => v !== ''));
    selects.forEach(sel => {
      const own = sel.value;
      [...sel.options].forEach(opt => {
        if (!opt.value) return; // leere Option immer erlaubt
        opt.disabled = picked.has(opt.value) && opt.value !== own;
      });
    });
  }

  // ----------------------------------------------------------------
  // saveF1Race – F1-Renntipp speichern (alle Slots parallel)
  // ----------------------------------------------------------------
  /**
   * Liest alle Fahrer-Selects einer F1-Rennkarte aus und schickt
   * pro Position einen Tipp an api/bets.php (Promise.all = parallel).
   * Im Geldmodus wird der Gesamteinsatz gleichmässig auf alle Slots aufgeteilt.
   * @param {HTMLElement} card  .f1-race-card-Element
   */
  async function saveF1Race(card) {
    const mode    = Tippspiel.getMode();
    const groupId = groupSel.value || null;
    const slots   = [...card.querySelectorAll('.f1-slot[data-match-id]')];

    if (!slots.length) { Tippspiel.toast('Keine Positionen gefunden.', 'error'); return; }

    // Validate all picks
    const picks = [];
    for (const slot of slots) {
      const driver = slot.querySelector('.driver-pick')?.value ?? '';
      if (!driver) {
        Tippspiel.toast(`${slot.dataset.pos}: Bitte Fahrer wählen.`, 'error'); return;
      }
      picks.push({ matchId: Number(slot.dataset.matchId), driver, pos: slot.dataset.pos });
    }

    // Stake für Geldmodus: Gesamteinsatz wird gleichmäßig auf alle Slots verteilt
    let stakePerSlot = 0;
    if (mode === 'money') {
      const totalStake = Number(card.querySelector('.f1-stake')?.value || 0);
      const minTotal = picks.length * 10;
      const maxTotal = picks.length * 500;
      if (!totalStake || totalStake < minTotal || totalStake > maxTotal) {
        Tippspiel.toast(`Gesamteinsatz zwischen ${minTotal} und ${maxTotal} CHF (${picks.length} × 10–500).`, 'error'); return;
      }
      stakePerSlot = parseFloat((totalStake / picks.length).toFixed(2));
    }

    // Alle Tipps parallel senden
    try {
      const requests = picks.map(({ matchId, driver }) => {
        const body = {
          match_id: matchId, mode,
          tip_home: 0, tip_away: 0,
          extra_data: { driver },
          group_id: groupId,
          stake: stakePerSlot,
        };
        return Tippspiel.post('/Tippspiel/api/bets.php', body);
      });
      await Promise.all(requests);
      Tippspiel.toast('F1-Tipps gespeichert!', 'ok');
      if (mode === 'money') {
        const me2 = await Tippspiel.get('/Tippspiel/api/me.php');
        Object.assign(me, me2); refreshBalance();
      }
      loadMatches();
    } catch (e) { Tippspiel.toast(e.message, 'error'); }
  }
})();
