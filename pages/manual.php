<?php
$active = 'manual';
require_once __DIR__ . '/../includes/header.php';
$isAdmin = $user['role'] === 'admin';
?>

<style>
.manual-nav {
  display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px;
}
.manual-nav a {
  padding: 6px 14px; background: var(--surface-2);
  border: 1px solid var(--border); border-radius: 20px;
  font-size: 13px; color: var(--muted); text-decoration: none;
  transition: background .15s, color .15s;
}
.manual-nav a:hover, .manual-nav a.active-sec {
  background: var(--primary); color: #fff; border-color: var(--primary);
}
.manual-section { scroll-margin-top: 80px; }
.manual-section h2 {
  font-size: 20px; margin: 0 0 12px;
  padding-bottom: 8px; border-bottom: 2px solid var(--primary);
  color: var(--text);
}
.manual-section h3 {
  font-size: 15px; margin: 18px 0 8px; color: var(--primary);
}
.manual-section p, .manual-section li {
  font-size: 14px; line-height: 1.7; color: var(--text);
}
.manual-section ul, .manual-section ol {
  padding-left: 20px; margin: 6px 0;
}
.manual-section li { margin-bottom: 4px; }
.step-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px; margin: 12px 0;
}
.step-card {
  background: var(--surface-2); border: 1px solid var(--border);
  border-radius: 10px; padding: 14px; position: relative;
}
.step-card .step-num {
  width: 28px; height: 28px; border-radius: 50%;
  background: var(--primary); color: #fff;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700; margin-bottom: 8px;
}
.step-card p { margin: 0; font-size: 13px; }
.score-table { max-width: 500px; }
.score-table td:last-child { font-weight: 700; text-align: right; }
.hl { background: rgba(37,99,235,.1); border-radius: 4px; padding: 1px 5px; font-size: 13px; color: var(--primary); }
.tip-box {
  background: rgba(5,150,105,.08); border: 1px solid rgba(5,150,105,.25);
  border-radius: 8px; padding: 10px 14px; margin: 10px 0; font-size: 13px;
}
.warn-box {
  background: rgba(217,119,6,.08); border: 1px solid rgba(217,119,6,.3);
  border-radius: 8px; padding: 10px 14px; margin: 10px 0; font-size: 13px; color: var(--warn);
}
.admin-badge {
  display: inline-block; padding: 2px 8px; background: var(--danger);
  color: #fff; border-radius: 4px; font-size: 11px; font-weight: 700;
  margin-left: 6px; vertical-align: middle;
}
</style>

<div class="card" style="margin-bottom:0">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
    <div>
      <h1 style="margin:0;font-size:22px">Benutzerhandbuch</h1>
      <p style="margin:4px 0 0;color:var(--muted);font-size:13px">Tippspiel – ZbW Projekt 2608</p>
    </div>
    <?php if ($isAdmin): ?>
      <span style="background:var(--danger);color:#fff;padding:4px 12px;border-radius:6px;font-size:12px;font-weight:700">Admin-Version</span>
    <?php endif; ?>
  </div>

  <nav class="manual-nav" id="manual-nav">
    <a href="#ueberblick">Überblick</a>
    <a href="#login">Login & Registrierung</a>
    <a href="#tippen">Tippen</a>
    <a href="#sportarten">Sportarten</a>
    <a href="#gruppen">Gruppen</a>
    <a href="#rangliste">Rangliste</a>
    <a href="#profil">Profil</a>
    <?php if ($isAdmin): ?>
      <a href="#admin">Admin-Panel</a>
    <?php endif; ?>
  </nav>
</div>

<!-- ================================================================ -->
<!-- ÜBERBLICK -->
<!-- ================================================================ -->
<section class="card manual-section" id="ueberblick">
  <h2>Überblick</h2>
  <p>Das Tippspiel erlaubt dir, Spielergebnisse aus verschiedenen Sportarten vorherzusagen und dabei Punkte oder virtuelles Geld zu verdienen. Du kannst alleine spielen oder in Gruppen gegen andere antreten.</p>

  <div class="step-grid" style="margin-top:14px">
    <div class="step-card">
      <div class="step-num">1</div>
      <p><strong>Sportart & Liga wählen</strong><br>Fussball, Eishockey, Basketball, Tennis, Formel 1, WM 2026</p>
    </div>
    <div class="step-card">
      <div class="step-num">2</div>
      <p><strong>Tipp abgeben</strong><br>Ergebnis, Sieger oder Fahrer/Platz vorhersagen – vor Spielbeginn</p>
    </div>
    <div class="step-card">
      <div class="step-num">3</div>
      <p><strong>Punkte sammeln</strong><br>Automatische Auswertung nach Spielende. Punkte oder Geld wird gutgeschrieben</p>
    </div>
    <div class="step-card">
      <div class="step-num">4</div>
      <p><strong>Rangliste</strong><br>Vergleiche dich mit anderen Spielern oder in deiner Gruppe</p>
    </div>
  </div>

  <h3>Navigation</h3>
  <ul>
    <li><span class="hl">Start</span> – Deine Punkte, dein Guthaben und das Punktesystem</li>
    <li><span class="hl">Tippen</span> – Spiele auswählen und Tipps abgeben</li>
    <li><span class="hl">Gruppen</span> – Gruppen erstellen, beitreten und Ranglisten anschauen</li>
    <li><span class="hl">Rangliste</span> – Globale Bestenliste aller Spieler</li>
    <li><span class="hl">Profil</span> – Profilbild ändern, Theme wählen, letzte Tipps anschauen</li>
    <?php if ($isAdmin): ?><li><span class="hl">Admin</span> – Verwaltung von Ligen, Ergebnissen und Benutzern</li><?php endif; ?>
  </ul>
</section>

<!-- ================================================================ -->
<!-- LOGIN & REGISTRIERUNG -->
<!-- ================================================================ -->
<section class="card manual-section" id="login">
  <h2>Login &amp; Registrierung</h2>

  <h3>Registrierung</h3>
  <ol>
    <li>Öffne die Startseite des Tippspiels</li>
    <li>Klicke auf <span class="hl">Registrieren</span></li>
    <li>Fülle alle Felder aus: Vorname, Nachname, Benutzername, E-Mail, Passwort</li>
    <li>Klicke auf <span class="hl">Konto erstellen</span></li>
  </ol>
  <div class="tip-box">Dein Startguthaben im Geldmodus beträgt <strong>2'500 CHF</strong>. Falls dein Guthaben unter 10 CHF fällt, kannst du nicht mehr tippen – bitte den Admin um eine Aufladung.</div>

  <h3>Login</h3>
  <p>Gib deinen <strong>Benutzernamen oder deine E-Mail</strong> sowie dein Passwort ein und klicke auf <span class="hl">Anmelden</span>.</p>

  <h3>Modus wählen</h3>
  <p>Oben rechts im Header findest du den <strong>Modus-Schalter</strong>:</p>
  <ul>
    <li><strong>Punkte</strong> – kostenlos, Punkte werden akkumuliert</li>
    <li><strong>Imaginäres Geld</strong> – du setzt virtuelles Geld ein und kannst es verdoppeln</li>
  </ul>
  <div class="warn-box">Der Modus beeinflusst, welche Gruppen im Dropdown erscheinen und wie dein Tipp bewertet wird. Stelle ihn VOR dem Tippen ein.</div>
</section>

<!-- ================================================================ -->
<!-- TIPPEN -->
<!-- ================================================================ -->
<section class="card manual-section" id="tippen">
  <h2>Tippen</h2>

  <h3>Schritt-für-Schritt</h3>
  <ol>
    <li>Navigiere zu <span class="hl">Tippen</span></li>
    <li>Wähle eine <strong>Sportart</strong> aus dem ersten Dropdown</li>
    <li>Wähle eine <strong>Liga / Turnier</strong></li>
    <li>Wähle ein <strong>Datum</strong> (Standard: heute)</li>
    <li>Optional: Wähle eine <strong>Gruppe</strong> (nur Gruppen der gewählten Liga erscheinen)</li>
    <li>Gib deinen Tipp ein und klicke <span class="hl">Speichern</span></li>
  </ol>

  <div class="tip-box"><strong>Tipp:</strong> Mit dem Button <span class="hl">Saisonübersicht</span> siehst du alle Spiele der Saison auf einen Blick – vergangene und zukünftige.</div>

  <h3>Punktemodus – Bewertung</h3>
  <table class="score-table">
    <tr><td>Exaktes Resultat (z.B. 2:1 richtig)</td><td style="color:var(--primary)">10 Pkt</td></tr>
    <tr><td>Richtiger Sieger oder Unentschieden</td><td>5 Pkt</td></tr>
    <tr><td>Richtige Heimtore</td><td>1 Pkt</td></tr>
    <tr><td>Richtige Auswärtststore</td><td>1 Pkt</td></tr>
    <tr><td>Richtige Tordifferenz (bei richtigem Sieger)</td><td>3 Pkt</td></tr>
  </table>

  <h3>Geldmodus – Bewertung</h3>
  <table class="score-table">
    <tr><td>Startkapital</td><td>2'500 CHF</td></tr>
    <tr><td>Einsatz pro Spiel</td><td>10 – 500 CHF</td></tr>
    <tr><td>Richtig getippt (Sieger/Unentschieden)</td><td style="color:var(--accent)">Einsatz × 2</td></tr>
    <tr><td>Falsch getippt</td><td style="color:var(--danger)">Einsatz verloren</td></tr>
  </table>

  <div class="warn-box">Ein Tipp kann nur abgegeben oder geändert werden, <strong>bis das Spiel beginnt</strong>. Danach ist der Tipp gesperrt.</div>

  <h3>Tipp aktualisieren</h3>
  <p>Hast du bereits getippt (grüner Rand beim Spiel), kannst du vor Spielbeginn jederzeit einen neuen Wert eingeben und erneut auf <span class="hl">Speichern</span> klicken. Der alte Tipp wird überschrieben.</p>
  <div class="warn-box">Im Geldmodus: Wenn du einen Tipp mit einem neuen Einsatz überschreibst, wird die Differenz verrechnet. Der ursprüngliche Einsatz wird zurückgegeben und der neue abgezogen.</div>
</section>

<!-- ================================================================ -->
<!-- SPORTARTEN -->
<!-- ================================================================ -->
<section class="card manual-section" id="sportarten">
  <h2>Sportarten</h2>

  <h3>Fussball / WM 2026</h3>
  <p>Standard-Tippmodus: Heimtore : Auswärtststore (z.B. <strong>2 : 1</strong>). Bewertung nach dem obigen Punkteschema. Im Geldmodus tippst du nur den <strong>Sieger</strong> (Heim / Unentschieden / Auswärts) und setzt einen Betrag.</p>

  <h3>Eishockey &amp; Basketball</h3>
  <p>Identisch mit Fussball – Tore (bzw. Punkte) für Heim und Auswärts eingeben.</p>

  <h3>Tennis</h3>
  <p>Im <strong>Punktemodus</strong> tippst du zuerst das <strong>Satzverhältnis</strong> (z.B. 2:1) und dann für jeden Satz das Score (z.B. 6:4 / 3:6 / 7:5). Die Bewertung erfolgt über die korrekte Anzahl gewonnener Sätze.</p>
  <ul>
    <li>Grand Slams (Roland Garros, Wimbledon, Australian Open, US Open): Best of 5</li>
    <li>ATP-Tour: Best of 3</li>
  </ul>

  <h3>Formel 1 – Punktemodus</h3>
  <p>Pro Rennen werden die <strong>Top-10-Fahrer (P1–P10)</strong> getippt. Für jeden Fahrer auf der exakt richtigen Position:</p>
  <table class="score-table">
    <tr><td>Fahrer auf exakter Position</td><td style="color:var(--primary)">2 Pkt</td></tr>
    <tr><td>Fahrer falsch oder nicht im Top 10</td><td>0 Pkt</td></tr>
    <tr><td>Maximum pro Rennen (10 × 2)</td><td style="color:var(--primary)">20 Pkt</td></tr>
  </table>
  <div class="tip-box">Jeder Fahrer kann innerhalb eines Rennens <strong>nur einmal</strong> ausgewählt werden – bereits gewählte Fahrer sind in anderen Dropdowns deaktiviert.</div>

  <h3>Formel 1 – Geldmodus (Podium)</h3>
  <p>Im Geldmodus tippst du nur die <strong>Top-3-Fahrer (Podium)</strong>. Der Gesamteinsatz wird gleichmässig auf P1, P2 und P3 aufgeteilt:</p>
  <table class="score-table">
    <tr><td>Fahrer auf genauer Position</td><td style="color:var(--accent)">Einsatz/3 × 2</td></tr>
    <tr><td>Fahrer im Podium, aber falsche Position</td><td>Einsatz/3 × 1 (nichts verloren)</td></tr>
    <tr><td>Fahrer nicht im Podium</td><td style="color:var(--danger)">Einsatz/3 verloren</td></tr>
  </table>
  <p style="font-size:13px;color:var(--muted)">Beispiel: Gesamteinsatz 300 CHF → je 100 CHF auf P1/P2/P3. Alle 3 exakt richtig = 600 CHF zurück.</p>
  <div class="tip-box">Minimaler Gesamteinsatz im Geldmodus: <strong>30 CHF</strong> (3 × 10 CHF). Maximum: <strong>1'500 CHF</strong> (3 × 500 CHF).</div>
</section>

<!-- ================================================================ -->
<!-- GRUPPEN -->
<!-- ================================================================ -->
<section class="card manual-section" id="gruppen">
  <h2>Gruppen</h2>
  <p>Gruppen ermöglichen den Wettbewerb mit Freunden. Jede Gruppe ist an eine <strong>Liga</strong> und einen <strong>Modus</strong> (Punkte oder Geld) gebunden.</p>

  <h3>Gruppe erstellen</h3>
  <ol>
    <li>Navigiere zu <span class="hl">Gruppen</span></li>
    <li>Klicke auf <span class="hl">Neue Gruppe</span></li>
    <li>Gib einen Namen ein, wähle Modus und Liga</li>
    <li>Klicke auf <span class="hl">Erstellen</span></li>
    <li>Du erhältst einen <strong>Beitrittscode</strong> – teile ihn mit Freunden</li>
  </ol>

  <h3>Gruppe beitreten</h3>
  <ol>
    <li>Navigiere zu <span class="hl">Gruppen</span></li>
    <li>Klicke auf <span class="hl">Gruppe beitreten</span></li>
    <li>Gib den <strong>Beitrittscode</strong> ein und bestätige</li>
  </ol>

  <h3>Tipp in einer Gruppe abgeben</h3>
  <ol>
    <li>Gehe zu <span class="hl">Tippen</span></li>
    <li>Wähle die Liga der Gruppe aus (nur dann erscheint die Gruppe im Dropdown)</li>
    <li>Stelle den richtigen <strong>Modus</strong> ein (Punkte oder Geld)</li>
    <li>Wähle deine Gruppe im <strong>Gruppen-Dropdown</strong> aus</li>
    <li>Gib den Tipp ab – Punkte/Geld werden der Gruppe und dir persönlich gutgeschrieben</li>
  </ol>

  <div class="tip-box">Gruppen-Punkte sind unabhängig von deinen Gesamt-Punkten. Du kannst auf dasselbe Spiel einmal mit Gruppe und einmal ohne Gruppe tippen.</div>
  <div class="warn-box">Eine Gruppe zeigt nur Tipps der <strong>eigenen Liga</strong>. Ein La-Liga-Gruppe zeigt nur La-Liga-Punkte.</div>

  <h3>Gruppen-Rangliste</h3>
  <p>Klicke auf eine Gruppe um die <strong>Mitglieder-Rangliste</strong> zu sehen. Sie zeigt den Score jedes Mitglieds basierend auf dem Gruppen-Modus (Punkte oder Kontostand).</p>
</section>

<!-- ================================================================ -->
<!-- RANGLISTE -->
<!-- ================================================================ -->
<section class="card manual-section" id="rangliste">
  <h2>Rangliste</h2>
  <p>Die globale Rangliste unter <span class="hl">Rangliste</span> zeigt alle Spieler sortiert nach ihren <strong>Gesamtpunkten</strong> oder ihrem <strong>Guthaben</strong> (je nach aktivem Modus).</p>
  <ul>
    <li>Im <strong>Punktemodus</strong>: Sortierung nach kumulierten Gesamtpunkten</li>
    <li>Im <strong>Geldmodus</strong>: Sortierung nach aktuellem Guthaben</li>
  </ul>
</section>

<!-- ================================================================ -->
<!-- PROFIL -->
<!-- ================================================================ -->
<section class="card manual-section" id="profil">
  <h2>Profil</h2>

  <h3>Profilbild ändern</h3>
  <ol>
    <li>Klicke oben links auf deinen <strong>Avatar / Benutzernamen</strong></li>
    <li>Klicke auf <span class="hl">Profilbild ändern</span></li>
    <li>Wähle ein Bild aus (JPG, PNG, max. empfohlen: 2 MB)</li>
    <li>Das Bild wird sofort gespeichert und angezeigt</li>
  </ol>

  <h3>Theme wechseln</h3>
  <p>Im Abschnitt <strong>Darstellung</strong> kannst du zwischen <strong>Light Mode</strong> und <strong>Dark Mode</strong> umschalten. Die Einstellung wird gespeichert.</p>

  <h3>Letzte Tipps</h3>
  <p>Auf der Profilseite siehst du deine letzten 25 Tipps mit Datum, Spiel, deinem Tipp, dem tatsächlichen Ergebnis und dem erzielten Ertrag.</p>
  <ul>
    <li>Bei <strong>F1-Tipps</strong> wird der getippte Fahrername und der tatsächliche Fahrer angezeigt</li>
    <li>Bei <strong>Punkte-Tipps</strong>: Ertrag in Punkten</li>
    <li>Bei <strong>Geld-Tipps</strong>: Ertrag in CHF (0.00 wenn noch nicht ausgewertet)</li>
  </ul>
</section>

<?php if ($isAdmin): ?>
<!-- ================================================================ -->
<!-- ADMIN-PANEL -->
<!-- ================================================================ -->
<section class="card manual-section" id="admin">
  <h2>Admin-Panel <span class="admin-badge">NUR ADMIN</span></h2>
  <p>Das Admin-Panel ist über den Menüpunkt <span class="hl">Admin</span> erreichbar. Nur Benutzer mit der Rolle <strong>admin</strong> haben Zugriff.</p>

  <h3>Statistiken</h3>
  <p>Oben im Admin-Panel findest du eine Übersicht der aktuellen Datenbank-Zahlen: Benutzer, Spiele, Tipps, Gruppen.</p>

  <h3>Sportart hinzufügen</h3>
  <ol>
    <li>Fülle die Felder <strong>Name</strong>, <strong>Typ</strong> (Team oder Einzel) und <strong>API-Klasse</strong> aus</li>
    <li>Klicke auf <span class="hl">Sportart hinzufügen</span></li>
  </ol>
  <p>API-Klassen: <code>FootballSport</code>, <code>IceHockeySport</code>, <code>BasketballSport</code>, <code>TennisSport</code>, <code>Formula1Sport</code>, <code>WorldCupSport</code></p>

  <h3>Liga hinzufügen</h3>
  <ol>
    <li>Wähle die Sportart aus dem Dropdown</li>
    <li>Gib <strong>Name</strong>, <strong>Saison</strong> (z.B. <code>2025-2026</code>) und <strong>API-ID</strong> (TheSportsDB-Liga-ID) ein</li>
    <li>Klicke auf <span class="hl">Liga hinzufügen</span></li>
  </ol>
  <p style="font-size:13px;color:var(--muted)">Tennis-Ligen verwenden das Präfix <code>search:</code> z.B. <code>search:Roland Garros</code>. F1 nutzt <code>4370</code>.</p>

  <h3>Liga synchronisieren (API-Sync)</h3>
  <p>Spiele werden automatisch aus externen APIs geladen. Du kannst die Synchronisierung manuell erzwingen:</p>
  <ul>
    <li><span class="hl">Liga synchronisieren</span> – Holt neue Spiele für eine einzelne Liga (umgeht den 1-Stunden-Cache)</li>
    <li><span class="hl">Alle synchronisieren</span> – Synchronisiert alle Ligen gleichzeitig (kann mehrere Sekunden dauern)</li>
    <li><span class="hl">Duplikate bereinigen</span> – Entfernt doppelte Einträge die durch mehrfache Syncs entstehen können</li>
  </ul>
  <div class="tip-box">Nach einem frischen Datenbankimport: <strong>«Alle synchronisieren»</strong> klicken um Spielpläne zu laden. Logos und Bilder werden bei der ersten Synchronisierung heruntergeladen.</div>

  <h3>Spielergebnis eintragen</h3>
  <p>Wenn ein Ergebnis nicht automatisch von der API übernommen wird, kannst du es manuell eintragen:</p>
  <ol>
    <li>Suche das Spiel in der <strong>Spielliste</strong> (letzte 200 Spiele)</li>
    <li>Trage <strong>Heim-Score</strong> und <strong>Auswärts-Score</strong> ein</li>
    <li>Klicke auf <span class="hl">Auswerten</span></li>
    <li>Alle Tipps für dieses Spiel werden sofort automatisch bewertet</li>
  </ol>
  <div class="warn-box">Ein bereits ausgewertetes Spiel kann nicht erneut ausgewertet werden (evaluated-Flag ist gesetzt). Wird das Ergebnis nachträglich korrigiert, müssen betroffene Bets manuell in der DB angepasst werden.</div>

  <h3>Benutzerverwaltung</h3>
  <table style="max-width:600px">
    <thead><tr><th>Aktion</th><th>Beschreibung</th></tr></thead>
    <tbody>
      <tr><td><span class="hl">Geld geben</span></td><td>Fügt dem Benutzer einen Betrag zum Guthaben hinzu – wirkt sich auch auf alle Gruppen-Mitgliedschaften aus</td></tr>
      <tr><td><span class="hl">Löschen</span></td><td>Löscht den Benutzer inklusive aller Tipps und Gruppen-Einträge (irreversibel)</td></tr>
    </tbody>
  </table>

  <h3>API-Quellen im Überblick</h3>
  <table>
    <thead><tr><th>Sport</th><th>Primäre API</th><th>Ergänzende APIs</th></tr></thead>
    <tbody>
      <tr><td>Fussball</td><td>TheSportsDB</td><td>OpenLigaDB (BL, CL), football-data.org (optional, Key nötig)</td></tr>
      <tr><td>WM 2026</td><td>TheSportsDB (ID 4453)</td><td>football-data.org WC (optional)</td></tr>
      <tr><td>Eishockey</td><td>TheSportsDB</td><td>NHL Official API (kostenlos)</td></tr>
      <tr><td>Basketball</td><td>TheSportsDB</td><td>balldontlie.io (optional, Key nötig)</td></tr>
      <tr><td>Tennis</td><td>TheSportsDB (eventsday)</td><td>–</td></tr>
      <tr><td>Formel 1</td><td>Ergast/Jolpica API</td><td>–</td></tr>
    </tbody>
  </table>

  <h3>API-Keys konfigurieren</h3>
  <p>Optionale API-Keys werden in <code>config/api_keys.php</code> eingetragen:</p>
  <ul>
    <li><code>FOOTBALL_DATA_ORG_KEY</code> – für football-data.org (Premier League, La Liga, Serie A etc.)</li>
    <li><code>BALLDONTLIE_KEY</code> – für balldontlie.io (NBA Daten)</li>
  </ul>
</section>
<?php endif; ?>

<script>
// Aktives Navigations-Element beim Scrollen hervorheben
const navLinks = document.querySelectorAll('#manual-nav a');
const sections = document.querySelectorAll('.manual-section');
const observer = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      navLinks.forEach(a => a.classList.remove('active-sec'));
      const id = e.target.id;
      const link = document.querySelector(`#manual-nav a[href="#${id}"]`);
      if (link) link.classList.add('active-sec');
    }
  });
}, { threshold: 0.25 });
sections.forEach(s => observer.observe(s));
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
