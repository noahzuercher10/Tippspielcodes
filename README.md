# tippspiel_projekt_blj
Ein Tippspiel das wir fuer die Projektphase des ZbW erstellen.
# Hajde be big Sinan

---

## Schnellstart (XAMPP)

1. **XAMPP** installieren, Apache + MySQL starten.
2. Diesen Ordner nach `C:\xampp\htdocs\` kopieren und in **`tipsspiel`** umbenennen.
3. <http://localhost/phpmyadmin> öffnen → Reiter **Importieren** → `sql/tippspiel.sql` wählen → OK.
4. <http://localhost/tipsspiel/> öffnen.

### Login-Daten der Beispiel-Accounts (Passwort fuer alle: `admin123`)

| Username | Rolle  | Hinweis                              |
| -------- | ------ | ------------------------------------ |
| admin    | admin  | Voller Zugriff (Spiele/Resultate/User/Geld verschenken) |
| noah     | user   | normaler Spieler, Startkapital 2'500 |
| sinan    | user   | normaler Spieler                     |

> Wichtig: Falls dein Ordner anders heisst als `tipsspiel`, wuerden alle
> internen Pfade `/tipsspiel/...` brechen. Entweder umbenennen oder die
> Pfade im Code ersetzen.

---

## Was es tut

* **Punktemodus** – exakter Tipp, Punkte nach Doku-Schema (10/5/1/1/3).
* **Geldmodus** – nur **Sieger oder Unentschieden** tippen.
  * Startkapital: **2'500**
  * Min-Einsatz pro Spiel: **10**
  * Max-Einsatz pro Spiel: **500** (faellt mit dem Guthaben mit, geht
    aber nie ueber 500)
  * Richtig getippt → Einsatz wird **verdoppelt** ausgezahlt
  * Falsch → Einsatz **weg**
  * Pleite (Guthaben < 10) → Admin muss Geld schenken (s. unten)
* **Gruppen** – User kann Gruppen erstellen oder ueber **Beitrittscode**
  beitreten. Pro Gruppe ein fester Modus + Liga.
* **Rangliste** – globale Modus-Rangliste + ein Tab pro eigener Gruppe
  (von rechts nach links angeordnet).
* **Profil** – Avatar mit Initialen + optionales eigenes
  **Hintergrundbild** (Upload-Button rechts vom Avatar).
* **Admin** – Sportarten/Ligen/Teams/Spiele anlegen, Resultate
  eintragen (verteilt automatisch Punkte und Geld), User loeschen,
  **Geld verschenken**, **Spielplan-Import via TheSportsDB**.

---

## Sportarten & Ligen (laut Doku-Entscheidung)

Die App ist mit 5 Sportarten vorbefuellt:

| Sportart   | Typ      | Beispiel-Ligen                                   |
| ---------- | -------- | ------------------------------------------------ |
| Fussball   | Team     | Schweizer Super League, Bundesliga, Premier League, La Liga, WM 2026 |
| Eishockey  | Team     | National League (CH), NHL                        |
| Basketball | Team     | NBA, EuroLeague                                  |
| Tennis     | Einzel   | ATP Tour, Grand Slam                             |
| Formel 1   | Einzel   | Saison 2026                                      |

Sample-Spiele sind in `sql/tippspiel.sql` enthalten — fuer jede Liga
mindestens 2 Beispiele und nur Teams aus der dazugehoerigen Sportart.

---

## Spielplan automatisch holen (TheSportsDB)

Wir nutzen die kostenlose **TheSportsDB**-API (kein Key noetig fuer
Basisendpoints): <https://www.thesportsdb.com/free_sports_api>

Pro Liga hinterlegen wir in der Tabelle `leagues.api_id` die TheSportsDB-
Liga-ID (z.B. Super League = `4344`, Bundesliga = `4331`, NBA = `4387`).

Der Endpunkt **`/api/import-from-thesportsdb.php?league_id=<id>`** holt
fuer eine Liga:
* die naechste Runde -> als `upcoming`-Spiele eintragen
* die letzte gespielte Runde -> als `finished` mit Resultat speichern
  und sofort die Tipps der User auswerten (Punkte und Geld werden
  verbucht).

**Aufruf**: nur Admins. Im Browser einfach
<http://localhost/tipsspiel/api/import-from-thesportsdb.php?league_id=1>
aufrufen, oder einen Button im Admin-Dashboard ergaenzen.

Der Admin kann jederzeit von Hand korrigieren (Resultat eintragen,
Spiele anlegen, Teams aendern).

---

## Schritt-fuer-Schritt: Datenbank verbinden

### 1. XAMPP installieren
* Download: <https://www.apachefriends.org/de/>
* Installieren (Standardpfad `C:\xampp`).
* **XAMPP Control Panel** öffnen → **Apache** start, **MySQL** start.

### 2. Projektordner ins htdocs
Kopiere den Ordner `Tippspielcodes` nach `C:\xampp\htdocs\` und
benenne ihn um in `tipsspiel`.

### 3. Datenbank importieren
* <http://localhost/phpmyadmin> öffnen
* Reiter **"Importieren"**
* Datei `sql/tippspiel.sql` waehlen → **OK**

Das Skript loescht eine evtl. vorhandene DB `tippspiel`, legt sie neu
an und fuellt Beispiel-Daten.

Alternativ ueber Konsole:
```cmd
cd C:\xampp\mysql\bin
mysql.exe -u root < C:\xampp\htdocs\tipsspiel\sql\tippspiel.sql
```

### 4. DB-Zugang (`config/db.php`)
Standard-XAMPP funktioniert direkt. Bei abweichendem Setup:
```php
const DB_HOST = '127.0.0.1';
const DB_NAME = 'tippspiel';
const DB_USER = 'root';
const DB_PASS = '';
```

### 5. Im Browser oeffnen
<http://localhost/tipsspiel/> – mit `admin / admin123` einloggen.

---

## Projektstruktur

```
tipsspiel/
├── index.php                Login
├── register.php             Registrierung
├── config/db.php            DB-Zugang
├── includes/
│   ├── auth.php             Login/Logout/Session
│   ├── functions.php        Punkte- + Geldlogik
│   ├── header.php           Topbar + Nav (server-seitig)
│   └── footer.php
├── pages/
│   ├── home.php             Dashboard (Punkte + Guthaben)
│   ├── groups.php           Meine Gruppen + Modal-Dialoge
│   ├── leaderboard.php      Globale + Gruppen-Rangliste
│   ├── sports.php           Tippen-Workflow
│   ├── profile.php          Avatar + Hintergrundbild-Upload
│   └── admin.php            Admin-Dashboard
├── api/
│   ├── login.php / register.php / logout.php / me.php
│   ├── sports.php / leagues.php / matches.php
│   ├── bets.php             Tipp speichern (Punkte/Money)
│   ├── my-bets.php          Eigene Tipps
│   ├── groups.php           Gruppen-CRUD
│   ├── leaderboard.php      Globale Rangliste
│   ├── upload-background.php Hintergrundbild-Upload
│   ├── admin.php            Admin-Aktionen (inkl. gift_money)
│   └── import-from-thesportsdb.php  Spielplan-Import
├── css/style.css
├── js/  app.js sports.js groups.js admin.js
├── img/backgrounds/         Hintergrundbilder der User (wird beim 1. Upload erstellt)
└── sql/tippspiel.sql
```

---

## Punktesystem (validiert gegen alle Doku-Beispiele)

| Tipp | Resultat | Punkte |
| ---- | -------- | -----: |
| 1:1  | 2:3      | 0      |
| 2:1  | 2:3      | 1      |
| 2:3  | 2:3      | 10     |
| 3:2  | 2:3      | 0      |
| 2:5  | 2:3      | 6      |
| 1:2  | 2:3      | 8      |
| 4:3  | 2:3      | 1      |

---

## Geldlogik (Spec)

```
balance < 10              -> max stake = 0  (Admin muss aushelfen)
balance >= 10             -> max stake = min(500, balance)
korrekter Sieger-Tipp     -> Auszahlung = Einsatz * 2
falsch                    -> Auszahlung = 0  (Einsatz war beim Setzen weg)
```

## Admin: Geld verschenken
Im Admin-Dashboard hat jede User-Zeile ein Eingabefeld + Button
"Geld geben". Der Betrag wird auf das User-Wallet **und** in alle
Gruppen-Mitgliedschaften des Users gutgeschrieben.

## Profil: Hintergrundbild
Profilseite oeffnen → Button "Hintergrundbild hinzufuegen" rechts neben
dem Avatar. Datei waehlen (jpg/png/webp/gif, max 4 MB) → wird in
`img/backgrounds/` gespeichert. Solange kein eigenes Bild gesetzt ist,
bleibt der Standard-Hintergrund. Ueber "Standard wiederherstellen"
laesst es sich wieder zuruecksetzen.

---

## Troubleshooting

| Symptom                                            | Loesung                                                       |
| -------------------------------------------------- | ------------------------------------------------------------- |
| `DB-Fehler: Unknown DB ...`                        | DB nicht importiert → Schritt 3 nochmals                      |
| `DB-Fehler: Access denied for user 'root'`         | `config/db.php` anpassen                                      |
| Login geht nicht trotz `admin / admin123`          | Import nicht vollstaendig → Schritt 3 wiederholen             |
| 404 nach Login                                     | Ordner heisst nicht `tipsspiel` → umbenennen                  |
| Hintergrundbild laesst sich nicht hochladen        | `img/backgrounds/` muss schreibbar sein (XAMPP normalerweise OK) |
| `import-from-thesportsdb.php` liefert leeren Datenstand | Liga hat keine `api_id`. In `leagues.api_id` muss die TheSportsDB-ID stehen. |
